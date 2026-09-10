# PHASE 3C.10 — SCALE MODEL

**ASSUMPTION-heavy.** Confirm before implementation capacity sign-off.

## Assumptions

| Input | Value | Label |
|-------|-------|-------|
| Schools | 20 | ASSUMPTION (45K baseline) |
| Completions evaluated / year | 12,000 | ASSUMPTION |
| Avg completion versions | 2 | ASSUMPTION |
| Avg requirements evaluated / version | 8 | ASSUMPTION |
| Avg evidence items / version | 20 | ASSUMPTION |
| Approvals / year | 10,000 | ASSUMPTION |
| Awards / year | 9,000 | ASSUMPTION |
| Avg award versions | 1.2 | ASSUMPTION |
| Avg row width (heap) | 200–600 bytes by table | ASSUMPTION |
| Index multiplier | 1.5–2.5× heap | ASSUMPTION |

## High-growth tables

### requirement_evaluations

| Horizon | Rows | Est. heap | +indexes (~2×) |
|---------|------|-----------|----------------|
| 1y | 12k×2×8 = 192k | ~80 MB | ~160 MB |
| 5y | ~1.0M | ~400 MB | ~800 MB |
| 10y | ~1.9M | ~800 MB | ~1.6 GB |
| 20y | ~3.8M | ~1.6 GB | ~3.2 GB |

### evidence_items

| Horizon | Rows | Est. heap | +indexes |
|---------|------|-----------|----------|
| 1y | 12k×2×20 = 480k | ~200 MB | ~400 MB |
| 5y | ~2.4M | ~1 GB | ~2 GB |
| 10y | ~4.8M | ~2 GB | ~4 GB |
| 20y | ~9.6M | ~4 GB | ~8 GB |

### completion_outcome_versions

| Horizon | Rows | Notes |
|---------|------|-------|
| 1y | ~24k | Modest |
| 10y | ~240k | No partition |
| 20y | ~480k | No partition |

## Verdict

At baseline assumptions, **partitioning not justified** for 10 years; **evidence_items** is the watchlist for 20-year storage (~single-digit GB with indexes — still modest vs attendance). Revisit if completion volume ≫ assumption.

Values requiring confirmation: completions/year, evidence density, multi-version rate.
