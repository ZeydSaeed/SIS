# Cache Invalidation Map

> **Golden Rule:** PostgreSQL = Source of Truth. Redis = Performance Layer Only.  
> **Never:** Application → Redis → Database as truth chain.

## Architecture

```
Write Request
     ↓
PostgreSQL (commit)
     ↓
Invalidate affected cache keys
     ↓
Next read → cache miss → PostgreSQL → repopulate Redis
```

---

## Invalidation Map

| Data | Cache Key Pattern | TTL | Invalidate On |
|------|-------------------|-----|---------------|
| Academic years | `academic:years:active` | 4h | academic_years CRUD |
| Current year | `academic:year:current` | 4h | is_current changed |
| Schools (directorate) | `org:schools:{directorate_id}` | 4h | schools CRUD |
| School detail | `org:school:{school_id}` | 4h | school update |
| Branches | `org:branches:{school_id}` | 4h | branches CRUD |
| Rooms | `org:rooms:{branch_id}` | 4h | rooms CRUD |
| Grade levels | `academic:grade_levels` | 24h | grade_levels CRUD |
| Subjects (school) | `curriculum:subjects:{school_id}` | 1h | subjects/curriculum change |
| Curriculum | `curriculum:{school_id}:{year_id}:{grade_id}` | 1h | curriculum_subjects change |
| Sections (school/year) | `enrollment:sections:{school_id}:{year_id}` | 30m | sections CRUD |
| User permissions | `auth:permissions:{user_id}` | 15m | user_roles change |
| User roles | `auth:roles:{user_id}` | 15m | user_roles change |
| System settings | `settings:{school_id\|global}` | 1h | system_settings update |
| School dashboard | `dashboard:school:{school_id}:{year_id}` | 5m | daily_summary refresh / MV refresh |
| Directorate dashboard | `dashboard:directorate:{directorate_id}:{year_id}` | 5m | MV refresh |
| Timetable (section) | `timetable:section:{section_id}:{year_id}` | 10m | schedules CRUD |

---

## Never Cache as Primary Read

| Data | Why |
|------|-----|
| Individual attendance records | Real-time accuracy required |
| Student grades (just entered) | Write-after-read consistency |
| Financial transactions | Must match DB exactly |
| Enrollment status after write | Use Primary DB read |
| Audit logs | Append-only, query from DB/replica |

---

## Invalidation Patterns (Laravel)

### On Model Update

```php
// App\Observers\SchoolObserver
public function updated(School $school): void
{
    Cache::forget("org:school:{$school->id}");
    Cache::forget("org:schools:{$school->directorate_id}");
    Cache::forget("dashboard:school:{$school->id}:*"); // tag-based if using cache tags
}
```

### Tag-Based (Redis)

```php
Cache::tags(["school:{$schoolId}"])->flush();
```

Requires Redis cache driver with tagging support.

### After Batch Attendance

```php
// After AttendanceBatchService completes
Cache::forget("dashboard:school:{$schoolId}:{$yearId}");
// daily_section_summary updated in DB — dashboard cache invalidated
```

---

## Dashboard Consistency Model

| Layer | Consistency | Latency |
|-------|-------------|---------|
| Operational attendance write | **Strong** — PostgreSQL transaction | Immediate |
| daily_section_summary | **Eventual** — updated after batch (seconds) | < 5s |
| Materialized views | **Eventual** — hourly/nightly refresh | 5m–24h |
| Redis dashboard cache | **Eventual** — TTL 5m | 5m max stale |

**UI must show:** `last_updated_at` on dashboards.

---

## TTL Guidelines

| Change Frequency | TTL |
|-----------------|-----|
| Rarely (grade levels) | 4–24 hours |
| Monthly (curriculum) | 1–4 hours |
| Daily (sections) | 30 minutes |
| Dashboard aggregates | 5 minutes |
| Permissions | 15 minutes (security vs performance balance) |

---

## Stampede Protection

```php
Cache::remember("curriculum:subjects:{$schoolId}", 3600, function () {
    return Subject::where(...)->get();
});
// Use lock for expensive rebuilds:
Cache::lock("lock:subjects:{$schoolId}", 10)->block(5, fn () => ...);
```

---

## Monitoring

| Metric | Target |
|--------|--------|
| Redis hit ratio | > 90% for reference data |
| Cache memory | < 80% max |
| Invalidation errors | 0 — log failures |

---

## Related

- [scalability-and-async.md](./scalability-and-async.md)
- [batch-write-patterns.md](./batch-write-patterns.md) — summary refresh
- [adr/ADR-004-redis.md](./adr/ADR-004-redis.md)
