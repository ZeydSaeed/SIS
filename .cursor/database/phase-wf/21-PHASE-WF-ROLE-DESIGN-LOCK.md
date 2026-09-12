# PHASE WF — DESIGN LOCK (role-per-step)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: WF-ROLE
Ballot: 20 LOCKED
```

## In

```text
- ApprovalDecisionRules::roleForStep + validateActorRole
- DecideApprovalRequestCommand.actorUserId
- ActorSchoolRolesPort (role codes for user@school)
- Permission workflow.decide + Gate decideWorkflow
- HTTP: manageWorkflow OR decideWorkflow; Application enforces exact step role
- No manageWorkflow bypass of step role
```

## Out

```text
- Module auto-hooks
- Schema / migrations
- Changing Cancel/Create auth (still manageWorkflow)
```

## Units

| Unit | Name | Status |
|------|------|--------|
| WF-U11 | Role-per-step decide | CLOSED |
| WF-U12 | Closure | CLOSED (see 24) |
