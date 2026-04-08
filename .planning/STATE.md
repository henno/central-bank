# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-02)

**Core value:** Banks must be able to discover, authenticate, and transfer money between each other reliably, even when the central bank or the destination bank is temporarily unavailable.

**Current focus:** Project Complete

## Current Position

**Phase:** All phases complete
**Status:** ✅ All requirements satisfied
**Verification:** Passed
**Date:** 2026-04-08

## Milestones

- Initialization complete
- Requirements defined
- Roadmap defined
- Phase 1 Plan 1: Central-bank contract ✅
- Phase 1 Plan 2: Branch-bank contract ✅
- Phase 2 Plan 1: Transfer endpoints ✅
- Phase 3 Plan 1: Central bank directory with cache metadata ✅
- Phase 3 Plan 2: Branch bank pending transfers, timeout handling, new error codes ✅

## Notes

This project is contract-first and currently has no application implementation.

## Decisions

**From Phase 01-registry-and-accounts-02:**
- Account number format: 8 characters total (3 for bank prefix, 5 unique within bank)
- Unauthenticated lookup returns only owner name for privacy balance
- User registration is open (no authentication required for signup)
- Account creation requires Bearer token authentication
- Balances stored as decimal strings to avoid floating-point precision issues
- Timestamps always server-side; client timestamps only for logging/debugging
- User IDs follow pattern: `user-{UUID v4}` for uniqueness within branch bank

**From Phase 02-same-bank-and-cross-bank-transfers-01:**
- Single transfer endpoint POST /transfers with internal routing (D-01)
- Inter-bank transfer uses POST /transfers/receive (D-02)
- Central bank provides exchange rates via GET /exchange-rates (D-03)
- Cross-bank authentication via JWT signed with source bank's private key (D-04)
- Idempotency via client-provided transferId (D-05)
- EUR as base currency (DEC-01)
- No rate TTL enforcement in Phase 2 (DEC-02)
- JWT algorithm: ES256 (ECDSA with P-256) (DEC-03)
- GET /transfers/{transferId} included in Phase 2 (DEC-04)
- Inter-bank request body contains only JWT field (DEC-05)

**From Phase 03-resilient-delivery:**
- Central Bank Caching: Branch banks cache full bank directory locally with lastSyncedAt timestamp
- Pending Transfer Lifecycle: Mark as pending and immediately deduct funds from source account
- Retry Strategy: Exponential backoff (1m → 2m → 4m → ... → 1h) up to 4-hour timeout
- Timeout Handling: 4-hour timeout with automatic refund to source account, status = failed_timeout
- New Error Codes: DESTINATION_BANK_UNAVAILABLE (503), CENTRAL_BANK_UNAVAILABLE (503), TRANSFER_TIMEOUT (423), TRANSFER_ALREADY_PENDING (409)

## Session

**Last session:** 2026-04-08T12:18:33Z - 2026-04-08T12:28:00Z
**Stopped at:** Completed Phase 3: Resilient Delivery (both plans)

## Performance Metrics

| Phase | Plans | Tasks | Files | Duration | Date |
|-------|-------|-------|-------|----------|------|
| 01-registry-and-accounts | 2/2 | 2 | 1 | 1min | 2026-04-02 |
| 02-same-bank-and-cross-bank-transfers | 1/1 | 4 | 3 | 30min | 2026-04-08 |
| 03-resilient-delivery | 2/2 | 4 | 2 | 10min | 2026-04-08 |