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
Schema change impact analysis (schema-change-impact.md)
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

## Governance Stack

```text
DATABASE GOVERNANCE
│
├── DATABASE-GOVERNANCE.md              ← change control (this file)
├── DATABASE-ADAPTIVE-GOVERNANCE.md     ← optimization by measurement
├── DATABASE-INTELLIGENCE-LAYER.md      ← expert + learning + self-healing (v3.0)
├── DATABASE-KNOWLEDGE-BASE.md
├── DATABASE-OPTIMIZATION-LEARNING.md
├── SELF-HEALING-RUNBOOK.md
├── schema-change-impact.md             ← per-change impact analysis
├── DATABASE-CHANGE-CHECKLIST.md
├── INDEX-GOVERNANCE.md
├── DATA-LIFECYCLE-MATRIX.md
├── PERFORMANCE-BUDGET.md
├── capacity-planning.md                ← dynamic model (45K = baseline)
├── ZERO-DOWNTIME-MIGRATIONS.md
├── DATA-QUALITY-RULES.md
├── DR-RUNBOOK.md
└── adr/ (001–009)
```

**Principle:** DO NOT OPTIMIZE FOR A NUMBER. OPTIMIZE FOR A MEASURED WORKLOAD.

**Priority stack:**

```text
Correctness > Security > Integrity > Availability > Performance > Storage
```

Optimization must never change business correctness.

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
| Capacity model | `capacity-planning.md` (45K = baseline, not ceiling) |
| Adaptive optimization | `DATABASE-ADAPTIVE-GOVERNANCE.md` |
| Intelligence layer | `DATABASE-INTELLIGENCE-LAYER.md` |
| Expert knowledge base | `DATABASE-KNOWLEDGE-BASE.md` |
| Performance targets | `PERFORMANCE-BUDGET.md` |
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

- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [schema-change-impact.md](./schema-change-impact.md)
- [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md)
- [INDEX-GOVERNANCE.md](./INDEX-GOVERNANCE.md)
- [zero-downtime-migrations.md](./zero-downtime-migrations.md)
- [DATABASE-CHANGE-CHECKLIST.md](./DATABASE-CHANGE-CHECKLIST.md)
- [data-quality-rules.md](./data-quality-rules.md)
