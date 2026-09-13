# Phase ENR-U02 — FORCE RLS classes/sections — BALLOT

**Unit:** ENR-U02  
**Parent HOLD:** ENR-U01 — classes/sections FORCE  
**Authority:** Continuation «استمر»  
**Date:** 2026-09-13

## Question

Harden `enrollment.classes` and `enrollment.sections` with FORCE RLS + soft-only deletes.

## Options (locked)

| # | Decision |
|---|----------|
| A | `classes`: fail-closed FORCE RLS on `school_id` |
| B | `sections`: FORCE RLS via parent `classes.school_id` |
| C | Reject hard DELETE on both (soft `status`) |
| D | Schema: POLICY + TRIGGER |

## Out of scope

Payroll; Ranking/PDF; new class/section HTTP APIs.
