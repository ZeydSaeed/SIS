# PHASE OPS SCHOOL CONTEXT — Closure

**Status:** CLOSED  
**Tip:** `e513db8`  
**Branch:** `feature/phase-ops-school-context`

## Fix

Empty module pages were caused by missing web school context (403 / no data), not missing React routes.

- Bootstrap `current_school_id` into session for allowed schools
- Implicit single-school enabled by default
- Inertia shares `schoolContext` + `academicYears`
- Ops context bar (school switch) + year select filter
- Arabic school-context error page

## Validation

- `PhaseOpsSchoolContextGuestTest` passed
- `npm run build` passed
- Hub HTTP 200

## Operator note

User must be logged in with at least one `security.user_roles.school_id`. Without role→school binding, pages still cannot load school data.
