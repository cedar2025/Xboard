<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('v2_user') || !Schema::hasColumn('v2_user', 'email')) {
            return;
        }

        $this->assertNoEmailCollisions();

        // Use bytewise comparison for MySQL and PostgreSQL; SQLite defaults to BINARY.
        if (DB::getDriverName() === 'mysql') {
            Schema::table('v2_user', function (Blueprint $table) {
                $table->string('email', 64)
                    ->charset('utf8mb4')
                    ->collation('utf8mb4_bin')
                    ->change();
            });
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "v2_user" ALTER COLUMN "email" TYPE varchar(64) COLLATE "C" USING "email"::varchar(64)');
        } elseif (DB::getDriverName() === 'sqlsrv') {
            Schema::table('v2_user', fn (Blueprint $table) => $table->dropUnique('email'));
            Schema::table('v2_user', function (Blueprint $table) {
                $table->string('email', 64)
                    ->collation('Latin1_General_100_BIN2')
                    ->change();
            });
            Schema::table('v2_user', fn (Blueprint $table) => $table->unique('email', 'email'));
        }

        DB::table('v2_user')
            ->select('id', 'email')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $email = User::normalizeEmail((string) $user->email);

                    if ($email !== $user->email) {
                        DB::table('v2_user')
                            ->where('id', $user->id)
                            ->update(['email' => $email]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('v2_user')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $hasLegacyCollisions = DB::table('v2_user')
                ->selectRaw('1')
                ->groupByRaw('email COLLATE utf8mb4_unicode_ci')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hasLegacyCollisions) {
                throw new RuntimeException(
                    'Cannot restore the legacy email collation while distinct email addresses collide under utf8mb4_unicode_ci.'
                );
            }

            Schema::table('v2_user', function (Blueprint $table) {
                $table->string('email', 64)
                    ->charset('utf8mb4')
                    ->collation('utf8mb4_unicode_ci')
                    ->change();
            });
        } elseif ($driver === 'pgsql') {
            $hasLegacyCollisions = DB::table('v2_user')
                ->selectRaw('1')
                ->groupByRaw('email COLLATE "default"')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hasLegacyCollisions) {
                throw new RuntimeException(
                    'Cannot restore the PostgreSQL database-default email collation while distinct addresses collide.'
                );
            }

            DB::statement('ALTER TABLE "v2_user" ALTER COLUMN "email" TYPE varchar(64) COLLATE "default" USING "email"::varchar(64)');
        } elseif ($driver === 'sqlsrv') {
            $hasLegacyCollisions = DB::table('v2_user')
                ->selectRaw('1')
                ->groupByRaw('email COLLATE DATABASE_DEFAULT')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hasLegacyCollisions) {
                throw new RuntimeException(
                    'Cannot restore the default email collation while distinct email addresses collide under DATABASE_DEFAULT.'
                );
            }

            Schema::table('v2_user', fn (Blueprint $table) => $table->dropUnique('email'));
            Schema::table('v2_user', function (Blueprint $table) {
                $table->string('email', 64)
                    ->collation('DATABASE_DEFAULT')
                    ->change();
            });
            Schema::table('v2_user', fn (Blueprint $table) => $table->unique('email', 'email'));
        }
    }

    private function assertNoEmailCollisions(): void
    {
        $seen = [];

        DB::table('v2_user')
            ->select('id', 'email')
            ->orderBy('id')
            ->chunkById(500, function ($users) use (&$seen) {
                foreach ($users as $user) {
                    $email = User::normalizeEmail((string) $user->email);

                    if (mb_strlen($email, 'UTF-8') > 64) {
                        throw new RuntimeException(
                            "Normalized email exceeds the 64-character column limit for user ID {$user->id}."
                        );
                    }

                    if (isset($seen[$email])) {
                        throw new RuntimeException(
                            "Email normalization collision between user IDs {$seen[$email]} and {$user->id}. Resolve these accounts before rerunning the migration."
                        );
                    }

                    $seen[$email] = $user->id;
                }
            });
    }
};
