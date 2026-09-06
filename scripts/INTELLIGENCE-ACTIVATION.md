# SIS Intelligence — PostgreSQL activation
# Run after installing PostgreSQL (Herd Pro / standalone) and setting DB_PASSWORD in .env

## 1. .env (already configured)

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sis
DB_USERNAME=postgres
DB_PASSWORD=your_password_here

INTELLIGENCE_ENABLED=true
INTELLIGENCE_AUTO_MAX_TIER=1
INTELLIGENCE_SELF_HEALING=true
INTELLIGENCE_PG_STAT=true
```

## 2. Create database

```sql
CREATE DATABASE sis ENCODING 'UTF8';
```

## 3. pg_stat_statements (postgresql.conf)

Add to PostgreSQL config, then restart PostgreSQL:

```
shared_preload_libraries = 'pg_stat_statements'
pg_stat_statements.track = all
```

Migration `2026_09_06_101000_enable_pg_stat_statements` runs `CREATE EXTENSION IF NOT EXISTS pg_stat_statements`.

## 4. Migrate & verify

```bash
php artisan migrate --force
php artisan intelligence:guardian health
php artisan schedule:list
```

## 5. Scheduler

**Linux/macOS cron:**

```bash
* * * * * cd /path/to/sis && php artisan schedule:run >> /dev/null 2>&1
```

**Windows (Task Scheduler):** run every minute:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/schedule-run.ps1
```

**Queue:** scheduler also runs `queue:work --stop-when-empty` each minute when `QUEUE_CONNECTION=database`.

## 6. UI

Open `/intelligence/recommendations` (authenticated) to approve Tier 2+ recommendations.

## SQLite fallback (dev without PostgreSQL)

Comment pgsql block and use:

```env
DB_CONNECTION=sqlite
```

Tier 1 ANALYZE and pg_stat will be skipped; monitoring + UI still work.
