# Phase 3C.16 — Award Lifecycle

## IssueAward

Requires approved `graduation_approvals` row matching school + enrollment. Creates award + version v1 (`is_current_issued`).

## RevokeAward

Creates `revocation_records` row; marks version lifecycle revoked; clears current-issued flag. Does **not** DELETE.

Reason is opaque `reason_ref` — no institutional reason catalog invented (HD-36 OPEN).

## PublishAward

Blocked — HD-38 OPEN (`PublicationPolicyNotConfiguredException`).

## Attributes

No mandatory award attribute catalog invented.
