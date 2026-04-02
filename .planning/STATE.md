# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-02)

**Core value:** Banks must be able to discover, authenticate, and transfer money between each other reliably, even when the central bank or the destination bank is temporarily unavailable.

**Current focus:** Phase 1 complete

## Current Position

**Phase:** 01-registry-and-accounts
**Status:** ✅ Complete
**Verification:** Passed
**Date:** 2026-04-02

## Milestones

- Initialization complete
- Requirements defined
- Roadmap defined
- Phase 1 Plan 1: Central-bank contract ✅
- Phase 1 Plan 2: Branch-bank contract ✅

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

## Session

**Last session:** 2026-04-02T11:17:48Z - 2026-04-02T11:18:24Z
**Stopped at:** Completed 01-registry-and-accounts-02-PLAN.md

## Performance Metrics

| Phase | Plan | Tasks | Files | Duration | Date |
|-------|------|-------|-------|----------|------|
| 01-registry-and-accounts | 02 | 2 | 1 | 1min | 2026-04-02 |
