# Feature: Audit

## Bounded Context

- **Context:** Audit (domain change trail — not Security audit)
- **Primary aggregate:** AuditLog
- **Scoped by:** school_id

## Planned Use Cases

| Type | Name | Status |
|------|------|--------|
| Command | RegisterAuditLog | ✅ AUDIT-U01 |
| Query | ListAuditLogs | ✅ AUDIT-U01 |
