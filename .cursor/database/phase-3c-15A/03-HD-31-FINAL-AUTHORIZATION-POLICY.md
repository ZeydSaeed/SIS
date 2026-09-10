# Phase 3C.15A — HD-31 Final Authorization Policy

## LOCKED

| ID | Decision |
|----|----------|
| HD-31 model | Human approval required; no silent auto-approve (3C.8B) |
| School isolation | Fail-closed; cross-school deny (HD-39 + RLS) |

## OPEN — Human Decision = NOT PROVIDED

| ID | Question |
|----|----------|
| HD-31-A | Who may approve Completion? |
| HD-31-B | Who may approve Graduation? |
| HD-31-C | Who may issue Award? |
| HD-31-D | Who may revoke approval? |
| HD-31-E | Who may revoke Award? |
| HD-31-F | Role-based / permission-based / both? |
| HD-31-G | Exact permission identifiers |
| HD-31-H | Multiple approval required? |
| HD-31-I | Evaluator ≠ approver mandatory? |
| HD-31-J | Self-approval prohibited? |
| HD-31-K/L | Delegation + constraints? |
| HD-31-M | Approval levels? |

```text
Invented permission names: FORBIDDEN
graduation.approve / revoke / publish: NOT AUTHORITATIVE (do not create)
```

## Repo evidence

`Permission.php`: Enrollment + Grades only.

## Classification

```text
HD-31: PARTIAL
```
