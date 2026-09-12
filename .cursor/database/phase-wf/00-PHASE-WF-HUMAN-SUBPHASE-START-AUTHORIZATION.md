# PHASE WF — WORKFLOW APPROVAL FLOWS
# HUMAN SUBPHASE START AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر بالافضل»
Selected: Phase WF — approval_flows catalog Create/List
Rejected this slice: approval_requests (runtime approvals)
Rejected: COM-SEND / FIN money / Ranking-PDF
Status: GRANTED
```

## Why flows first

| Option | Verdict |
|--------|---------|
| approval_flows | Catalog · no runtime mutation of entities |
| approval_requests | HOLD — needs step engine + decision ballot |
| FIN payments | Ballot HOLD |
| COM send | HOLD |

## Absolute holds

```text
- ADD school_id for FORCE RLS
- No invent approve/reject runtime without ballot
- steps JSONB stored as definition only — not executed in this slice
- No hard delete
```
