---
name: database-change
description: >-
  Mandatory workflow for ANY database change in SIS — create table, alter table,
  add/drop column, add index, add FK, partition, or performance improvement.
  Use ALWAYS when touching migrations, models schema, indexes, constraints,
  or database-blueprint.md. Never skip this skill for database work.
---

# Database Change — Mandatory Workflow

> **Every** database change MUST follow this workflow and the reference files in `.cursor/`.
> No exceptions. No manual SQL in production. No schema changes without migration + blueprint update.

## Step 0 — Read Reference Files (Required Before Writing Code)

Read the relevant sections from these files **before** creating or editing anything:

| File | When to Read |
|------|-------------|
| `.cursor/architecture/database-blueprint.md` | Always — find or define the table |
| `.cursor/architecture/01-principles-and-layers.md` | Always — data types, soft delete, temporal rules |
| `.cursor/architecture/indexing-matrix.md` | Adding/changing indexes or FK columns |
| `.cursor/architecture/improvement-matrix.md` | Partitioning, performance improvements |
| `.cursor/brain/student-lifecycle.md` | Tables tied to enrollment, grades, attendance |
| `.cursor/architecture/DATABASE-CHANGE-CHECKLIST.md` | Always — complete the checklist |
| `.cursor/architecture/schema-change-impact.md` | Always — impact analysis per change |
| `.cursor/architecture/DATABASE-ADAPTIVE-GOVERNANCE.md` | Indexes, partitions, cache, MV decisions |
| `.cursor/architecture/PERFORMANCE-BUDGET.md` | Performance claims require before/after metrics |

## Step 1 — Classify the Change

| Type | Examples | Extra Requirements |
|------|----------|-------------------|
| **CREATE** | New table | Define in blueprint first if missing; pick correct schema |
| **ALTER** | Add/drop/modify column | Never drop academic history columns; prefer additive changes |
| **INDEX** | Add/drop/improve index | Must justify via query pattern + EXPLAIN; read INDEX-GOVERNANCE + adaptive rules |
| **FK** | Add/change relationship | Index FK column **only if** used in JOINs/WHERE (not automatic) |
| **CONSTRAINT** | CHECK, UNIQUE | Enforce in DB, not app-only |
| **PARTITION** | High-growth table | Only per improvement-matrix; academic_year_id or created_at |
| **DELETE** | Drop table/column | **Forbidden** for official academic data; require explicit user approval |

## Step 2 — Pre-Change Validation Checklist

Answer ALL before writing migration:

```
[ ] Table exists or is defined in database-blueprint.md
[ ] Correct PostgreSQL schema (organization, academic, students, …)
[ ] PK is BIGINT IDENTITY ($table->id())
[ ] academic_year_id included if table is academic/transactional
[ ] FK constraints defined with restrictOnDelete() (not cascade for academic data)
[ ] FK columns have B-Tree index ONLY if used in JOINs/WHERE (not every FK)
[ ] No TINYINT — use smallInteger() for status/enums
[ ] Timestamps: timestamps() on transactional tables
[ ] Temporal entities have effective_from / effective_to
[ ] No hard-delete pattern for academic records — use status + effective_to
[ ] CHECK constraints for bounded values (grades 0–100, etc.)
[ ] File content stored in object storage — DB stores metadata only
[ ] Index additions justified (not speculative)
[ ] Partition only if decision tree in DATABASE-ADAPTIVE-GOVERNANCE says YES
[ ] schema-change-impact.md checklist completed for this change
[ ] Performance claims include before/after metrics if optimizing
```

## Step 3 — Write Migration

### Naming
```
YYYY_MM_DD_HHMMSS_{action}_{schema}_{table}.php

Examples:
2026_09_05_100000_create_organization_schools_table.php
2026_09_05_100001_add_status_index_to_students_students_table.php
```

### Migration Template
```php
public function up(): void
{
    Schema::create('organization.schools', function (Blueprint $table) {
        $table->id();
        $table->foreignId('directorate_id')
            ->constrained('organization.directorates')
            ->restrictOnDelete();
        $table->string('code', 20)->unique();
        $table->string('name');
        $table->smallInteger('status')->default(1);
        $table->timestamps();

        $table->index(['directorate_id']);
        $table->index(['status']);
    });
}

public function down(): void
{
    Schema::dropIfExists('organization.schools');
}
```

### PostgreSQL Rules (Non-Negotiable)
- PKs: `$table->id()` → BIGINT
- Status: `$table->smallInteger('status')` — never tinyInteger
- Money: `$table->decimal('amount', 12, 2)`
- Never skip FK: `->constrained()->restrictOnDelete()`
- Never `onDelete('cascade')` on academic history tables

## Step 4 — Update Model (if applicable)

```
app/Models/{Domain}/{ModelName}.php
```

- Match blueprint columns and casts
- Define relationships per blueprint FK map
- Add `scopeForAcademicYear()` for academic tables
- No `$table->softDeletes()` on official academic records

## Step 5 — Update Reference Documentation (Mandatory)

After migration, **always** update:

1. **`.cursor/architecture/database-blueprint.md`**
   - Add/modify table definition (columns, types, FK, indexes)
   - Update table count if new table

2. **`.cursor/architecture/indexing-matrix.md`** (if indexes changed)
   - Add row to per-table index matrix

3. **Migration log** in blueprint or a note if deviation from blueprint (with reason)

> The blueprint MUST stay in sync with actual migrations. If they diverge, the blueprint is wrong.

## Step 6 — Post-Change Verification

```
[ ] Migration runs: php artisan migrate
[ ] Migration rolls back: php artisan migrate:rollback --step=1
[ ] Model matches migration columns
[ ] database-blueprint.md updated
[ ] indexing-matrix.md updated (if applicable)
[ ] No TINYINT, no missing FK, no academic hard-delete
[ ] PHPStan passes on affected models
```

## Forbidden Actions

| Action | Why |
|--------|-----|
| Manual SQL in production | Breaks migration versioning |
| `Schema::drop()` on academic tables | Destroys history |
| `onDelete('cascade')` on enrollments/grades/attendance | Cascading data loss |
| `tinyInteger` / TINYINT | Not PostgreSQL standard — use smallInteger |
| Index without query justification | Index bloat |
| Schema change without blueprint update | Reference docs become lies |
| `SELECT *` driving new indexes without EXPLAIN | Wrong optimization |
| Storing PDF/image blobs in DB | Use object storage + metadata table |

## Decision Tree

```
User requests DB change
        ↓
Read database-blueprint.md — table defined?
        ↓ No → Add to blueprint FIRST, then migrate
        ↓ Yes
Read principles + indexing-matrix
        ↓
Complete pre-change checklist
        ↓
Write migration + model
        ↓
Update blueprint + indexing-matrix
        ↓
Verify migrate up/down
```

## When User Says "Improve Performance"

1. Read `.cursor/architecture/DATABASE-ADAPTIVE-GOVERNANCE.md`
2. Read `.cursor/architecture/indexing-matrix.md`
3. Identify slow query pattern — require EXPLAIN ANALYZE before/after
4. Check if existing index covers the query
5. Document evidence: P95 before → P95 after (PERFORMANCE-BUDGET.md)
6. Add index via migration — not raw SQL
7. Update indexing-matrix.md with new index entry

Never add indexes "just in case" — every index must have measured query purpose.
Never optimize for a fixed student count — optimize for measured workload.
