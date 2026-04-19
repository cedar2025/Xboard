#!/bin/sh
set -e

# Resolve the binding scheme based on whether the embedded Caddy is enabled.
#
# When ENABLE_CADDY=true (default), Caddy owns the public port (7001) and
# dispatches traffic internally; Octane and ws-server bind to localhost only
# so they cannot be reached from outside the container.
#
# When ENABLE_CADDY=false (e.g. an external reverse proxy or split mode),
# Octane takes the public port directly to keep behaviour identical to the
# pre-Caddy releases.
if [ "${ENABLE_CADDY}" = "true" ]; then
    : "${OCTANE_HOST:=127.0.0.1}"
    : "${OCTANE_PORT:=7002}"
    : "${WS_HOST:=127.0.0.1}"
    : "${WS_PORT:=8076}"
    : "${CADDY_LISTEN_PORT:=7001}"
else
    : "${OCTANE_HOST:=0.0.0.0}"
    : "${OCTANE_PORT:=7001}"
    : "${WS_HOST:=0.0.0.0}"
    : "${WS_PORT:=8076}"
fi
export OCTANE_HOST OCTANE_PORT WS_HOST WS_PORT CADDY_LISTEN_PORT
export OCTANE_INTERNAL_PORT="${OCTANE_PORT}"

# ---------------------------------------------------------------------------
# Auto-tune worker counts based on the host (CPU + memory).
#
# Heuristic: each PHP worker (Octane/Horizon) costs ~80 MiB. After reserving
# ~300 MiB for the always-on processes (caddy/redis/ws-server/masters), divide
# the remaining budget across roles.  Any user-set ENV wins.
# ---------------------------------------------------------------------------
detect_cpus() {
    if [ -r /sys/fs/cgroup/cpu.max ]; then
        # cgroup v2: "<quota> <period>" or "max <period>"
        read -r q p < /sys/fs/cgroup/cpu.max 2>/dev/null
        if [ "$q" != "max" ] && [ -n "$q" ] && [ -n "$p" ] && [ "$p" -gt 0 ]; then
            echo $(( (q + p - 1) / p ))
            return
        fi
    fi
    nproc 2>/dev/null || echo 1
}

detect_mem_mib() {
    if [ -r /sys/fs/cgroup/memory.max ]; then
        m=$(cat /sys/fs/cgroup/memory.max 2>/dev/null)
        if [ "$m" != "max" ] && [ -n "$m" ]; then
            echo $(( m / 1024 / 1024 ))
            return
        fi
    fi
    # No cgroup limit: avoid over-provisioning on big hosts. Cap the assumed
    # budget to MEM_FALLBACK_MIB (default 1024) unless the user opts out by
    # setting it explicitly. Use whichever is smaller of MemAvailable and cap.
    avail=$(awk '/MemAvailable/ {print int($2/1024)}' /proc/meminfo 2>/dev/null || echo 1024)
    cap=${MEM_FALLBACK_MIB:-1024}
    [ "$avail" -lt "$cap" ] && echo "$avail" || echo "$cap"
}

CPUS=$(detect_cpus)
MEM_MIB=$(detect_mem_mib)

# Resource profile presets. RESOURCE_PROFILE selects ratios for the budget split:
#   minimal     - smallest possible footprint (~250-350 MiB), single octane worker,
#                 horizon capped to 1/1/1. Suitable for VPS with <=512 MiB RAM.
#   balanced    - default; ~80 MiB per worker, octane gets 25% of slots.
#   performance - larger reserves for opcache/caches, more aggressive horizon caps.
#   auto        - same as balanced.
: "${RESOURCE_PROFILE:=auto}"
case "$RESOURCE_PROFILE" in
    minimal)     RESERVED_MIB=200; SLOT_MIB=100; OCT_NUM=1; OCT_DEN=1; OCT_FORCE=1; auto_horizon_mem=128; auto_octane_gc=64 ;;
    performance) RESERVED_MIB=400; SLOT_MIB=70;  OCT_NUM=1; OCT_DEN=3; OCT_FORCE=0; auto_horizon_mem=384; auto_octane_gc=256 ;;
    balanced|auto|*) RESERVED_MIB=300; SLOT_MIB=80;  OCT_NUM=1; OCT_DEN=4; OCT_FORCE=0; auto_horizon_mem=256; auto_octane_gc=128 ;;
esac

BUDGET=$(( MEM_MIB - RESERVED_MIB ))
[ "$BUDGET" -lt "$SLOT_MIB" ] && BUDGET=$SLOT_MIB
SLOTS=$(( BUDGET / SLOT_MIB ))

clamp() { v=$1; lo=$2; hi=$3; [ "$v" -lt "$lo" ] && v=$lo; [ "$v" -gt "$hi" ] && v=$hi; echo "$v"; }

if [ "$OCT_FORCE" = "1" ]; then
    auto_octane=1
    auto_dp=1; auto_biz=1; auto_notif=1
else
    auto_octane=$(clamp $(( (SLOTS * OCT_NUM) / OCT_DEN )) 1 "$CPUS")
    remaining=$(( SLOTS - auto_octane - 2 ))
    [ "$remaining" -lt 3 ] && remaining=3
    auto_dp=$(clamp $(( remaining / 2 )) 1 $(( CPUS * 2 )))
    auto_biz=$(clamp $(( remaining / 4 )) 1 "$CPUS")
    auto_notif=$(clamp $(( remaining / 4 )) 1 "$CPUS")
fi

# User-set ENV always wins.
: "${OCTANE_WORKERS:=$auto_octane}"
: "${OCTANE_TASK_WORKERS:=1}"
: "${OCTANE_MAX_REQUESTS:=500}"
: "${OCTANE_GARBAGE_MB:=$auto_octane_gc}"
: "${OCTANE_MAX_EXECUTION_TIME:=60}"
: "${HORIZON_DATA_PIPELINE_MAX:=$auto_dp}"
: "${HORIZON_BUSINESS_MAX:=$auto_biz}"
: "${HORIZON_NOTIFICATION_MAX:=$auto_notif}"
: "${HORIZON_WORKER_MEMORY_MB:=$auto_horizon_mem}"
: "${HORIZON_WORKER_MAX_TIME:=0}"
: "${HORIZON_WORKER_MAX_JOBS:=0}"

export OCTANE_WORKERS OCTANE_TASK_WORKERS OCTANE_MAX_REQUESTS \
       OCTANE_GARBAGE_MB OCTANE_MAX_EXECUTION_TIME \
       HORIZON_DATA_PIPELINE_MAX HORIZON_BUSINESS_MAX HORIZON_NOTIFICATION_MAX \
       HORIZON_WORKER_MEMORY_MB HORIZON_WORKER_MAX_TIME HORIZON_WORKER_MAX_JOBS \
       RESOURCE_PROFILE

echo "[entrypoint] Auto-tune (profile=${RESOURCE_PROFILE}): cpus=${CPUS} mem=${MEM_MIB}MiB slots=${SLOTS} -> octane=${OCTANE_WORKERS} horizon(dp/biz/notif)=${HORIZON_DATA_PIPELINE_MAX}/${HORIZON_BUSINESS_MAX}/${HORIZON_NOTIFICATION_MAX} horizon_worker_mem=${HORIZON_WORKER_MEMORY_MB}MB"
echo "[entrypoint] Horizon supervisors use balance=auto with minProcesses=1, so they scale up to the cap on demand and back down when idle."

redis_reachable() {
    local host port
    host=$(grep -E '^REDIS_HOST=' /www/.env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
    port=$(grep -E '^REDIS_PORT=' /www/.env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
    command -v redis-cli >/dev/null 2>&1 || return 1
    [ -n "$host" ] || return 1
    case "$host" in
        /*) [ -S "$host" ] && redis-cli -s "$host" ping 2>/dev/null | grep -q PONG ;;
        *)  redis-cli -h "$host" -p "${port:-6379}" ping 2>/dev/null | grep -q PONG ;;
    esac
}

if [ ! -s /www/.env ] || ! grep -qE '^INSTALLED=(1|true)$' /www/.env || echo " $* " | grep -q ' xboard:install '; then
    echo "[entrypoint] Skipping xboard:update (not yet installed or running xboard:install)."
else
    if redis_reachable; then
        echo "[entrypoint] Running xboard:update (redis reachable, real drivers)..."
        php /www/artisan xboard:update --no-interaction || \
            echo "[entrypoint] WARNING: xboard:update failed; continuing so supervisor can boot anyway." >&2
    else
        echo "[entrypoint] Running xboard:update (redis not yet up, using array/sync drivers)..."
        CACHE_DRIVER=array QUEUE_CONNECTION=sync SESSION_DRIVER=array \
            php /www/artisan xboard:update --no-interaction || \
            echo "[entrypoint] WARNING: xboard:update failed; continuing so supervisor can boot anyway." >&2
    fi
fi

echo "[entrypoint] Starting services (caddy=${ENABLE_CADDY} web=${ENABLE_WEB} horizon=${ENABLE_HORIZON} ws=${ENABLE_WS_SERVER})..."
# Drop stale Octane/WorkerMan state files so the new master does not signal
# PIDs left over from a previous container run (causes Swoole kill EPERM).
rm -f /www/storage/logs/octane-server-state.json /www/storage/logs/xboard-ws-server.pid 2>/dev/null || true
chown -R www:www /www 2>/dev/null || true
exec "$@"
