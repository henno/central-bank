---
phase: 03-resilient-delivery
plan: 01
subsystem: central-bank
tags: [caching, resilience, XFER-05]
depends_on_provides:
  requires: []
  provides: [lastSyncedAt-timestamp]
  affects: [branch-bank-cache-implementation]
tech_stack:
  added: []
  patterns: [cache-metadata, server-side-timestamps]
decision_needs: []
---

# Phase 03 Plan 01: Central Bank Directory with Cache Metadata Summary

**One-liner:** Added `lastSyncedAt` timestamp to BankDirectory schema enabling branch banks to cache directory data and operate during central bank outages.

## Objective

Add cache metadata to central bank directory for resilient operation during outages, enabling branch banks to track cache freshness using a server-provided timestamp per XFER-05 decision.

## Changes Made

### Files Modified

1. **openapi/central-bank.yaml** (21 insertions, 2 deletions)

### Implementation Details

**Task 1: Added lastSyncedAt field to BankDirectory schema and endpoint responses**

- **Schema Update (lines 378-397):**
  - Added `lastSyncedAt` property to `BankDirectory` schema
  - Type: `string` with `format: date-time`
  - Made it a required field (added to `required` array)
  - Comprehensive description documenting:
    - Cache freshness tracking purpose (XFER-05)
    - How branch banks cache directory locally
    - Usage during central bank outages
    - Caching strategy (download on startup, periodic refresh)

- **Endpoint Example Update (lines 117-133):**
  - Added `lastSyncedAt: "2026-04-08T12:00:00Z"` to GET /banks response example
  - Demonstrates correct format for cache timestamp tracking

- **Endpoint Description Update (lines 103-105):**
  - Enhanced GET /banks description to mention cache freshness tracking
  - Documented ability to operate with cached data during outages

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- ✅ swagger-cli validation passes with no errors
- ✅ BankDirectory schema includes lastSyncedAt as required field
- ✅ GET /banks response example includes lastSyncedAt
- ✅ Comprehensive documentation of caching strategy in descriptions

## Requirements Satisfied

- **XFER-05:** Cross-bank transfers can proceed when the central bank is unavailable
  - Central bank provides `lastSyncedAt` timestamp for cache freshness tracking
  - Branch banks can cache full directory locally using this timestamp

## Decisions Made

No new decisions required. Implementation followed locked decisions from CONTEXT.md:

- Central bank caching strategy (caching with timestamp tracking)
- No rejection based on cache staleness during outages

## Key Technical Points

**Cache Metadata Design:**
- Timestamp format: RFC 3339 (`date-time` format)
- Required field ensures clients always receive freshness information
- Server-side timestamp (not client-provided) for consistency
- Timestamp refresh pattern: cached on client, updated on successful central bank queries

**Resilience Pattern:**
- Primary service (central bank) provides freshness metadata
- Clients (branch banks) implement local caching
- Graceful degradation: operation continues with cached data during outages
- No TTL enforcement in API contract (implementation-specific caching policies)

## Self-Check: PASSED

- ✅ Modified file exists: openapi/central-bank.yaml
- ✅ Commit exists: 1ae431d
- ✅ Validation passes
- ✅ All plan success criteria met