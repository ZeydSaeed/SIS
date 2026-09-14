# ENR-CLS-U01 ListClasses index — Schema NONE

**Unit:** ENR-CLS-U01  
**Date:** 2026-09-14  
**Authority:** phase-enrichment-wave5

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | HTTP: `GET /api/v1/enrollment/classes` |
| C | Optional filter: `academic_year_id` |
| D | Auth: `enrollment.view` via `viewAny` EnrollmentRecord |
| E | Explicit column select — no SELECT * |

## Out of scope

Create/update class; migrations.
