# PHASE 3C.10 — PARTITIONING DESIGN

**No partitions created.**

## Assessment principle

Do NOT partition because academic years exist. Partition only when volume + pruning + maintenance justify complexity (FK/UNIQUE/RLS cost).

## Baseline assumptions (ASSUMPTION — confirm capacity)

| Variable | Value | Label |
|----------|-------|-------|
| Active students baseline | 45,000 | ASSUMPTION from capacity docs |
| Schools | 20 | ASSUMPTION |
| Completions/year (eligible seniors) | ~8,000–15,000 | ASSUMPTION |
| Versions per completion (avg) | 1.5–3 | ASSUMPTION |
| Evidence items per evaluation | 10–40 | ASSUMPTION |
| Approvals/year | ~completions | ASSUMPTION |
| Awards/year | ≤ completions | ASSUMPTION |

## Per-table recommendation

| Table | Expected rows/10y | Partition? | Strategy | Rationale |
|-------|-------------------|------------|----------|-----------|
| eligibility_policies | <1k | NO | — | Catalog |
| eligibility_policy_versions | <10k | NO | — | Catalog |
| requirement_* | <50k | NO | — | Catalog |
| completion_outcomes | ~50k–150k | NO | — | One per enrollment grain; modest |
| completion_outcome_versions | ~100k–400k | NO initially | Revisit RANGE academic_year if >5M | FK uniqueness simpler without partition |
| requirement_evaluations | ~1M–5M | NO initially | Revisit later | Highest growth; still manageable without partition at baseline |
| evidence_sets | ~versions | NO | — | 1:1 |
| evidence_items | ~5M–20M | **WATCH** | Possible HASH/RANGE later | Highest volume; partition only with measured evidence |
| graduation_approvals | ~100k–300k | NO | — | |
| graduation_awards | ~outcomes | NO | — | |
| graduation_award_versions | ~outcomes×2 | NO | — | |
| outcome_supersessions | low | NO | — | |
| revocation_records | low | NO | — | |

## Academic year

- **Business attribute:** `academic_year_id` on outcomes/awards (denormalized from enrollment) — YES.  
- **Partition key:** NOT required at launch for these tables.

## FK / UNIQUE implications if partitioned later

- Unique constraints must include partition key.  
- Composite FKs become harder across partitions.  
- Prefer delaying partition until measured.

## Verdict

**No unjustified partitioning.** Explicitly: **NO PARTITION** for Phase 3C.10 launch design.
