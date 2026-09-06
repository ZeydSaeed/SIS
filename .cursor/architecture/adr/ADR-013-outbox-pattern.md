# ADR-013: Transactional Outbox for Domain Events

## Status
Accepted — 2026-09-06

## Context
Domain events (audit, notifications, intelligence) must not fire before the database transaction commits. Direct dispatch after `UnitOfWork::transaction()` still risks partial failures between commit and event delivery.

## Decision
1. Handlers stage events via `OutboxRepository::stage()` **inside** the transaction.
2. `audit.outbox_messages` stores serialized domain events.
3. `ProcessOutboxJob` (scheduled every minute) rehydrates and dispatches bridge events to Laravel listeners.
4. Listeners remain in Infrastructure/Application — never in Domain.

## Consequences
- Reliable at-least-once delivery after commit.
- Slight latency (up to 1 minute) before side effects run.
- New event types require registration in `EloquentOutboxRepository::rehydrateEvent()`.
