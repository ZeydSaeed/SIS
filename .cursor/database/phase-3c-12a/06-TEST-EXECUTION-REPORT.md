# 06 — TEST EXECUTION REPORT

| Run | Command | Result |
|-----|---------|--------|
| A | `phpunit -c phpunit.database-pgsql.xml …/Phase3C12GraduationSchemaTest.php` | **PASS** 5/5 (79 asserts) |
| B | same | **PASS** 5/5 |
| C | same | **PASS** 5/5 |
| Unit | `GraduationIdempotencyGuardTest` | **PASS** 2/2 |
| Regression | `StudentGradesPartitionAndIntegrityPostgreSqlTest` | **PASS** 4/4 |

| Gate | Result |
|------|--------|
| RefreshDatabase (PG path) | PASS |
| Clean migration via dropSchemas+fresh | PASS |
| Repeatability | PASS |
| Graduation security in tests | PASS (asserted) |
| Architecture fitness (domain layers) | PASS lines; **SEC-DEP-001** composer audit noise observed (unrelated) |
| Security validate | Same SEC-DEP-001 composer audit (not introduced by 3C.12A) |

No business Graduation rows inserted.
