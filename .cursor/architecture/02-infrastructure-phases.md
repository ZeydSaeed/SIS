# Infrastructure Phases

## Phase 1 — Initial Deployment

Start here. Do not over-provision.

```
┌─────────────┐
│  Laravel    │  Web + API + Queue Workers
│  App        │
└──────┬──────┘
       │
┌──────▼──────┐
│    Redis    │  Cache + Session + Queue
└──────┬──────┘
       │
┌──────▼──────┐
│ PostgreSQL  │  Single instance (OLTP)
└─────────────┘
```

**When:** Project start through early production.

## Phase 2 — Connection & Read Scaling

Add when concurrent users exceed single-node comfort or connection count grows.

```
┌─────────┐  ┌─────────┐
│ App-01  │  │ App-02  │
└────┬────┘  └────┬────┘
     └──────┬─────┘
            │
     ┌──────▼──────┐
     │  PgBouncer  │  1000 HTTP requests → 50–100 DB connections
     └──────┬──────┘
            │
     ┌──────▼──────┐         ┌──────────────┐
     │  Primary    │────────▶│ Read Replica │
     │  (writes)   │  repl   │  (reads)     │
     └─────────────┘         └──────────────┘
```

**Replica reads for:** Reports, dashboards, search, analytics.  
**Replica NOT for:** Reads immediately after writes (replication lag).

## Phase 3 — Reporting Separation

```
Primary (OLTP)
    │ replication
    ▼
Read Replica
    │ background jobs (refresh)
    ▼
Materialized Views / Reporting Tables
    │
    ▼
Dashboards & Analytics
```

## Full Target Architecture

```
                       ┌───────────────┐
                       │     Users     │
                       └───────┬───────┘
                               │
                       ┌───────▼───────┐
                       │ Load Balancer │
                       └───────┬───────┘
                               │
                ┌──────────────┼──────────────┐
             App-01         App-02         App-03
                └──────────────┼──────────────┘
                               │
                    ┌──────────▼─────────┐
                    │       Redis        │
                    └──────────┬─────────┘
                               │
                    ┌──────────▼─────────┐
                    │     PgBouncer      │
                    └──────────┬─────────┘
                               │
                  ┌────────────▼────────────┐
                  │ PostgreSQL Primary      │
                  │ (OLTP writes)           │
                  └──────┬──────────┬───────┘
                         │          │
                    Replication     │
                         │          │
                ┌────────▼──────┐   │
                │ Read Replica  │   │
                └───────┬───────┘   │
                        │            │
                 ┌──────▼──────┐     │
                 │ Reporting   │     │
                 └─────────────┘     │
                                     │
                              ┌──────▼──────┐
                              │ WAL/Backup  │
                              │ / DR        │
                              └─────────────┘
```

## Redis Caching Strategy

**PostgreSQL = Source of Truth. Redis = Cache.**

| Cache Target | TTL Strategy | Invalidation |
|-------------|-------------|--------------|
| Academic years | Long (hours) | On admin update |
| Schools, branches | Long | On admin update |
| Subjects, curriculum | Medium | On curriculum change |
| Permissions, roles | Medium | On role change |
| Dashboard stats | Short (minutes) | Scheduled refresh |
| User session | Session lifetime | Logout |

**Never cache:** Individual student grades, attendance records, financial transactions as primary read path.

## Connection Pooling

```
1000 HTTP requests
      ↓
PgBouncer (pool mode: transaction)
      ↓
50–100 PostgreSQL connections
```

Set `max_connections` based on actual load testing — not arbitrary high numbers.

Laravel uses its own connection pool per process; PgBouncer sits between app cluster and PostgreSQL.

## Capacity Planning Formula

```
800 students
× 200 school days
× 10 attendance events/day
× 20 years
= ~32 million attendance records
```

Plan for:

- Current data volume
- Annual growth rate
- 5 / 10 / 20 year projections
- Peak concurrent users
- Requests per second at peak
- Database IOPS and storage growth
- Backup storage growth

## Load Testing Targets

| Test | Concurrent Users | Goal |
|------|-----------------|------|
| Baseline | 100 | Establish P95 baseline |
| Normal peak | 500 | Verify acceptable response |
| High load | 1,000 | Find degradation point |
| Stress | 5,000 | Find breaking point |

**Scenarios:** Login, search student, open profile, mark attendance, enter grades, generate report, dashboard, bulk import, generate certificates, mass notifications.

**Record:** Avg response, P95, P99, req/sec, CPU, RAM, DB connections, slow queries, errors.

## Data Retention Tiers

| Tier | Years | Storage | Access Pattern |
|------|-------|---------|---------------|
| HOT | Current 2–3 | Primary DB, active partitions | Frequent |
| WARM | 3–10 years ago | Primary DB, older partitions | Occasional |
| ARCHIVE | 10–20 years ago | Archive DB or compressed partitions | Rare |
| COLD | 20+ years | Object storage / offline backup | Legal/compliance only |

Official records are retained per legal/administrative policy — never deleted casually.
