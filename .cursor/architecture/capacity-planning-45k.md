# Capacity Planning — 45K Baseline Snapshot

> **⚠️ This file is a baseline snapshot.**  
> **Authoritative dynamic model:** [capacity-planning.md](./capacity-planning.md)  
> **Adaptive rules:** [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)

## Baseline (2026)

```
20 schools × 5 departments × 3 stages × 3 sections × 50 students = 45,000 students
900 sections · ~1,500 teachers
```

## Baseline Volume Estimate

| Table | Formula (baseline vars) | Per Year | 10 Years |
|-------|------------------------|----------|----------|
| attendance.records | 45K × 200 days × 5 sessions | ~45M | ~450M |
| student_grades | 45K × 15 × 4 | ~2.7M | ~27M |
| daily_section_summary | 900 × 200 | 180K | 1.8M |

**Recalculate** when variables change — use capacity-planning.md formulas.

## Baseline Infrastructure (Starting Point)

| Component | Spec |
|-----------|------|
| PostgreSQL Primary | 16 vCPU, 32 GB, 500 GB NVMe |
| Read Replica | 8 vCPU, 16 GB |
| Redis | 4 GB |
| App × 2 | 4 vCPU, 8 GB each |

Right-size when measurements exceed PERFORMANCE-BUDGET thresholds.

## For Full Dynamic Model

→ [capacity-planning.md](./capacity-planning.md)
