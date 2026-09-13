# PHASE ENR — U01 SCHEMA CHANGE IMPACT

---

```text
Change: FORCE RLS + fail-closed policy + reject DELETE on enrollment.enrollments
Type: POLICY + TRIGGER
Risk: MEDIUM (replaces fail-open GUC allowance)
Blast: Non-superuser / app roles without app.current_school_id see zero rows
Rollback: down() restores prior ENABLE-only posture (not recommended)
```

## Checklist

- [x] Additive security hardening
- [x] No column drop
- [x] Soft cancel remains write path
- [x] Blueprint updated
