---
phase: 01-registry-and-accounts
status: passed
completed: 2026-04-02T14:21:00+00:00
requirements:
  - REG-01
  - REG-02
  - REG-03
  - ACCT-01
  - ACCT-02
  - ACCT-03
  - ACCT-04
  - HRTB-01
  - HRTB-02
---

# Verification: Phase 01 - Registry and Accounts

## Phase Goal

Define the central-bank directory and core account lifecycle APIs.

## Verification Results

### ✓ All Requirements Satisfied

#### Registry Requirements
- **REG-01** ✓ Central bank can register a bank with public key and address
  - Implemented in `POST /banks` endpoint in `openapi/central-bank.yaml`
  - Request includes name, address, publicKey
  - Response returns bankId and initial expiresAt

- **REG-02** ✓ Central bank can return the list of currently registered banks
  - Implemented in `GET /banks` endpoint in `openapi/central-bank.yaml`
  - Returns array of Bank objects (bankId, name, address, publicKey, lastHeartbeat, expiresAt)
  - Excludes stale banks (expiresAt <= current time)

- **REG-03** ✓ Central bank removes stale banks (no heartbeat within 30 minutes)
  - 30-minute timeout rule is explicit in contract documentation
  - Stale discovery happens on-demand during directory operations
  - Stale banks return 410 Gone status (permanent removal)

#### Account Requirements
- **ACCT-01** ✓ Bank can register a user
  - Implemented in `POST /users` endpoint in `openapi/branch-bank.yaml`
  - Request includes username and email
  - Returns userId (format: `user-{UUID}`)

- **ACCT-02** ✓ User can create a new account with a chosen currency
  - Implemented in `POST /users/{userId}/accounts` endpoint
  - Requires Bearer token authentication
  - Request includes currency code (3-letter ISO code)
  - Returns accountNumber (8 characters: 3 for bank prefix + 5 unique)

- **ACCT-03** ✓ Account numbers are 8 characters long with first 3 identifying the bank
  - Account number format is explicit: `{bankPrefix}{uniqueSuffix}`
  - Bank prefix component is exactly 3 characters
  - Full account number is exactly 8 characters total

- **ACCT-04** ✓ Unauthenticated lookup returns owner name or 404
  - Implemented in `GET /accounts/{accountNumber}` endpoint
  - No authentication required
  - Returns ownerName for existing accounts
  - Returns 404 for non-existent account numbers

#### Heartbeat Requirements
- **HRTB-01** ✓ Branch bank can send heartbeat to central bank
  - Implemented in `POST /banks/{bankId}/heartbeat` endpoint
  - Endpoint explicitly updates lastHeartbeat and resets expiresAt
  - Heartbeats are idempotent

- **HRTB-02** ✓ Central bank keeps registry fresh via heartbeats
  - Registry pruning is on-demand during directory operations
  - expiresAt is always lastHeartbeat + 30 minutes
  - Only active banks (expiresAt > current time) are returned in directory

### Success Criteria Assessment

1. ✓ API documents how banks register with central bank
   - Complete OpenAPI specification with registration flow
   - Examples provided for_registration

2. ✓ API documents heartbeat behavior and stale-bank pruning
   - Explicit 30-minute timeout rule documented
   - Heartbeat semantics clearly specified
   - Stale bank discovery and removal rules defined

3. ✓ API documents user registration, account creation, and lookup
   - All three endpoints fully specified
   - Account number format constraints explicit
   - Unauthenticated lookup behavior documented

4. ✓ Account number format is explicit and testable
   - 8-character format specified in OpenAPI schemas
   - Bank prefix (3 chars) and suffix (5 chars) separation
   - Validation rules are testable

### Contract Quality Checks

- ✓ OpenAPI 3.1 specification is valid (passed linting)
- ✓ All request/response schemas include examples
- ✓ All error conditions documented (400, 404, 410, 422)
- ✓ Implementation-agnostic (no framework-specific constraints)
- ✓ HTTP status codes follow REST patterns
- ✓ Security requirements documented (Bearer token for account creation)

### Integration Points

- Central-bank contract (`openapi/central-bank.yaml`) is ready for branch-bank integration
- Branch-bank contract (`openapi/branch-bank.yaml`) references central bank for registry
- Both contracts use consistent patterns for timestamps, error handling, and pagination

### Automated Verification

```bash
# Verify OpenAPI specs are valid
npx --yes @redocly/cli lint openapi/central-bank.yaml  # ✓ Passed
npx --yes @redocly/cli lint openapi/branch-bank.yaml   # ✓ Passed

# Verify required files exist
ls openapi/central-bank.yaml  # ✓ Exists
ls openapi/branch-bank.yaml   # ✓ Exists

# Verify requirements mapping
grep REG-01 openapi/central-bank.yaml  # ✓ Found in registration endpoint
grep ACCT-01 openapi/branch-bank.yaml  # ✓ Found in user registration endpoint
```

## Conclusion

**Status: PASSED** ✓

Phase 01 successfully achieved its goal of defining the central-bank directory and core account lifecycle APIs. All 9 requirements are satisfied, and both OpenAPI contracts are valid and implementation-ready.

The project is ready to proceed to Phase 2: Same-Bank and Cross-Bank Transfers.