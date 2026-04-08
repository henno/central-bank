# Requirements: Distributed Banking System

**Defined:** 2026-04-02
**Core Value:** Banks must be able to discover, authenticate, and transfer money between each other reliably, even when the central bank or the destination bank is temporarily unavailable.

## v1 Requirements

### Registry

- [x] **REG-01**: Central bank can register a bank and store its public key and reachable address.
- [x] **REG-02**: Central bank can return the list of currently registered banks.
- [x] **REG-03**: Central bank removes a bank that has not sent a heartbeat within 30 minutes.

### Accounts

- [x] **ACCT-01**: Bank can register a user.
- [x] **ACCT-02**: User can create a new account with a chosen currency.
- [x] **ACCT-03**: Account numbers are 8 characters long and the first 3 characters identify the bank.
- [x] **ACCT-04**: Unauthenticated lookup of an account number returns the account owner's name or 404 if the account does not exist.

### Transfers

- [x] **XFER-01**: User can transfer funds between accounts in the same bank.
- [x] **XFER-02**: User can transfer funds to another bank using the destination account number.
- [x] **XFER-03**: Cross-bank transfers use the bank prefix to resolve the destination bank.
- [x] **XFER-04**: Cross-bank transfers convert currency at the current exchange rate when currencies differ.
- [x] **XFER-05**: Cross-bank transfers can proceed when the central bank is unavailable.
- [x] **XFER-06**: Cross-bank transfers can be marked pending when the destination bank is temporarily unavailable.
- [x] **XFER-07**: A pending transfer eventually succeeds or fails with timeout if the destination bank never comes back online.

### Heartbeats

- [x] **HRTB-01**: Branch bank can send a heartbeat to the central bank.
- [x] **HRTB-02**: Central bank can use heartbeats to keep bank registry state fresh.

## v2 Requirements

### Contract Detail

- **SPEC-01**: OpenAPI contract should include enough schema detail to implement both central and branch banks.
- **SPEC-02**: OpenAPI contract should define examples for bank registration, account lookup, and transfer flows.

## Out of Scope

| Feature | Reason |
|---------|--------|
| End-user UI | Not requested; the deliverable is API documentation only |
| Actual runtime implementation | The assignment starts with contract design, not code |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| REG-01 | Phase 1 | Complete |
| REG-02 | Phase 1 | Complete |
| REG-03 | Phase 1 | Complete |
| ACCT-01 | Phase 1 | Complete |
| ACCT-02 | Phase 1 | Complete |
| ACCT-03 | Phase 1 | Complete |
| ACCT-04 | Phase 1 | Complete |
| XFER-01 | Phase 2 | Complete |
| XFER-02 | Phase 2 | Complete |
| XFER-03 | Phase 2 | Complete |
| XFER-04 | Phase 2 | Complete |
| XFER-05 | Phase 3 | Complete |
| XFER-06 | Phase 3 | Complete |
| XFER-07 | Phase 3 | Complete |
| HRTB-01 | Phase 1 | Complete |
| HRTB-02 | Phase 1 | Complete |

**Coverage:**
- v1 requirements: 16 total
- Mapped to phases: 16
- Unmapped: 0 ✓

---
*Requirements defined: 2026-04-02*
*Last updated: 2026-04-02 after initialization*
