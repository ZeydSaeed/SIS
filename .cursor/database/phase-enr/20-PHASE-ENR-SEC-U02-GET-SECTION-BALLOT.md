# ENR-SEC-U02 GetSection show — Schema NONE

**Unit:** ENR-SEC-U02  
**Date:** 2026-09-14

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | HTTP: `GET /api/v1/enrollment/sections/{section}` |
| C | Auth: `enrollment.view` |
| D | School via parent `enrollment.classes.school_id` |

## Out of scope

Writes; migrations.
