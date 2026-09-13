# Phase ENR-U01 — FORCE RLS enrollments — BALLOT

**Unit:** ENR-U01  
**Parent:** Master audit — `enrollment.enrollments` RLS ON / FORCE OFF + fail-open GUC  
**Authority:** Continuation «استمر»  
**Date:** 2026-09-13

## Question

Harden `enrollment.enrollments` tenant isolation to match other school-scoped academic tables.

## Options (locked)

| # | Decision |
|---|----------|
| A | `FORCE ROW LEVEL SECURITY` on `enrollment.enrollments` |
| B | Replace fail-open policy with fail-closed `USING` + `WITH CHECK` on `school_id` = GUC |
| C | Reject hard DELETE (cancel via status remains) |
| D | classes / sections FORCE — HOLD (ENR-U02) |
| E | Schema: POLICY + TRIGGER only |

## Out of scope

Payroll; Ranking/PDF; enrollment HTTP API changes.
