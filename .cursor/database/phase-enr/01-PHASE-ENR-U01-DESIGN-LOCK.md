# PHASE ENR — U01 DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 00 LOCKED
AuthZ: GRANTED via «استمر»
```

## Schema

```text
FORCE RLS enrollment.enrollments
Policy enrollment_school_isolation fail-closed USING + WITH CHECK
Trigger reject hard DELETE
```
