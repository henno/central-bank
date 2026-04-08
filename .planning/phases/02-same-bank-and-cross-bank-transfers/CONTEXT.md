# Phase 2 Context: Same-Bank and Cross-Bank Transfers

**Phase:** 2
**Requirements:** XFER-01, XFER-02, XFER-03, XFER-04
**Status:** Planning pending

## Design Decisions

### Transfer Endpoint Structure

**Decision:** Single transfer endpoint on branch bank
- **Endpoint:** `POST /transfers`
- **Routing Logic:** The branch bank determines whether the transfer is same-bank or cross-bank by inspecting the destination account number prefix (first 3 characters)
- **Rationale:** Simpler API surface, internal routing is an implementation detail

### Inter-Bank Transfer API

**Decision:** `POST /transfers/receive` on destination branch bank
- **Purpose:** Endpoint called by source banks to deliver cross-bank transfers
- **Caller:** Source branch bank (authenticated via JWT)
- **Rationale:** Clear, consistent naming; distinct from user-initiated transfers

### Currency Conversion Strategy

**Decision: Central bank provides exchange rates
- **New Central Bank Endpoint Required:** `GET /exchange-rates` (or similar)
- **Rate Capture:** Source bank queries current rates at transfer request time
- **Application:** Rate applied when converting currency for cross-bank transfers
- **Storage:** Rate captured in transfer record for audit trail
- **Rationale:** Central authority ensures consistency across the banking system

### Cross-Bank Authentication

**Decision:** JWT signed with source bank's private key
- **Flow:**
  1. Source bank signs transfer request payload with its private key
  2. Destination bank retrieves source bank's public key from central bank registry (cached allowed)
  3. Destination bank verifies JWT signature
- **JWT Payload Contains:** transferId, sourceAccount, destinationAccount, amount, timestamp, exchangeRate (if applicable)
- **Rationale:** Leverages existing public key infrastructure, no shared secret management overhead

### Transfer Idempotency

**Decision:** Client-provided transfer ID
- **Requirement:** Clients must provide a unique `transferId` in each transfer request
- **Deduplication:** Server rejects requests with duplicate `transferId` values
- **Scope:** Applies to both same-bank and cross-bank transfers
- **Rationale:** Prevents double-spending from network retries or duplicate client requests

## Requirements Scope

### Covered in Phase 2

- **XFER-01:** User can transfer funds between accounts in the same bank
- **XFER-02:** User can transfer funds to another bank using the destination account number
- **XFER-03:** Cross-bank transfers use the bank prefix to resolve the destination bank
- **XFER-04:** Cross-bank transfers convert currency at the current exchange rate when currencies differ

### Deferred to Phase 3

- **XFER-05:** Cross-bank transfers can proceed when the central bank is unavailable
- **XFER-06:** Cross-bank transfers can be marked pending when the destination bank is temporarily unavailable
- **XFER-07:** A pending transfer eventually succeeds or fails with timeout if the destination bank never comes back online

## API Contracts to Extend

### Central Bank API (`openapi/central-bank.yaml`)

**New Endpoint Required:**
- `GET /exchange-rates` - Returns current exchange rates between supported currencies

**Schema to Add:**
- `ExchangeRateResponse` - Contains rates (e.g., base currency, rate map)

### Branch Bank API (`openapi/branch-bank.yaml`)

**New Endpoints Required:**
- `POST /transfers` - User-initiated transfer (same-bank or cross-bank)
- `POST /transfers/receive` - Inter-bank transfer reception (called by other banks)
- `GET /transfers/{transferId}` - Transfer status lookup (optional but useful)

**Schemas to Add:**
- `TransferRequest` - Same-bank/cross-bank transfer request
- `TransferResponse` - Transfer outcome with details
- `InterBankTransferRequest` - Cross-bank transfer from another bank (authenticated)
- `InterBankTransferResponse` - Acknowledgment of received transfer
- `ExchangeRate` - Currency pair with rate (for audit trail in records)

## Prerequisites

- Phase 1 complete (registry, heartbeats, user registration, account creation, account lookup)
- Central bank registry accessible for bank discovery and public key retrieval
- Bank prefix (3 characters) is known to each branch bank

## Dependencies

### External Dependencies
- External exchange rate API (central bank source) - implementation detail
- No additional infrastructure dependencies

### Internal Dependencies
- Central bank registry for bank discovery and public key lookup
- Account lookup endpoint for destination account verification
- Public key infrastructure already established in Phase 1

## Non-Goals

- Pending transfer handling (Phase 3)
- Central bank unavailability resilience (Phase 3)
- Destination bank timeout handling (Phase 3)
- Transaction history/reconciliation (out of scope)

## Open Questions (Resolved Above)

| Question | Resolution |
|----------|------------|
| Single or dual transfer endpoint? | Single endpoint with internal routing |
| Exchange rate source? | Central bank provides rates |
| Cross-bank authentication method? | JWT signed with source bank key |
| Idempotency approach? | Client-provided transfer ID |
| Inter-bank API endpoint? | `POST /transfers/receive` |

---

**Created:** 2026-04-08
**Last Updated:** 2026-04-08