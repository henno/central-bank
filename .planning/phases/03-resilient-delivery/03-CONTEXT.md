# Phase 3: Resilient Delivery - Discussion Context

**Phase:** 03 - Resilient Delivery
**Date:** 2026-04-08
**Requirements:** XFER-05, XFER-06, XFER-07

## Decisions

### Central Bank Unavailability (XFER-05)
- **Caching Strategy:** Branch banks must cache the full bank directory locally
  - Download complete directory from central bank on startup and periodically refresh
  - When central bank is unavailable, serve transfers using cached directory data
  - Include `lastSyncedAt` timestamp to indicate cache freshness
  - Cached data is accepted for routing cross-bank transfers regardless of age
  - No rejection based on cache staleness during central bank outage

### Pending Transfer Storage (XFER-06)
- **Pending Lifecycle:** When destination bank is unavailable during cross-bank transfer:
  - Source bank marks transfer status as `pending`
  - Source bank IMMEDIATELY deducts funds from source account (funds locked)
  - Transfer is stored in persistent pending queue
  - Asynchronous retry mechanism attempts delivery to destination bank
  - Funds remain locked until transfer succeeds, times out, or fails
- **No Double-Booking:** Same `transferId` cannot be submitted again while pending
  - Reject duplicate requests with error code `TRANSFER_ALREADY_PENDING` (409)

### Retry Strategy (XFER-06)
- **Exponential Backoff:** Retry interval schedule:
  - First retry: 1 minute
  - Subsequent retries: Double previous interval up to maximum of 1 hour
  - Pattern: 1m → 2m → 4m → 8m → 16m → 32m → 1h → 1h → 1h (continue until timeout)
- **Retry Continuation:** Continue retries until 4-hour timeout expires
  - No maximum retry count - time-based timeout only
  - Retries are asynchronous, not blocking on user request

### Timeout Duration (XFER-07)
- **Timeout Period:** 4 hours maximum
  - Pending transfers expire and fail after 4 hours
  - Timer starts when transfer is first marked pending
  - All retries stop after timeout expiration

### Timeout Handling (XFER-07)
- **Automatic Refund:** When timeout expires:
  - Transfer status changes to `failed_timeout`
  - Funds are automatically credited back to source account
  - `errorMessage` set to indicate timeout failure
  - No manual intervention or admin approval required
  - Refund is atomic with status change

### Error Codes
Branch bank API must return these new error codes for resilient delivery scenarios:

- `DESTINATION_BANK_UNAVAILABLE` (503 Service Unavailable)
  - Returned when destination bank cannot be reached during cross-bank transfer
  - Indicates transfer will be retried or is already pending

- `CENTRAL_BANK_UNAVAILABLE` (503 Service Unavailable)
  - Returned when central bank directory service is unavailable
  - Implementation may use cached directory data based on caching strategy

- `TRANSFER_TIMEOUT` (423 Locked)
  - Returned when attempting to retry or modify a transfer that has timed out
  - Indicates transfer is in terminal failed state with refund processed

- `TRANSFER_ALREADY_PENDING` (409 Conflict)
  - Returned when transferId already exists with status `pending`
  - Prevents duplicate pending transfers for same idempotency key

### State Machine
Transfer status enum expanded to include: `completed`, `failed`, `pending`, `failed_timeout`

Valid transitions:
- `pending` → `completed` (delivery successful)
- `pending` → `failed` (permanent error before timeout)
- `pending` → `failed_timeout` (timeout expired, refund processed)

Invalid/redundant transitions:
- `failed_timeout` → any other state (terminal state)
- Any state → `pending` (once resolved, cannot become pending again)

## the agent's Discretion

The following areas are up to the planner to specify:

### Cache Implementation Details
- **Cache TTL Policy:** Not specified how long cached directory data is considered "fresh"
  - Could be: no TTL (accept any cached age during outage), 1 hour TTL, 24 hour TTL
  - Should specify when cached data is rejected (if ever)
- **Cache Refresh Mechanism:** Not specified how/when banks refresh cached directory
  - Could be: on heartbeat schedule, on timer, on-demand before timeout
  - Could include `GET /banks/local` endpoint vs transparent server from cache

### Retry Implementation Details
- **Retry Failure Detection:** Not specified what constitutes a "failed retry attempt"
  - Could be: network timeout, 503 response, 5xx responses, any non-200 response
  - Should specify which HTTP status codes trigger immediate retry vs permanent failure
- **Retry Concurrency:** Not specified if multiple pending transfers retry in parallel
  - Could be: single-threaded retry queue, parallel per-destination bank, fully parallel
  - Should specify rate limiting to avoid overwhelming recovering destination bank

### Timeout Precision
- **Timeout Check Granularity:** Not specified how frequently timeout expiry is checked
  - Could be: on every retry attempt, cron job every minute, background scheduler
  - Should ensure refunds happen promptly after 4-hour window expires

### Status Response Details
- **Pending Response Fields:** Not specified what additional fields appear in TransferStatusResponse when status = `pending`
  - Could include: `pendingSince`, `nextRetryAt`, `retryCount`, `lastError`
  - Should specify which diagnostic fields (if any) are included

### Exchange Rate Handling for Pending Transfers
- **Rate Validity:** Not specified what happens to exchange rates used for pending transfers
  - Could be: rate captured at transfer init and locked in, rate re-fetched on each retry
  - Should specify if stale rates cause failure or rate updates are applied

## Deferred Ideas (OUT OF SCOPE)

The following features were explicitly deferred or deemed out of scope for Phase 3:

### Query Endpoints for Pending Transfers
- **No Bulk Query in Contract:** No requirement for `GET /transfers/pending` or similar bulk query endpoints
  - Individual transfer lookup via existing `GET /transfers/{transferId}` is sufficient
  - Bulk query, filtering, pagination for pending transfers is implementation-specific
  - Branch banks may implement admin tools internally, but not part of cross-bank interoperability contract

### Admin and Management Interfaces
- **No Manual Override:** No admin endpoints for manually retrying or canceling pending transfers
  - Timeout and automatic refund are the only mechanisms for resolving stuck transfers
  - Manual intervention would require out-of-band processes (not API-specified)

### Cross-Bank Visibility
- **No Cross-Bank Pending State Tracking:** Source bank tracks pending state, but destination bank has no visibility into pending transfers
  - Destination bank only sees transfers when they are attempted (via `/transfers/receive`)
  - No cross-bank coordination queue or shared pending state

### Advanced Retry Features
- **No Callback/Webhook on Completion:** No requirement for notifying source bank when destination bank recovers
  - Source bank must discover availability via retry attempts
- **No Priority Queues:** All pending transfers treated equally - no priority system for urgent vs non-urgent
- **No Partial Deliveries:** Either transfer succeeds fully or remains pending - no partial amount settlements

### Monitoring and Observability
- **No Metric Endpoints:** No API requirement for exposing retry statistics, pending queue depth, timeout counts
  - Implementations should monitor these internally, but not contract requirement

## Summary

Phase 3 introduces resilient delivery for cross-bank transfers through:

1. **Local Directory Caching:** Banks can survive central bank outages using cached directory data
2. **Pending Transfer State:** Funds locked on source side while destination bank is unavailable
3. **Exponential Backoff Retry:** Aggressive initial retries (1m → 32m) then sustained (1h intervals) for up to 4 hours
4. **Automatic Timeout Recovery:** 4-hour timeout triggers atomic refund to source account
5. **Explicit Error Codes:** New error codes for unavailable services, timeouts, and duplicate pending transfers

The core insight is that transfers can proceed with eventual consistency: the system accepts transfers during outages, retries automatically, and recovers gracefully when services recover or after timeout. This trades immediate confirmations for resilience and availability.