# Zero-Downtime Migrations

> **Pattern:** Expand → Migrate → Contract  
> **When:** Production with active users — especially 45K student dataset

## Never Do in Production (Direct)

```sql
-- ❌ Immediate breaking change
ALTER TABLE students.students DROP COLUMN old_field;
ALTER TABLE enrollment.enrollments ALTER COLUMN status TYPE VARCHAR(50);
CREATE INDEX ix_huge ON attendance.records (student_id);  -- locks table
```

---

## Pattern: Expand → Migrate → Contract

### Example — Rename Column

**Phase 1 — Expand:** Add new column

```sql
ALTER TABLE students.students ADD COLUMN full_name_v2 VARCHAR(255);
-- App: still reads/writes old column
```

**Phase 2 — Dual write:** Application writes both

```php
$student->full_name = $name;
$student->full_name_v2 = $name;
$student->save();
```

**Phase 3 — Backfill:** Fill new column for existing rows

```sql
UPDATE students.students SET full_name_v2 = full_name WHERE full_name_v2 IS NULL;
-- Run in batches of 10,000 via queue job
```

**Phase 4 — Switch reads:** App reads new column

```php
// Model accessor or direct full_name_v2
```

**Phase 5 — Verify:** Monitor errors, compare values

**Phase 6 — Contract:** Drop old column

```sql
ALTER TABLE students.students DROP COLUMN full_name;
ALTER TABLE students.students RENAME COLUMN full_name_v2 TO full_name;
```

Each phase = **separate migration + deploy**.

---

## Safe Index Creation

```sql
-- ✅ No long table lock on large tables
CREATE INDEX CONCURRENTLY ix_attendance_student_date
ON attendance.records (student_id, attendance_date);
```

Laravel migration:

```php
public function up(): void
{
    DB::statement('
        CREATE INDEX CONCURRENTLY IF NOT EXISTS ix_attendance_student_date
        ON attendance.records (student_id, attendance_date)
    ');
}
```

**Note:** `CONCURRENTLY` cannot run inside transaction — disable `$withinTransaction` if needed.

---

## Adding NOT NULL Column

```sql
-- Phase 1: Add nullable
ALTER TABLE enrollment.enrollments ADD COLUMN new_field SMALLINT;

-- Phase 2: Backfill default
UPDATE enrollment.enrollments SET new_field = 1 WHERE new_field IS NULL;

-- Phase 3: Add NOT NULL (after all rows filled)
ALTER TABLE enrollment.enrollments ALTER COLUMN new_field SET NOT NULL;
```

---

## Adding FK to Large Table

```sql
-- Phase 1: Add column nullable, no FK
ALTER TABLE attendance.records ADD COLUMN verified_by BIGINT;

-- Phase 2: Backfill if needed

-- Phase 3: Add FK NOT VALID (PostgreSQL 9.1+)
ALTER TABLE attendance.records
    ADD CONSTRAINT fk_verified_by
    FOREIGN KEY (verified_by) REFERENCES security.users(id)
    NOT VALID;

-- Phase 4: Validate without blocking writes long
ALTER TABLE attendance.records VALIDATE CONSTRAINT fk_verified_by;
```

---

## Partition Addition (New Academic Year)

```sql
-- Before school year starts — no downtime
CREATE TABLE attendance.records_y2027
    PARTITION OF attendance.records
    FOR VALUES IN (3);

CREATE INDEX ON attendance.records_y2027 (student_id, attendance_date);
```

Schedule via migration before enrollment opens.

---

## Dangerous Operations — Require Maintenance Window

| Operation | Alternative |
|-----------|-------------|
| DROP TABLE | Archive + rename first |
| ALTER TYPE | New column + backfill + switch |
| REINDEX TABLE (non-concurrent) | REINDEX CONCURRENTLY |
| VACUUM FULL | Regular autovacuum + monitoring |

---

## Migration Deployment Checklist

- [ ] Expand phase deployed and stable 24h+
- [ ] Backfill job completed — row count verified
- [ ] Contract phase scheduled in low-traffic window
- [ ] Rollback plan: revert app before contract migration
- [ ] blueprint.md updated

---

## Related

- [DATABASE-GOVERNANCE.md](./DATABASE-GOVERNANCE.md)
- [database-change/SKILL.md](../skills/database-change/SKILL.md)
