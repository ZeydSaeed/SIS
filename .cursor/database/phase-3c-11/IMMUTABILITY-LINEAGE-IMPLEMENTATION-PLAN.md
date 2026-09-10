# PHASE 3C.11 — IMMUTABILITY & LINEAGE IMPLEMENTATION PLAN

**Triggers NOT created.**

## Model

```text
stable entity + immutable version rows + version_no + lineage + supersession + revocation
```

## What may change

| State | INSERT | UPDATE | DELETE |
|-------|--------|--------|--------|
| Draft policy/req versions | YES | YES payload | Prefer soft-retire |
| Published policy/req | YES new version only | NO payload | NO |
| Candidate completion version | YES | Limited until official | NO if linked |
| Official completion version | NO new payload | lineage flags only | NO |
| Decided approval | — | NO | NO |
| Issued award version | — | revoke/supersede flags only | NO |
| Supersession / revocation rows | YES | NO | NO |

## DB enforcement (future M18)

1. **BEFORE DELETE** trigger on official tables — mirror `exams.reject_student_grades_delete`  
2. **BEFORE UPDATE** trigger — reject protected column changes when lifecycle ∈ {official, issued, published, decided}  
3. Allowed UPDATE columns for official completion: `is_current_official`, `superseded_by_version_id`, `lifecycle_status`→superseded via controlled path  
4. Privilege: app role no DELETE on these tables  
5. Laravel `WriteGuard` domain services (like `StudentGradeWriteGuard`) — necessary, not sufficient  

## Version allocation

```text
BEGIN
  lock parent outcome/award FOR UPDATE
  next_version = max(version_no)+1
  INSERT version
  if publish official: clear prior is_current_*; set new
  INSERT supersession edge if replacing
  stage outbox
  store idempotency
COMMIT
```

Partial UNIQUE prevents two currents even under race.

## Supersession

- Hybrid: self-ref columns + `outcome_supersessions` edge  
- CHECK no self-super; reject cycles in domain service + optional trigger walk  
- HD-35: official V1 → impact → candidate → human → supersede  

## Revocation

```text
REVOCATION ≠ DELETION
```

1. INSERT `revocation_records`  
2. UPDATE award version flags (lifecycle revoked, is_current_issued=false)  
3. Outbox `graduation.award_revoked` (PROPOSED)  
4. StudentStatus rebuild/consumer may clear Graduated **eventually**  

Original issued row retained forever.

## Historical reconstruction

Join plan per 3C.10 QUERY-MATRIX — implement as Query handler later, not raw controller SQL.

## Tests

Official UPDATE/DELETE denied at DB even if app bypassed in test role; lineage negatives; revoke preserves row.
