# PHASE CUR — U02 DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 05 LOCKED
AuthZ: GRANTED via «استمر»
```

## Application

```text
CreateSubject → insert active subject
ListSubjects → active rows
DeactivateSubject → status=2
```

## Schema

```text
ADD reject hard DELETE trigger on curriculum.subjects
OPTIONAL CHECK subject_type IN (1,2,3) if missing
```
