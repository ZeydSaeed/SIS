# ENR-CLS-U02 GetClass show — Schema NONE

**Unit:** ENR-CLS-U02  
**Date:** 2026-09-14

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | HTTP: `GET /api/v1/enrollment/classes/{class}` |
| C | Auth: `enrollment.view` |
| D | 404 `enrollment.class_not_found` when missing/cross-school |

## Out of scope

Writes; migrations.
