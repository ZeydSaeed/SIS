# PHASE HR — SOFT DEACTIVATE DESIGN LOCK (HR-U03)

---

```text
Status: LOCKED
Ballot: 07 LOCKED
AuthZ: GRANTED via «استمر»
Schema: NO MIGRATION
```

## In

```text
- DeactivateJobPosition / DeactivateEmployee commands
- Domain events JobPositionDeactivated / EmployeeDeactivated
- Repo: findEmployeeInSchool, setJobPositionStatus, deactivateEmployee
- Routes under /api/v1/hr/...
```

## Out

```text
- Payroll, reactivate HTTP (future), multi-school transfer
- CASCADE or hard DELETE
```

## Invariants

| ID | Rule |
|----|------|
| INV-HR-U03-01 | Soft status only |
| INV-HR-U03-02 | School-scoped visibility via existing RLS |
| INV-HR-U03-03 | Payroll remains NOT AUTHORIZED |
