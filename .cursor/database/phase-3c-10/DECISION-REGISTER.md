# PHASE 3C.10 — DECISION REGISTER

Unresolved physical/logical items. **No silent resolution.**

---

DECISION ID: D-3C10-001  
QUESTION: Exact PostgreSQL schema name `graduation` vs `lifecycle` vs other?  
CURRENT RECOMMENDATION: `graduation` (align 3C.9 reserved ownership; avoid stale blueprint table names)  
ALTERNATIVES: `lifecycle`, `academic_completion`  
TRADE-OFFS: Blueprint already mentions graduation.*; 3C.7 used Lifecycle concept  
RISK: LOW naming  
REQUIRES HUMAN DECISION: YES  

---

DECISION ID: D-3C10-002  
QUESTION: Exclusion constraint on overlapping policy effective ranges?  
CURRENT RECOMMENDATION: Defer; enforce in app + unique effective pointer until measured need  
ALTERNATIVES: tstzrange EXCLUDE USING gist  
TRADE-OFFS: Stronger temporal integrity vs migration complexity  
RISK: MEDIUM if concurrent policy publish  
REQUIRES HUMAN DECISION: YES  

---

DECISION ID: D-3C10-003  
QUESTION: evidence_set PK = completion_outcome_version_id (1:1 shared PK) vs separate identity?  
CURRENT RECOMMENDATION: Separate BIGINT identity + UNIQUE(version_id) for consistency with other tables  
ALTERNATIVES: Shared PK  
TRADE-OFFS: Extra key vs simpler joins  
RISK: LOW  
REQUIRES HUMAN DECISION: YES (prefer recommendation)  

---

DECISION ID: D-3C10-004  
QUESTION: When to partition evidence_items?  
CURRENT RECOMMENDATION: No partition at launch; revisit at >5M rows or measured pruning need  
ALTERNATIVES: RANGE by academic_year_id early  
TRADE-OFFS: Complexity vs future storage  
RISK: LOW at baseline  
REQUIRES HUMAN DECISION: YES if ops wants early partition  

---

DECISION ID: D-3C10-005  
QUESTION: Outbox event names for graduation transitions?  
CURRENT RECOMMENDATION: Mark PROPOSED until event governance approves  
PROPOSED: COMPLETION_EVALUATED, COMPLETION_OFFICIALLY_RECORDED, GRADUATION_APPROVED, GRADUATION_AWARD_ISSUED, GRADUATION_AWARD_REVOKED, VERSION_SUPERSEDED, VERSION_REVOKED  
ALTERNATIVES: Reuse existing taxonomy if present  
TRADE-OFFS: Consistency vs inventing events  
RISK: MEDIUM contract drift  
REQUIRES HUMAN DECISION: YES — EVENT GOVERNANCE  

---

DECISION ID: D-3C10-006  
QUESTION: Actor columns BIGINT → users.id FK or opaque?  
CURRENT RECOMMENDATION: Opaque BIGINT without inventing role FKs (HD-31) until identity model locked  
ALTERNATIVES: FK to users  
TRADE-OFFS: Referential clarity vs premature coupling  
RISK: LOW  
REQUIRES HUMAN DECISION: YES  

---

DECISION ID: D-3C10-007  
QUESTION: Policy/requirement JSONB required at table create or nullable empty?  
CURRENT RECOMMENDATION: JSONB NULL or '{}' until HD-20/21 content authored — no invented schema  
ALTERNATIVES: Defer JSONB columns  
TRADE-OFFS: Flexibility vs empty columns  
RISK: LOW  
REQUIRES HUMAN DECISION: YES  

---

DECISION ID: D-3C10-008  
QUESTION: StudentStatus projection table vs pure derived query/cache?  
CURRENT RECOMMENDATION: No new SSOT table; projection/read model only if latency requires — rebuild from awards  
ALTERNATIVES: Materialized projection table  
TRADE-OFFS: Speed vs competing SSOT risk  
RISK: MEDIUM if table created carelessly  
REQUIRES HUMAN DECISION: YES before any projection table  

---

## Still open from predecessors (not re-decided)

HD-23…30, 33–34, 37–38, 40–42; policy content; roles; award attributes; prior Results HDs as needed.

Physical design must not invent these.
