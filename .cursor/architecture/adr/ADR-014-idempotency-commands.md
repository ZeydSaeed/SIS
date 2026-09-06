# ADR-014: Command Idempotency Keys

## Status
Accepted — 2026-09-06

## Context
Academic commands (enrollment, payments, imports) can be retried due to network failures, queue redelivery, or double-clicks.

## Decision
1. Sensitive commands accept optional `idempotencyKey` (e.g. `EnrollStudentCommand`).
2. `audit.idempotency_keys` stores command name + response payload with TTL (default 24h).
3. Handlers return cached `Result` when key exists — no duplicate side effects.

## Consequences
- Clients must generate stable keys per logical operation.
- Idempotency is opt-in per command — not global middleware yet.
