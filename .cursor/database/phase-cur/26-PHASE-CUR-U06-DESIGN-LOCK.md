# PHASE CUR — U06 DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 25 LOCKED
AuthZ: GRANTED via «استمر»
Schema: NONE
```

## Application

```text
AssignEnrollmentSubjectGuard:
  for each active prereq subject:
    satisfied = history(U05) OR gradePass(U06)
    else enrollment.prerequisite_not_met

PrerequisitePassEvidencePort → Infrastructure adapter on exams.student_grades
```
