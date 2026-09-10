# ADR-003: BIGINT IDENTITY over UUID/Snowflake

**Status:** Accepted  
**Date:** 2026-09-05

## Decision

Internal primary keys: **`BIGINT GENERATED ALWAYS AS IDENTITY`**  
External/public identifiers: **`UUID`** (`public_id`) where needed only.

## Why

- 45K students × 10 years ≠ distributed global scale
- Sequential BIGINT: smaller indexes, faster joins, better B-tree locality
- Simpler migrations and FK relationships
- PostgreSQL IDENTITY is native and reliable

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| UUID v4 every PK | Index bloat, random I/O, 16 bytes vs 8 |
| Snowflake IDs | Unnecessary without multi-region writers |
| INT (32-bit) | Risk of overflow at extreme scale |

## Consequences

- Never expose internal BIGINT in public URLs — use `public_id` UUID or `student_code`
- Certificate verification uses `verification_code` not internal id
- **Review:** If horizontal sharding added, revisit Snowflake

## Related

- [database-blueprint.md](../database-blueprint.md)
- [01-principles-and-layers.md](../01-principles-and-layers.md)
- [ADR-020](./ADR-020-phase-1-database-architecture.md) — Phase 1 human reaffirmation (D1): keep BIGINT IDENTITY; do not migrate existing IDs to UUID
