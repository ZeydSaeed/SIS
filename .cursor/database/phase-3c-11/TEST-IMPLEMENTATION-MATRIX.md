# PHASE 3C.11 — TEST IMPLEMENTATION MATRIX

**Tests NOT executed as destructive DDL tests in this phase.**

## Database

| Area | Cases |
|------|-------|
| PK / IDENTITY | insert returns id |
| FK RESTRICT | delete parent blocked |
| Composite FK | wrong school+enrollment rejected |
| CHECK | exclusion reason; version_no; self-super |
| UNIQUE | domain identities |
| Partial UNIQUE | second current official fails |
| Nullability | required columns |
| Concurrency | parallel version/publish |

## RLS

same-school OK · cross-school deny · insert cross deny · update/delete deny · **fail-closed without GUC**

## Immutability

official UPDATE denied · DELETE denied · historical preserved · revoke preserved · supersession preserved

## Lineage

valid chain · invalid cycle · cross-school lineage deny · supersession conflict · revocation conflict

## Idempotency / Outbox

duplicate key replay · retry · partial failure · atomic outbox with write · consumer replay · duplicate event handling

## StudentStatus

projection update · stale · replay · rebuild · event failure · never SSOT

## Application

authorization · CQRS layers · UnitOfWork · validation · audit · `architecture:validate --fitness`

---

## Negative tests (mandatory)

| # | Case | Expected |
|---|------|----------|
| 1 | cross-school enrollment | FK/RLS fail |
| 2 | cross-school student denorm | trigger fail |
| 3 | cross-school outcome ref | fail |
| 4 | cross-school award | fail |
| 5 | duplicate current version | partial UNIQUE fail |
| 6 | duplicate version_no | UNIQUE fail |
| 7 | invalid supersession | CHECK/domain fail |
| 8 | invalid revocation | domain/FK fail |
| 9 | official UPDATE | trigger fail |
| 10 | official DELETE | trigger fail |
| 11 | invalid evidence ref type | validation fail |
| 12 | invalid enrollment | FK fail |
| 13 | invalid lineage cycle | reject |
| 14 | duplicate idempotency | replay OK |
| 15 | duplicate event consumer | idempotent |
| 16 | missing school GUC | RLS deny |
| 17 | fail-open policy attempt | gate fail / forbidden |
| 18 | unauthorized actor | Policy deny |

Happy paths alone = incomplete.
