# ADR-015: Rich Domain Entities + Application DTOs

## Status
Accepted — 2026-09-06

## Context
Anemic domain models (data bags + services with all logic) reduce testability and blur business rules.

## Decision
1. **Domain Entities** encapsulate state + behavior (`Student::activate()`, `Student::canEnroll()`).
2. **Value Objects** enforce invariants (`StudentCode`, `SchoolId`, `AcademicYearId`).
3. **Application DTOs** transfer data across layers — never pass Eloquent models to handlers.
4. **Result objects** (`EnrollStudentResult`) return structured outcomes instead of bare `bool`/`int`.
5. **Domain exceptions** use typed errors with codes (`StudentAlreadyEnrolledException`).

## Layer rules (enforced by `architecture:validate`)
- Domain → no Application, Illuminate, or Eloquent.
- Application → no Http Request, Eloquent models, or direct DB.
- Controllers → delegate to Command/Query handlers only.

## Consequences
- More files per feature, but clearer boundaries and OOP encapsulation.
