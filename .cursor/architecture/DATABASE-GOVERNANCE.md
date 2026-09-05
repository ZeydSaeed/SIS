# Database Governance

> **Authority:** `database-blueprint.md` is the single source of truth for schema.  
> **Enforcement:** `.cursor/skills/database-change/SKILL.md` + `DATABASE-CHANGE-CHECKLIST.md`

## Maturity Levels

| Level | Meaning | Current Status |
|-------|---------|----------------|
| **Architecture Ready** | Design is correct and documented | ✅ Achieved |
| **Development Ready** | Can start building with guides | ✅ Achieved |
| **Production Proven** | Tested with measured numbers | ⏳ Not yet |

---

## Forbidden (Never Without ADR + Approval)

| Action | Why |
|--------|-----|
| Direct production schema changes (manual SQL) | Breaks migration versioning |
| Unreviewed migration merge to main | Data integrity risk |
| Dropping column without deprecation phase | Breaks running app |
| Removing FK without ADR | Orphan data |
| Creating index without justification | Write overhead, bloat |
| Changing enum/status semantics silently | Breaks reports and history |
| Hard-delete official academic records | Legal/historical violation |
| Storing files (PDF/images) as blobs in DB | Use object storage |
| Using Redis as source of truth | PostgreSQL is authority |

---

## Mandatory Workflow

```
Change Request
     ↓
Read database-blueprint.md + DATABASE-CHANGE-CHECKLIST.md
     ↓
Write migration (versioned)
     ↓
Code review (schema + performance impact)
     ↓
Test: migrate up + rollback
     ↓
Update database-blueprint.md (always)
     ↓
Update indexing-matrix.md (if indexes changed)
     ↓
Deploy staging → verify → production
     ↓
Post-deploy: ANALYZE if bulk change
```

---

## Migration Review Checklist

- [ ] Matches blueprint or blueprint updated first
- [ ] FK with explicit `restrictOnDelete()` on academic tables
- [ ] No TINYINT — use `smallInteger`
- [ ] Indexes justified (see INDEX-GOVERNANCE.md)
- [ ] Large table changes follow zero-downtime-migrations.md
- [ ] Rollback tested
- [ ] No secrets in migration files

---

## Single Source of Truth

| Topic | Authoritative File |
|-------|-------------------|
| Table count & schema | `database-blueprint.md` (89 tables) |
| Index strategy | `indexing-matrix.md` + `INDEX-GOVERNANCE.md` |
| Column meanings | `database-dictionary.md` |
| Priorities (45K) | `improvement-matrix.md` |
| Architecture decisions | `adr/*.md` |

**Rule:** If WORK-PLAN or other docs disagree with blueprint table count — **blueprint wins**.

---

## Roles & Ownership

| Role | Responsibility |
|------|----------------|
| Developer | Migration + blueprint sync + checklist |
| Reviewer | FK, indexes, performance impact |
| DBA/Ops | Autovacuum, backup, partition maintenance |
| Tech Lead | ADR approval for structural changes |

---

## Data Classification

| Class | Examples | Rules |
|-------|----------|-------|
| PII | national_id, names, phone | Encrypt in transit; minimal audit exposure |
| Academic Official | grades, attendance, certificates | Never hard-delete |
| Financial | payments, fees | Idempotency + audit |
| Operational | queue jobs, cache | Ephemeral OK |

---

## Related Documents

- [INDEX-GOVERNANCE.md](./INDEX-GOVERNANCE.md)
- [zero-downtime-migrations.md](./zero-downtime-migrations.md)
- [DATABASE-CHANGE-CHECKLIST.md](./DATABASE-CHANGE-CHECKLIST.md)
- [data-quality-rules.md](./data-quality-rules.md)
