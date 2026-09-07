# Security Operations — SIS

## Daily

- Review security audit logs for `SEC_*` events
- Monitor failed login / 403 spikes

## CI

```bash
composer test                 # includes security:validate
php artisan security:validate
php artisan security:test
php artisan security:baseline
```

## Exception Management

1. Add entry to `.cursor/security/SECURITY-EXCEPTIONS.yaml`
2. Must include: id, rule, path, reason, owner, expires_at, risk, compensating_controls, approval
3. Expired exceptions → CI BLOCK

## Incident Response

See `SECURITY-INCIDENT-RESPONSE.md`
