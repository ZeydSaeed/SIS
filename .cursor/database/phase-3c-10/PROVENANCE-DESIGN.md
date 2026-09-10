# PHASE 3C.10 — PROVENANCE & EVIDENCE DESIGN

## Separation (mandatory)

```text
Requirement Definition
  ≠ Requirement Evaluation
  ≠ Evidence
  ≠ Completion Outcome
  ≠ Graduation Approval
  ≠ Graduation Award
```

## Evidence storage

| Store | Contains |
|-------|----------|
| evidence_sets | Bundle 1:1 with completion_outcome_version |
| evidence_items | Typed references: source_type + source_id + source_version_ref |

**Do not** copy entire grade/result payloads into graduation tables.

### Fingerprints

| Field | Purpose | Not |
|-------|---------|-----|
| source_fingerprint | Optional integrity of included source set | Idempotency key |
| policy_fingerprint | Optional pin of policy content | Competing SSOT |

Algorithm: SHA-256 over canonical sorted refs — **design only**; confirm in 3C.11.

## Provenance answers

> Why was this student complete/graduated at time T?

Requires:

- policy version id + published snapshot  
- requirement definition versions  
- requirement_evaluations results  
- evidence_items references (resolve grades/results as of capture — prefer versioned upstream or capture fingerprint)  
- completion_outcome_version  
- graduation_approval  
- graduation_award_version  
- supersession/revocation chain  
- actors + timestamps + correlation_id  

## JSONB

Allowed on policy/requirement **payloads** only when content structure is institution-variable (HD-20/21 open content).

```text
why JSONB: extensible unit definitions without inventing columns for unknown attrs
schema expectations: versioned application schema; empty until policy authored
indexing: GIN only if query proven
validation: application + optional CHECK (jsonb_typeof)
query: prefer relational ids for joins; JSONB not join key
```

## Grades / transcripts

- Grades SSOT remains `exams.student_grades`  
- Transcripts consume completion/graduation — do not reverse SSOT
