# Per-Node User Traffic Tracking - Implementation Summary

## Changes Made

### 1. Database Migration
**File:** `database/migrations/2025_11_29_000000_add_server_id_to_stat_user.php`

- Added `server_id` column to `v2_stat_user` table
- Column is **nullable** for backward compatibility with existing data
- Added composite index `user_server_record_idx` for efficient per-node queries

### 2. StatUserJob Updates
**File:** `app/Jobs/StatUserJob.php`

Updated all three database-specific methods to include `server_id`:

- `processUserStatForSqlite()` - Added `server_id` to WHERE clause and CREATE
- `processUserStatForOtherDatabases()` - Added `server_id` to upsert unique key
- `processUserStatForPostgres()` - Added `server_id` to ON CONFLICT clause

### 3. StatUser Model
**File:** `app/Models/StatUser.php`

- Added `@property int|null $server_id` documentation
- Added `server()` relationship method to Server model

## How It Works

### Node Identification Flow

1. **Node sends traffic report:**
   ```http
   POST /api/v1/server/UniProxy/push?node_type=vmess&node_id=5&token=xxx
   ```

2. **Middleware extracts node info:**
   - `Server` middleware validates `node_id` and `token`
   - Loads full server object from database
   - Injects into `$request->attributes->set('node_info', $serverInfo)`

3. **Controller passes to service:**
   - `$server = $request->attributes->get('node_info')`
   - Contains `$server->id`, `$server->rate`, etc.

4. **StatUserJob now uses `server_id`:**
   - Creates/updates records with composite key: `(user_id, server_id, server_rate, record_at, record_type)`
   - Traffic from different nodes is now stored separately

### Record Creation Logic

**NEW behavior with `server_id`:**

| Scenario | Result |
|----------|--------|
| Same user, same node, same day | **UPDATE** existing record (accumulate traffic) |
| Same user, different node, same day | **CREATE** separate records per node |
| Same user, same node, next day | **CREATE** new record (new day) |

**OLD behavior (without `server_id`):**
- Same user, same rate, same day → Single aggregated record (couldn't differentiate nodes)

## Backward Compatibility

### ✅ Existing Queries Continue to Work

All existing queries that aggregate user traffic will work unchanged:

```php
// Example: User consumption ranking (aggregates across all nodes)
StatUser::select([
    'user_id',
    DB::raw('sum(u) as u'),
    DB::raw('sum(d) as d'),
    DB::raw('sum(u) + sum(d) as total')
])
->where('record_at', '>=', $startAt)
->where('record_at', '<', $endAt)
->groupBy('user_id')
->orderBy('total', 'DESC')
->get();
```

This query:
- Works with old records (where `server_id` is NULL)
- Works with new records (where `server_id` is populated)
- Correctly sums traffic across all nodes per user

### ✅ API Endpoints Unchanged

- **No changes** to admin API endpoints
- **No changes** to user API endpoints
- **No changes** to node API endpoints (they already send `node_id`)

### ✅ Legacy Data Preserved

- Old records without `server_id` remain valid
- Represent aggregated historical data
- New records will have `server_id` populated

## New Capabilities

### Per-Node User Traffic Analysis

You can now query traffic per user per node:

```php
// Get user's traffic breakdown by node
StatUser::where('user_id', $userId)
    ->where('record_at', '>=', $startDate)
    ->whereNotNull('server_id')  // Only new records
    ->groupBy('server_id')
    ->selectRaw('server_id, SUM(u) as upload, SUM(d) as download')
    ->get();
```

### Example Use Cases

1. **Identify which nodes a user uses most**
2. **Detect unusual traffic patterns per node**
3. **Analyze node-specific user behavior**
4. **Generate per-node billing reports**

## Migration Instructions

1. **Run the migration:**
   ```bash
   php artisan migrate
   ```

2. **Deploy code changes** - No downtime required

3. **Verify:**
   - Old data remains queryable
   - New traffic reports populate `server_id`
   - Existing dashboards continue to work

## Database Schema

### Before
```sql
CREATE TABLE v2_stat_user (
    id INT PRIMARY KEY,
    user_id INT,
    server_rate DECIMAL(10),
    u BIGINT,
    d BIGINT,
    record_type CHAR(2),
    record_at INT,
    created_at INT,
    updated_at INT,
    UNIQUE KEY (server_rate, user_id, record_at)
);
```

### After
```sql
CREATE TABLE v2_stat_user (
    id INT PRIMARY KEY,
    user_id INT,
    server_id INT NULL,  -- NEW
    server_rate DECIMAL(10),
    u BIGINT,
    d BIGINT,
    record_type CHAR(2),
    record_at INT,
    created_at INT,
    updated_at INT,
    UNIQUE KEY (server_rate, user_id, record_at),  -- Old unique key still exists
    INDEX user_server_record_idx (user_id, server_id, record_at)  -- NEW
);
```

## Testing Checklist

- [ ] Run migration successfully
- [ ] Node reports traffic → `server_id` is populated
- [ ] Same user on different nodes → separate records created
- [ ] Same user on same node → traffic accumulates in single record
- [ ] Existing admin dashboards show correct totals
- [ ] User traffic logs display correctly
- [ ] Old records (server_id=NULL) are still queryable
- [ ] SUM queries aggregate correctly across nodes

## Notes

- The `server_id` is sourced from the `node_id` parameter that nodes already send
- No changes needed to node software - they already provide this information
- The composite unique key now effectively includes `server_id` in the WHERE clauses
- PostgreSQL ON CONFLICT clause updated to match new unique constraint
