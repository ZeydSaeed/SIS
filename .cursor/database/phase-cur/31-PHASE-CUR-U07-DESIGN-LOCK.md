# PHASE CUR — U07 DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 30 LOCKED
AuthZ: GRANTED via «استمر»
Schema: NONE
```

## Application

```text
CreateCurriculumCommand.+specializationId
CreateCurriculumGuard → SpecializationCatalogPort
CurriculumSnapshot/DTO include specializationId
List curricula returns specialization_id
```
