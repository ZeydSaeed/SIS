# ENR-SEC-U01 ListClassSections index — Schema NONE

**Unit:** ENR-SEC-U01  
**Date:** 2026-09-14

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | HTTP: `GET /api/v1/enrollment/classes/{class}/sections` |
| C | Auth: `enrollment.view` |
| D | 404 when parent class not in school |

## Out of scope

Create section; migrations.
