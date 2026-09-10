# Phase 3C.14 — Implementation Decision Lock

Every decision is **LOCKED** or **NOT LOCKED**. No convenient defaults.

| Decision | Status | Implementation lane |
|----------|--------|---------------------|
| Completion ≠ Graduation (HD-19) | LOCKED | SAFE TO IMPLEMENT (structure) |
| Identity = school + enrollment (HD-39) | LOCKED | SAFE TO IMPLEMENT |
| Official immutability + supersession (HD-35, DL-019) | LOCKED | SAFE TO IMPLEMENT |
| Revocation mechanism human+lineage (HD-36 model) | LOCKED | SAFE TO IMPLEMENT (mechanism) |
| Revocation reasons/roles | NOT LOCKED | BLOCKED UNTIL POLICY DECISION |
| Evidence fail-closed (DL-020) | LOCKED | SAFE TO IMPLEMENT |
| No default GPA gate (HD-22) | LOCKED | SAFE TO IMPLEMENT (must not add gate) |
| Eligibility framework (HD-20) | LOCKED (framework) | SAFE TO IMPLEMENT (empty/versioned shells) |
| Eligibility **content** | NOT LOCKED | BLOCKED UNTIL POLICY DECISION (engine) |
| Required unit **lists** (HD-21 content) | NOT LOCKED | BLOCKED UNTIL POLICY DECISION |
| Human approval model (HD-31) | LOCKED | SAFE TO IMPLEMENT (no auto-approve) |
| Approval **role matrix** | NOT LOCKED | BLOCKED UNTIL POLICY DECISION |
| Award entity after approval (HD-32) | LOCKED | SAFE TO IMPLEMENT (structure) |
| Award attributes (honors/numbering) | NOT LOCKED | OPTIONAL FOLLOW-UP / BLOCKED if mandatory |
| StudentStatus projection-only (DL-022) | LOCKED | SAFE TO IMPLEMENT (never treat as SSOT) |
| Multi-enrollment → student Graduated flip | NOT LOCKED | BLOCKED UNTIL POLICY DECISION (auto-sync) |
| Clear Graduated on revoke-all | NOT LOCKED | BLOCKED UNTIL POLICY DECISION |
| Outbox storage reuse | LOCKED | SAFE TO IMPLEMENT |
| Outbox event name governance | NOT LOCKED (PROPOSED) | OPTIONAL FOLLOW-UP (register names) |
| Idempotency in-txn + fingerprint (3C.13) | LOCKED (architecture) | SAFE TO IMPLEMENT |
| Publication (HD-38) | NOT LOCKED | BLOCKED UNTIL POLICY DECISION |
| Separation of duties | NOT LOCKED | OPTIONAL FOLLOW-UP |
| Cross-school access | LOCKED DENY | SAFE TO IMPLEMENT (fail closed) |

## Lane summary

### SAFE TO IMPLEMENT (only after explicit human **implementation** authorization for 3C.15+)

- CQRS scaffolding, repositories, DTOs mirroring LIVE schema  
- Commands that **do not** invent policy content or named Graduation permissions  
- Idempotency/outbox/UoW wiring per 3C.13 (stricter than Enrollment)  
- Read queries against awards/completion SSOT  
- UI that queries SSOT and **does not** auto-flip `students.status`

### BLOCKED UNTIL POLICY DECISION

- `EvaluateCompletion` producing official eligibility from real rules  
- `DecideGraduationApproval` / award issue / revoke with bound `Permission` + Policy  
- Production StudentStatus auto-sync consumers  
- Publication commands  
- Any GPA/credit/attendance gate without published policy version content  

### OPTIONAL FOLLOW-UP

- Event name finalization  
- Award attribute catalogs  
- SoD rules  
- Non-superuser RLS proof role (3C.12B condition)  

## Global

```text
IMPLEMENTATION AUTHORIZATION: NOT GRANTED (this phase)
FULL OFFICIAL WRITE-PATH: BLOCKED (roles + eval content + status sync)
STRUCTURAL / INFRA LANE: CONDITIONAL — still needs separate human authorize to code
```
