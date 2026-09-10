# Phase 3C.15A — Multi-Enrollment Scenario Matrix

SSOT identity always `school_id + enrollment_id`.

| Scenario | Graduation SSOT | StudentStatus projection | Award state | Revocation effect | Authorization |
|----------|-----------------|--------------------------|-------------|-------------------|---------------|
| A: one enrollment graduated | One outcome/award lineage | **POLICY NOT LOCKED** | Issued if award exists | N/A | **POLICY NOT LOCKED** |
| B: A graduated, B active (same school) | Independent; B unaffected at SSOT | **POLICY NOT LOCKED** | A may have award | N/A | OPEN |
| C: both graduated | Two SSOT lineages allowed | **POLICY NOT LOCKED** | Two awards possible | N/A | OPEN |
| D: two schools, one graduated | Isolated by school | **POLICY NOT LOCKED** | School-scoped | Cross-school deny LOCKED | Cross-school DENIED |
| E: A revoked, B active | A revoked lineage; B SSOT intact | **POLICY NOT LOCKED** (SS-REVOKE-CLEAR) | A revoked | Revocation record required | HD-36 roles OPEN |
| F: two graduated; one award revoked | One revoked + one current | **POLICY NOT LOCKED** | Mixed | Independent lineages | OPEN |
| G: supersession/correction | New version + supersession edge (HD-35) | **POLICY NOT LOCKED** | Per new award rules | No in-place mutate | OPEN |

```text
EXPECTED RESULT = POLICY NOT LOCKED
```

for all StudentStatus cells until D-3C15A-018/019 closed.
