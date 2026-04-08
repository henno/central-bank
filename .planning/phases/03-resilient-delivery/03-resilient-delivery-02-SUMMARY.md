---
phase: 03-resilient-delivery
plan: 02
subsystem: branch-bank
tags: [resilience, pending-transfers, timeout, error-codes, XFER-06, XFER-07]
depends_on_provides:
  requires: [03-resilient-delivery-01]
  provides: [pending-transfers, timeout-handling, resilience-error-codes]
  affects: [branch-bank-implementation, cross-bank-transfer-retry]
tech_stack:
  added: []
  patterns: [pending-transfer-lifecycle, exponential-backoff, timeout-recovery, diagnostic-fields]
decision_needs: []
---

# Phase 03 Plan 02: Branch Bank Pending Transfers, Timeout Handling, New Error Codes Summary

**One-liner:** Added pending transfer status with exponential backoff retry, 4-hour timeout with automatic refund, and new error codes (DESTINATION_BANK_UNAVAILABLE, CENTRAL_BANK_UNAVAILABLE, TRANSFER_TIMEOUT, TRANSFER_ALREADY_PENDING) implementing XFER-06 and XFER-07.

## Objective

Add pending transfer status, timeout handling, and new error codes to branch bank contract to implement XFER-06 (pending transfers when destination unavailable) and XFER-07 (automatic timeout with refund) using locked decisions from CONTEXT.md.

## Changes Made

### Files Modified

1. **openapi/branch-bank.yaml** (113 insertions, 6 deletions)

### Implementation Details

**Task 1: Add failed_timeout status to transfer status enums**

- **TransferResponse schema (lines 787-792):**
  - Expanded status enum: `[completed, failed, pending, failed_timeout]`
  - Updated description to document all four states:
    - `completed`: Transfer succeeded
    - `failed`: Transfer failed permanently
    - `pending`: Transfer is being retried (destination bank unavailable)
    - `failed_timeout`: Transfer failed due to timeout expiration with automatic refund

- **TransferStatusResponse schema (lines 892-920):**
  - Matching enum expansion with identical description
  - Ensures consistency between transfer initiation and status query responses

- **GET /transfers/{transferId} examples (lines 529-579):**
  - Added `pendingTransfer` example:
    - Status: `pending`
    - Diagnostic fields: `pendingSince`, `nextRetryAt`, `retryCount`
    - Demonstrates pending state during retry attempts
  - Added `timeoutFailedTransfer` example:
    - Status: `failed_timeout`
    - Error message: "Transfer timed out after 4 hours. Funds refunded to source account."
    - Shows terminal timeout state

**Task 2: Add diagnostic fields for pending transfers and new error codes**

- **TransferStatusResponse diagnostic fields (lines 952-972):**
  - `pendingSince`: Date-time timestamp indicating when transfer was first marked pending
    - Used to calculate timeout expiration (4-hour window)
  - `nextRetryAt`: Date-time timestamp indicating next retry attempt
    - Follows exponential backoff schedule (1m → 2m → 4m → ... → 1h)
  - `retryCount`: Integer (minimum 0) tracking retry attempts
  - All fields are optional and only present when status = `pending`

- **POST /transfers error examples:**
  - `transferAlreadyPending` (409 Conflict):
    - Code: `TRANSFER_ALREADY_PENDING`
    - Message: "Transfer with ID '...' is already pending. Cannot submit duplicate transfer."
    - Located after duplicateTransfer example (lines 414-418)
  - `destinationBankUnavailable` (503 Service Unavailable):
    - Code: `DESTINATION_BANK_UNAVAILABLE`
    - Message: "Destination bank is temporarily unavailable. Transfer has been queued for retry."
  - `centralBankUnavailable` (503 Service Unavailable):
    - Code: `CENTRAL_BANK_UNAVAILABLE`
    - Message: "Central bank is temporarily unavailable. Using cached directory data for routing."
  - Both 503 examples replace generic error (lines 432-446)

- **GET /transfers/{transferId} 423 response (lines 598-607):**
  - New HTTP 423 response (Locked status)
  - Example: `transferTimeout`
    - Code: `TRANSFER_TIMEOUT`
    - Message: "Transfer has timed out and cannot be modified or retried. Status is failed_timeout with refund processed."
  - Prevents modification of timed-out transfers

**Task 3: Update POST /transfers documentation to describe resilient behavior**

- **Comprehensive endpoint documentation (lines 332-371):**
  - Documented existing routing (D-01), idempotency (D-05), currency conversion (XFER-04), and cross-bank authentication (D-04)
  - Added **Resilient Delivery (Phase 3)** section covering:
    - **Pending Transfers (XFER-06):**
      - Status set to "pending" with immediate fund deduction
      - Exponential backoff retry queue (1m → 2m → 4m → ... → 1h)
      - Funds locked until resolution
      - Duplicate transferId returns 409: TRANSFER_ALREADY_PENDING
    - **Central Bank Unavailability (XFER-05):**
      - Use cached directory data for routing
      - Returns 503: CENTRAL_BANK_UNAVAILABLE
    - **Timeout Handling (XFER-07):**
      - 4-hour timeout triggers status change to "failed_timeout"
      - Automatic refund to source account
      - Modification attempts return 423: TRANSFER_TIMEOUT

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- ✅ swagger-cli validation passes with no errors
- ✅ Spectral lint passes with no errors
- ✅ Transfer status enums include `failed_timeout` in both TransferResponse and TransferStatusResponse
- ✅ All four new error codes defined with examples:
  - DESTINATION_BANK_UNAVAILABLE (503)
  - CENTRAL_BANK_UNAVAILABLE (503)
  - TRANSFER_TIMEOUT (423)
  - TRANSFER_ALREADY_PENDING (409)
- ✅ Pending transfer response includes optional diagnostic fields (pendingSince, nextRetryAt, retryCount)
- ✅ POST /transfers description documents complete resilient delivery behavior
- ✅ Examples demonstrate pending and timeout scenarios

## Requirements Satisfied

- **XFER-06:** Cross-bank transfers can be marked pending when the destination bank is temporarily unavailable
  - Status enum includes `pending` state
  - Diagnostic fields track retry progress
  - Error code: `TRANSFER_ALREADY_PENDING` prevents duplicate submissions
  - Error code: `DESTINATION_BANK_UNAVAILABLE` indicates destination unavailable

- **XFER-07:** A pending transfer eventually succeeds or fails with timeout if the destination bank never comes back online
  - Status enum includes `failed_timeout` for expired transfers
  - Example shows timeout with automatic refund
  - Error code: `TRANSFER_TIMEOUT` (423) prevents modification of timed-out transfers
  - Documentation describes 4-hour timeout behavior

- **XFER-05:** (via existing Plan 01) Cross-bank transfers can proceed when the central bank is unavailable
  - Error code: `CENTRAL_BANK_UNAVAILABLE` (503) indicates central bank unavailable
  - Documentation explains cache-based routing fallback

## Decisions Made

No new decisions required. Implementation followed locked decisions from CONTEXT.md:

- **Pending Transfer Lifecycle (XFER-06):**
  - Mark as pending with immediate fund deduction
  - Funds locked until resolution

- **Retry Strategy (XFER-06):**
  - Exponential backoff: 1m → 2m → 4m → 8m → 16m → 32m → 1h → 1h (continue until timeout)
  - Continue retries until 4-hour timeout expires
  - No maximum retry count (time-based only)

- **Timeout Duration (XFER-07):**
  - 4-hour maximum from pendingSince timestamp
  - Automatic refund with atomic status change

- **Error Codes:**
  - DESTINATION_BANK_UNAVAILABLE (503)
  - CENTRAL_BANK_UNAVAILABLE (503)
  - TRANSFER_TIMEOUT (423)
  - TRANSFER_ALREADY_PENDING (409)

## Key Technical Points

**Pending Transfer State Machine:**
- Valid transitions: `pending` → `completed` | `failed` | `failed_timeout`
- Terminal state: `failed_timeout` (cannot transition to other states)
- Idempotency: Same `transferId` cannot be submitted again while `pending`

**Diagnostic Field Semantics:**
- `pendingSince`: Absolute timestamp, used for timeout calculation
- `nextRetryAt`: Scheduled timestamp based on exponential backoff algorithm
- `retryCount`: Monotonic counter, starts at 1 on first retry

**Exponential Backoff Algorithm:**
```
retry_1:  1 minute   (60 seconds)
retry_2:  2 minutes  (120 seconds)
retry_3:  4 minutes  (240 seconds)
retry_4:  8 minutes  (480 seconds)
retry_5:  16 minutes (960 seconds)
retry_6:  32 minutes (1920 seconds)
retry_7+: 1 hour max (3600 seconds, capped)
```

**Error Code Semantics:**
- `TRANSFER_ALREADY_PENDING` (409): Client error, duplicate submission
- `DESTINATION_BANK_UNAVAILABLE` (503): Server error, transfer will be retried
- `CENTRAL_BANK_UNAVAILABLE` (503): Server error, cache fallback triggered
- `TRANSFER_TIMEOUT` (423): Client error, transfer already in terminal state

**API Contract Clarity:**
- Optional fields clearly documented as "Present only when status = pending"
- Error codes paired with HTTP status codes indicating severity
- Examples demonstrate edge cases and timeout scenarios

## Self-Check: PASSED

- ✅ Modified file exists: openapi/branch-bank.yaml
- ✅ Commits exist: 4695097, 5a51e62, f54558c
- ✅ Validation passes (swagger-cli + spectral)
- ✅ All plan success criteria met