# PHASE TV — TV-U09
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: TV-U09 — Vocational staff JSON HTTP catalog writers
AuthZ: 21 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED / ACCEPTED
Date: 2026-09-12
```

## Delivered

```text
Permission: vocational.manage
Role: vocational_manager
Policy: VocationalPolicy + VocationalSchoolAccessService
Gate: SpecializationRecord / TrackRecord / SpecializationSubjectRecord
Thin Api\VocationalController → TV-U06 Application handlers
Routes:
  POST   /api/v1/vocational/specializations
  PATCH  /api/v1/vocational/specializations/{specialization}
  POST   /api/v1/vocational/specializations/{specialization}/deactivate
  POST   /api/v1/vocational/specializations/{specialization}/tracks
  PATCH  /api/v1/vocational/tracks/{track}
  POST   /api/v1/vocational/tracks/{track}/deactivate
  POST   /api/v1/vocational/specializations/{specialization}/subjects
  POST   /api/v1/vocational/specialization-subjects/{link}/deactivate
X-School-Id + required X-Idempotency-Key
Audit: SEC_VOCATIONAL_DATA_MODIFIED
Soft deactivate only
```

## Validation

```text
architecture:validate --fitness → PASS
security:validate → PASS
PhaseTvVocationalHttpApiPostgreSqlTest → 3 passed / 18 assertions
```

## Out of scope (still deferred)

```text
Vocational read/list HTTP · Results writer HTTP · Student portal · Phase 8
```

```text
TV-U09: CLOSED / ACCEPTED
Phase TV Timetable + Vocational Application writers now both have staff JSON API.
```
