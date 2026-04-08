# Phase 2 Plan 1: Same-Bank and Cross-Bank Transfer Endpoints Summary

**OpenAPI contracts for JWT-authenticated cross-bank transfers with currency conversion, single-transfer-surface API, and Spectral validation rules**

## Performance

- **Duration:** 30 min
- **Started:** 2026-04-08T12:00:00Z
- **Completed:** 2026-04-08T12:30:00Z
- **Tasks:** 4 (3 tasks + 1 deviation fix)
- **Files modified:** 3

## Accomplishments

- Central bank contract extended with GET /exchange-rates endpoint providing EUR-based exchange rates with up to 6 decimal places precision
- Branch bank contract extended with three transfer endpoints: POST /transfers (single surface API), POST /transfers/receive (inter-bank), GET /transfers/{transferId} (status lookup)
- Transfer operation idempotency via client-provided transferId with UUID format and server-side deduplication
- Cross-bank authentication using JWT signed with source bank's private key (ES256 algorithm)
- Currency conversion for cross-bank transfers when source/destination currencies differ, with rate capture for audit trail
- Spectral validation rules for decimal strings, account numbers, currency codes, and transfer-specific constraints
- All schemas follow Phase 1 patterns: decimal strings for money, server-side timestamps, pattern validation for account numbers and currencies

## Task Commits

Each task was committed atomically:

1. **Task 1: Set up Spectral validation rules** - `c6a63da` (feat)
2. **Task 2: Add exchange rates endpoint to central bank contract** - `8fc74b7` (feat)
3. **Task 3: Add transfer endpoints and schemas to branch bank contract** - `9c8fc18` (feat)
4. **Deviation fix: YAML structure correction** - `8ddb720` (fix)

**Plan metadata:** `6260bad` (docs: create phase plan)

## Files Created/Modified

- `.spectral.yml` - Spectral validation rules for OpenAPI contracts (decimal strings, account numbers, currency codes, transfer-specific constraints)
- `openapi/central-bank.yaml` - Added GET /exchange-rates endpoint with ExchangeRatesResponse schema; references DEC-01 (EUR base currency), DEC-02 (no rate TTL in Phase 2)
- `openapi/branch-bank.yaml` - Added three transfer endpoints (POST /transfers, POST /transfers/receive, GET /transfers/{transferId}), transferred request/response schemas (TransferRequest, TransferResponse, InterBankTransferRequest/Response, TransferStatusResponse), updated BearerAuth descriptions, added Transfers tag

## Decisions Made

All decisions were locked during planning phase (D-01 through D-05, DEC-01 through DEC-05). No new runtime decisions required - implementation followed plan exactly:
- D-01: Single POST /transfers endpoint with internal routing based on destination account's bank prefix (first 3 characters)
- D-02: POST /transfers/receive for inter-bank transfer reception, called by source banks using JWT authentication
- D-03: Central bank provides exchange rates via GET /exchange-rates; source bank captures rate at transfer time for audit trail
- D-04: Cross-bank authentication via JWT signed with source bank's private key (ES256 algorithm), destination bank verifies using public key from central bank registry
- D-05: Idempotency via client-provided transferId (UUID format), server rejects duplicate transferId values
- DEC-01: Use EUR as base currency (consistent with Estonian banking context), documented as deployment-time decision
- DEC-02: No rate TTL enforcement in Phase 2, implementations may apply freshness policies
- DEC-03: Use ES256 (ECDSA with P-256) JWT algorithm
- DEC-04: Include GET /transfers/{transferId} in Phase 2 for debugging and consistency
- DEC-05: HTTP body for inter-bank requests contains only JWT field; all data in JWT payload for cryptographic integrity

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed YAML structure in central-bank.yaml**
- **Found during:** Task 2 verification (exchange rates endpoint validation)
- **Issue:** The components section in central-bank.yaml had incorrect indentation (2-space instead of 0-space from document root). Swagger-cli validation failed with "Token 'components' does not exist".
- **Fix:** Changed components section indentation from `  components:` to `components:` (0-space from root). Also fixed bankRemoved example indentation in /banks/{bankId}/heartbeat endpoint which had duplicate content. All schema definitions already had proper 2-space indentation under components/schemas.
- **Files modified:** openapi/central-bank.yaml (248 lines re-indented for fix)
- **Verification:** Both swagger-cli and Spectral validation pass after fix
- **Committed in:** `8ddb720`

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** The YAML structure issue prevented validation but was a simple indentation fix. No scope creep or functional changes.

## Issues Encountered

- YAML indentation error in central-bank.yaml's components section prevented swagger-cli validation. Fix involved adjusting components level from 2-space to 0-space indent (matching branch-bank.yaml pattern). All schemas already had proper nesting.

## Requirements Coverage

All Phase 2 transfer requirements satisfied:
- **XFER-01:** Same-bank transfers via POST /transfers - Endpoint added, schema with account number pattern validation, idempotency via transferId
- **XFER-02:** Cross-bank transfers via POST /transfers - Same endpoint with internal routing based on destination account's bank prefix, currency conversion support
- **XFER-03:** Bank prefix routing - POST /transfers description documents routing logic (first 3 characters), cross-bank transfers route via POST /transfers/receive
- **XFER-04:** Currency conversion - TransferResponse includes convertedAmount, exchangeRate, rateCapturedAt fields for cross-currency transfers; source bank queries GET /exchange-rates from central bank for rate lookup

## Schema Specifications

### ExchangeRatesResponse (Central Bank)
```yaml
baseCurrency: string (pattern: ^[A-Z]{3}$, example: "EUR")
rates: object (key: currency code, value: decimal string pattern ^\d+\.\d{1,6}$)
timestamp: date-time (server-side)
```

### TransferRequest (Branch Bank - POST /transfers)
```yaml
transferId: string (format: uuid, required)
sourceAccount: string (pattern: ^[A-Z0-9]{8}$, required)
destinationAccount: string (pattern: ^[A-Z0-9]{8}$, required)
amount: string (format: decimal, pattern ^\d+\.\d{2}$, required)
```

### TransferResponse (Branch Bank - POST /transfers response)
```yaml
transferId: string (format: uuid)
status: string (enum: [completed, failed, pending])
sourceAccount: string (pattern: ^[A-Z0-9]{8}$)
destinationAccount: string (pattern: ^[A-Z0-9]{8}$)
amount: string (format: decimal, pattern ^\d+\.\d{2}$)
convertedAmount: string (format: decimal, pattern ^\d+\.\d{2}$, optional - cross-currency only)
exchangeRate: string (format: decimal, pattern ^\d+\.\d{6}$, optional - cross-currency only)
rateCapturedAt: date-time (server-side, optional - present when exchangeRate present)
timestamp: date-time (server-side)
errorMessage: string (optional - only when status = "failed")
```

### InterBankTransferRequest (Branch Bank - POST /transfers/receive)
```yaml
jwt: string (JWT signed by source bank, required)
```

### InterBankTransferResponse (Branch Bank - POST /transfers/receive response)
```yaml
transferId: string (format: uuid)
status: string (enum: [completed, failed])
destinationAccount: string (pattern: ^[A-Z0-9]{8}$)
amount: string (format: decimal, pattern ^\d+\.\d{2}$)
timestamp: date-time (server-side)
```

### TransferStatusResponse (Branch Bank - GET /transfers/{transferId})
```yaml
transferId: string (format: uuid)
status: string (enum: [completed, failed, pending])
sourceAccount: string (pattern: ^[A-Z0-9]{8}$)
destinationAccount: string (pattern: ^[A-Z0-9]{8}$)
amount: string (format: decimal, pattern ^\d+\.\d{2}$)
convertedAmount: string (format: decimal, pattern ^\d+\.\d{2}$, optional)
exchangeRate: string (format: decimal, pattern ^\d+\.\d{6}$, optional)
rateCapturedAt: date-time (optional)
timestamp: date-time (server-side)
errorMessage: string (optional - only when status = "failed")
```

## Pattern Consistency with Phase 1

All schemas follow Phase 1 patterns:
- **Pattern 1:** OpenAPI 3.1 with JSON Schema-compatible components
- **Pattern 2:** Comprehensive examples for all request/response bodies (same-bank, cross-bank, cross-currency variants)
- **Pattern 3:** Account-number validation via pattern regex (`^[A-Z0-9]{8}$`)
- **Pattern 4:** Decimal strings for monetary amounts (pattern `^\d+\.\d{2}$` for amounts, `^\d+\.\d{1,6}$` for exchange rates)
- **Pattern 5:** Timestamps always server-side, format `date-time` (RFC 3339)
- **Pattern 6:** Currency codes use pattern `^[A-Z]{3}$` (ISO 4217)
- **Pattern 7:** Bank IDs follow pattern `^[A-Z]{3}\d{3}$`

## Validation Results

All validation passes:
- **swagger-cli:** Both openapi/central-bank.yaml and openapi/branch-bank.yaml validate successfully
- **Spectral:** All custom rules pass (decimal-string-money, account-number-pattern, currency-code-pattern)
- **Schema Completeness:** All transfer schemas include required fields and proper validation patterns
- **Requirement Coverage:** XFER-01 through XFER-04 all satisfied
- **Decision References:** All locked decisions (D-01 through D-05) and decision points (DEC-01 through DEC-05) documented in descriptions

## Next Phase Readiness

Phase 2 foundation complete. Ready for transfer implementation work:
- All schemas defined with proper validation patterns
- Single-surface POST /transfers endpoint with internal routing logic specified
- Inter-bank authentication flow documented (JWT signing/verification)
- Currency conversion capture and storage pattern established
- Idempotency via transferId specified

Phase 3 will address pending transfers, central bank unavailability, and timeout handling (XFER-05 through XFER-07).

## Auth Gates

None - no authentication required for API contract validation work.

## Known Stubs

None found - all schemas have proper field definitions, no placeholder or TODO-marked fields in the OpenAPI contracts.

---
*Phase: 02-same-bank-and-cross-bank-transfers*
*Completed: 2026-04-08*