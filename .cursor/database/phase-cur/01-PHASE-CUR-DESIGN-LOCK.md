# PHASE CUR — DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 00 LOCKED
AuthZ: GRANTED via «استمر»
```

## Schema

```text
CREATE curriculum.prerequisites (
  id BIGINT IDENTITY PK,
  subject_id FK → subjects RESTRICT,
  prerequisite_subject_id FK → subjects RESTRICT,
  status SMALLINT NOT NULL DEFAULT 1 CHECK IN (1,2),
  created_at TIMESTAMPTZ NOT NULL,
  UNIQUE(subject_id, prerequisite_subject_id),
  CHECK (subject_id <> prerequisite_subject_id)
)
```

## Application

```text
AddSubjectPrerequisite → insert or reactivate
ListSubjectPrerequisites → active only
DeactivateSubjectPrerequisite → status=2
```
