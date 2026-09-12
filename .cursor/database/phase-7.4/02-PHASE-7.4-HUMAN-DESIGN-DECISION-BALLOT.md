# MASTER PHASE 7 — PHASE 7.4
# HUMAN DESIGN DECISION BALLOT → RECORDED

---

```text
Document Type:
HUMAN DESIGN DECISION BALLOT → RECORDED

Subphase:
PHASE 7.4 — RESULTS / ACADEMIC AGGREGATION

Date:
2026-09-12

Start AuthZ:
00 APPROVED

Authority:
Absolute human grant — agent applies RECOMMENDED SET

Implementation:
NOT AUTHORIZED until Design Lock + unit Implementation AuthZ
```

---

## Recorded Decisions

### HD-7.4-001 — P7-D2 Ownership (CRITICAL)

```text
[x] A — Ownership = Phase 7.4 (Term/Annual Results) + Phase 7.5 (GPA/Ranking/Transcript)
        under Master Phase 7 conditional track
[ ] B — Separate future Master Phase (not 7.x)
[ ] C — Keep DEFERRED indefinitely
[ ] D — Assign to Phase 18 Reporting MVs — FORBIDDEN
```

**Rationale:** 3C design is academic projection BC; grades SSOT already closed; Phase 18 is analytics MVs — wrong home.

---

### HD-7.4-002 — Phase 7.4 scope boundary

```text
[x] A — 7.4 = Term Results + Annual Results only
[ ] B — Include GPA in 7.4
[ ] C — Include Ranking + Transcript in 7.4
```

**Rationale:** Isolates P0 aggregation from unresolved GPA/letter/ranking/transcript HDs (HD-01/02/08–12).

---

### HD-7.4-003 — Promote Phase 3C Design Locks DL-001…DL-016

```text
[x] A — PROMOTE to LOCKED for Phase 7.4/7.5 governance
[ ] B — Re-open full 3C redesign
[ ] C — Cherry-pick only DL-001
```

**Key locks retained:** DL-001 grade SSOT; DL-002 versioned hybrid; DL-007 no silent official mutate; DL-016 deterministic rebuild.

---

### HD-7.4-004 — Weight normalization (maps HD-03)

```text
[x] A — Official term calc: contributing exam_type.weight_percentage for the term subject set
        MUST sum to 100 (±0.01); else fail-closed
[ ] B — Auto-renormalize weights to 100
[ ] C — DEFER official calc entirely
```

---

### HD-7.4-005 — Grade-status eligibility (maps HD-04)

```text
[x] A — Official: only is_current=true AND status ∈ {Finalized}
        Operational: is_current=true AND status ∈ {Entered, Finalized}
        Voided / superseded rows NEVER contribute
[ ] B — Include Entered in official
[ ] C — DEFER
```

**Note:** Aligns with VOID+INSERT correction model; current row only.

---

### HD-7.4-006 — Absent treatment (maps HD-05)

```text
[x] A — Absent grades: EXCLUDE from weighted average (skip weight in both num and den);
        mark term subject incomplete if any required session is Absent without replacement
[ ] B — Treat Absent as zero
[ ] C — DEFER
```

---

### HD-7.4-007 — Retake / currency (maps HD-06)

```text
[x] A — Use CURRENT grade row only (is_current=true); history via VOID+INSERT already
[ ] B — Max-of-all historical scores
[ ] C — DEFER
```

---

### HD-7.4-008 — Dataset eligibility for official finalize (maps HD-18)

```text
[x] A — Official FinalizeTermResult requires every enrolled subject in scope to have
        a current Finalized eligible grade (per HD-7.4-005) OR an explicit Incomplete outcome path
[ ] B — Allow partial official finalize
[ ] C — DEFER official finalize (operational Calculate only in 7.4)
```

**Interaction with HD-7.4-002:** Annual finalize inherits same completeness rule at year grain.

---

### HD-7.4-009 — Term-closed automation (maps HD-17)

```text
[ ] A — Auto-finalize when term status = Closed
[x] B — NO automation in 7.4 — explicit Finalize* commands only
[ ] C — DEFER all finalize
```

---

### HD-7.4-010 — Letter / GPA on term rows (maps HD-01/02)

```text
[ ] A — Store letter + GPA on term versions in 7.4
[x] B — EXCLUDE letter + GPA columns from 7.4 v1 (reserved for 7.5)
[ ] C — Nullable placeholders without formula
```

---

### HD-7.4-011 — Physical model choice (3C.2)

```text
[x] A — Version rows carry business identity (no empty header required for v1)
[ ] B — Thin header + versions
[ ] C — Compute-only (no persistence) — REJECTED for official freeze
```

---

### HD-7.4-012 — HTTP / API exposure in 7.4 first units

```text
[ ] A — Expose Results HTTP writers immediately
[x] B — Application commands + tests only for U01–U04; HTTP NOT AUTHORIZED in 7.4 unless later unit AuthZ
[ ] C — Read-only HTTP only
```

---

### HD-7.4-013 — First implementation unit preference

```text
[x] A — U01 = schema + FORCE RLS for term result versions (no calculator)
         U02 = Calculate (operational)
         U03 = Finalize (official)
         U04 = Rebuild
         then Annual units
[ ] B — Schema + calculator in one unit
[ ] C — Policy catalog tables only (no results facts yet)
```

---

### HD-7.4-014 — Rounding (term totals)

```text
[x] A — Round weighted total to NUMERIC(8,2) half-up at persist
[ ] B — Store full precision only
[ ] C — DEFER
```

---

### HD-7.4-015 — Pass/fail on term subject

```text
[x] A — Derived at calculate time: pass if total >= exam session/subject policy pass threshold
        when available; else NULL pass_fail until policy present
[ ] B — Always NULL in 7.4
[ ] C — Hard-code 50%
```

---

## Consolidated RECOMMENDED SET (APPLIED)

| Decision | Choice |
|----------|--------|
| HD-7.4-001 | **A** — P7-D2 owned by 7.4/7.5 |
| HD-7.4-002 | **A** — Term+Annual only |
| HD-7.4-003 | **A** — DL-001…016 LOCKED |
| HD-7.4-004 | **A** — weights sum 100 fail-closed |
| HD-7.4-005 | **A** — official Finalized+current |
| HD-7.4-006 | **A** — Absent exclude + incomplete mark |
| HD-7.4-007 | **A** — current row only |
| HD-7.4-008 | **A** — complete dataset for official |
| HD-7.4-009 | **B** — no auto finalize |
| HD-7.4-010 | **B** — no letter/GPA in 7.4 |
| HD-7.4-011 | **A** — version-row identity |
| HD-7.4-012 | **B** — no HTTP yet |
| HD-7.4-013 | **A** — schema-first units |
| HD-7.4-014 | **A** — NUMERIC(8,2) half-up |
| HD-7.4-015 | **A** — derived pass when threshold known |

```text
BALLOT STATUS: RECORDED / APPLIED
P7-D2: RESOLVED → Phase 7.4 / 7.5 ownership
Continue → Design Lock (03)
```
