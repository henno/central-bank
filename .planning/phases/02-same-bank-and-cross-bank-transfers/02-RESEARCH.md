# Phase 2: Same-Bank and Cross-Bank Transfers - Research

**Researched:** 2026-04-08
**Domain:** OpenAPI 3.1 API contract design for financial transfers
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **Transfer Endpoint Structure:** Single transfer endpoint on branch bank (`POST /transfers`) with internal routing logic based on destination account number prefix
- **Inter-Bank Transfer API:** `POST /transfers/receive` on destination branch bank (called by source banks)
- **Currency Conversion Strategy:** Central bank provides exchange rates via new `GET /exchange-rates` endpoint; source bank queries at transfer request time
- **Cross-Bank Authentication:** JWT signed with source bank's private key; destination bank retrieves public key from central bank registry
- **Transfer Idempotency:** Client-provided unique `transferId`; server deduplicates both same-bank and cross-bank transfers

### the agent's Discretion

- Exact schema field names for transfer requests/responses
- Exchange rate response structure (object vs array)
- Inter-bank transfer JWT payload structure
- Transfer status tracking approach for Phase 2 (before Phase 3 pending states)
- Error code naming conventions
- Whether to include `GET /transfers/{transferId}` endpoint

### Deferred Ideas (OUT OF SCOPE)

- Pending transfer handling (Phase 3)
- Central bank unavailability resilience (Phase 3)
- Destination bank timeout handling (Phase 3)
- Transaction history/reconciliation (out of scope)
</user_constraints>

## Summary

Phase 2 extends the distributed banking system's OpenAPI contracts to support both same-bank and cross-bank fund transfers. The research confirms a single-surface API design where branch banks internally route to same-bank or cross-bank paths based on the destination account number's 3-character bank prefix. Cross-bank transfers use JWT-based authentication (signed by source bank, verified by destination bank using central bank registry) and rely on a new central bank exchange rate endpoint for currency conversion.

**Primary recommendation:** Implement the transfers with decimal string amounts for precision, capture exchange rates at transfer time for audit trails, and use client-generated transfer IDs for idempotency across the entire system.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| XFER-01 | User can transfer funds between accounts in the same bank | `POST /transfers` endpoint with internal routing; same-bank path handles transfers where source and destination bank prefixes match |
| XFER-02 | User can transfer funds to another bank using the destination account number | `POST /transfers` routes to cross-bank path when bank prefix differs; uses `POST /transfers/receive` on destination bank |
| XFER-03 | Cross-bank transfers use the bank prefix to resolve the destination bank | Bank prefix (first 3 chars of account number) determines routing; central bank registry provides bank address for resolution |
| XFER-04 | Cross-bank transfers convert currency at the current exchange rate when currencies differ | Central bank `GET /exchange-rates` provides rates; source bank captures rate and applies conversion; stored in transfer record |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| OpenAPI 3.1 | 3.1.0 | API contract specification | University assignment requirement; industry standard for API documentation |
| JWT | RFC 7519 | Inter-bank authentication | Standard for signed claims; leverages existing public key infrastructure |
| ISO 4217 | 2018 | Currency codes | Standard 3-letter currency codes (EUR, USD, GBP) already used in Phase 1 |
| decimal strings | — | Monetary amounts | Avoids floating-point precision issues; standard practice in financial APIs |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| RFC 3339 | — | Timestamps | Already used in Phase 1; consistent timezone-aware datetime format |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Decimal strings | Floating-point numbers | Floats introduce precision errors; decimal strings are safer for money |
| Client-generated transferId | Server-generated transferId | Client generation enables idempotency at request time; server generation requires separate deduplication mechanism |
| Single `/transfers` endpoint | Separate `/transfers/same-bank` and `/transfers/cross-bank` endpoints | Single endpoint simplifies API surface; routing is internal implementation detail |

**Installation:**
No packages required - this is API contract definition only.

**Version verification:** Not applicable - no npm packages involved.

## Architecture Patterns

### Recommended Project Structure
The OpenAPI contracts are split into two files with clear ownership:

```
openapi/
├── central-bank.yaml      # Central bank API (registry, heartbeats, exchange rates)
└── branch-bank.yaml       # Branch bank API (users, accounts, transfers)
```

### Pattern 1: Single Endpoint with Internal Routing
**What:** A single `POST /transfers` endpoint that internally determines whether to route same-bank or cross-bank based on the destination account number's bank prefix.

**When to use:** When the API surface should remain simple while supporting multiple implementation paths.

**Example:**
```yaml
POST /transfers
# Client always uses this endpoint
# Server inspects destinationAccount[0:3] to determine routing
# If prefix matches source bank: same-bank transfer
# If prefix differs: cross-bank transfer
```

**Source:** Based on CONTEXT.md design decision.

### Pattern 2: Decimal Strings for Monetary Amounts
**What:** Always represent money as strings with fixed decimal places, never as floating-point numbers.

**When to use:** All financial values to avoid precision loss.

**Example:**
```yaml
amount:
  type: string
  format: decimal
  pattern: '^\d+\.\d{2}$'
  example: "100.50"
```

**Source:** Financial API best practice; already used in Phase 1 account creation.

### Pattern 3: Client-Generated Idempotency Keys
**What:** Clients provide a unique `transferId` in requests; servers reject duplicates.

**When to use:** Operations that must not be repeated (e.g., money transfers).

**Example:**
```yaml
TransferRequest:
  required:
    - transferId  # Client-generated UUID
    - sourceAccount
    - destinationAccount
    - amount
```

**Source:** CONTEXT.md design decision; standard idempotency pattern.

### Anti-Patterns to Avoid

- **Floating-point money:** Never use `type: number` with `format: float` for monetary values due to precision issues.
- **Server-generated idempotency keys:** Client-generated keys enable immediate deduplication; server generation adds complexity.
- **Exposing internal routing in API:** Don't require clients to specify "same-bank" vs "cross-bank" in requests; keep routing internal.
- **Storing exchange rates as separate records:** Capture the rate in the transfer record itself for audit trail integrity.
- **Missing timestamp capture:** Always capture the rate capture timestamp to distinguish between stale rates.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Currency validation | Custom currency code validator | ISO 4217 pattern regex | Standard currencies already defined |
| JWT signing/verification | Custom crypto implementation | JWT library + existing PKI | Leverages Phase 1 public key infrastructure |
| Decimal arithmetic | Manual string manipulation | Decimal-aware math library (in implementation) | Handles edge cases correctly |
| Timestamp parsing | Custom date logic | RFC 3339 standard | Already used in Phase 1; well-tested |

**Key insight:** The banking system already has public key infrastructure from Phase 1. Reuse it for JWT authentication rather than building new credential systems.

## Runtime State Inventory

> Not applicable - this is a greenfield API contract design phase, not a rename/refactor phase.

## Common Pitfalls

### Pitfall 1: Floating-Point Precision Loss
**What goes wrong:** Using `type: number` with `format: float` can result in `0.1 + 0.2 = 0.30000000000000004`

**Why it happens:** Binary floating-point representation cannot exactly represent decimal fractions.

**How to avoid:** Always use `type: string` with `format: decimal` and pattern `^\d+\.\d{2}$` for monetary amounts.

**Warning signs:** Test cases showing unexpected rounding errors; reconciliation mismatches.

### Pitfall 2: Missing Exchange Rate Capture Timestamp
**What goes wrong:** Exchange rates change over time, but the transfer record doesn't show when the rate was captured, making audits ambiguous.

**Why it happens:** Focusing only on the rate value, not on rate freshness.

**How to avoid:** Include `rateCapturedAt` timestamp in exchange rate-related payloads.

**Warning signs:** Audit queries returning multiple possible rates for the same currency pair; inability to trace which rate was used.

### Pitfall 3: Inconsistent Bank Prefix Extraction
**What goes wrong:** Different parts of the system extract the bank prefix from account numbers inconsistently (e.g., `substring(0,3)` vs `split('-')[0]`).

**Why it happens:** No shared utility or clear specification of prefix format.

**How to avoid:** Document the bank prefix format explicitly in OpenAPI description fields: "First 3 characters of account number".

**Warning signs:** 404 errors when routing cross-bank transfers; prefix validation failing for valid accounts.

### Pitfall 4: JWT Payload Not Self-Contained
**What goes wrong:** JWT contains only a transfer ID, requiring the destination bank to look up source bank details from the registry mid-request.

**Why it happens:** Designing JWT as just a token rather than a signed payload.

**How to avoid:** Include all necessary transfer details in the JWT payload: transferId, sourceAccount, destinationAccount, amount, timestamp, exchangeRate (if applicable), sourceBankId.

**Warning signs:** Destination bank making multiple registry calls; circular dependencies between JWT verification and registry lookup.

### Pitfall 5: Missing Idempotency Scope
**What goes wrong:** Duplicate transfer IDs are rejected globally when they should be scoped to source account or source bank.

**Why it happens:** Idempotency key not clearly scoped in documentation.

**How to avoid:** Document that `transferId` must be unique per source bank (or globally unique if simpler).

**Warning signs:** Legitimate transfers rejected because transferId was reused from a different source account.

## Code Examples

### Transfer Request Schema (Same-Bank and Cross-Bank)

```yaml
# Source: Based on CONTEXT.md design decision and Phase 1 patterns
TransferRequest:
  type: object
  required:
    - transferId
    - sourceAccount
    - destinationAccount
    - amount
  properties:
    transferId:
      type: string
      format: uuid
      description: |
        Unique identifier for this transfer, generated by the client.

        **Idempotency:**
        The server will reject any subsequent request with the same transferId to prevent duplicate transfers.

        **Scope:**
        transferId must be globally unique across all transfers from this source bank.
      example: "550e8400-e29b-41d4-a716-446655440000"
    sourceAccount:
      type: string
      description: |
        The 8-character account number to transfer funds from.

        **Bank Prefix:**
        The first 3 characters identify the source bank and should match the bank's
        assigned prefix from the central bank registry.

        **Validation:**
        The account must exist and have sufficient balance.
      pattern: '^[A-Z0-9]{8}$'
      example: "EST12345"
    destinationAccount:
      type: string
      description: |
        The 8-character account number to transfer funds to.

        **Routing Logic:**
        - If the first 3 characters match the source bank prefix: same-bank transfer
        - If the first 3 characters differ: cross-bank transfer (routed to destination bank)

        **Validation:**
        The account must exist. For cross-bank transfers, the account's existence is
        verified by the destination bank.
      pattern: '^[A-Z0-9]{8}$'
      example: "LAT54321"
    amount:
      type: string
      format: decimal
      description: |
        The amount to transfer, as a decimal string.

        **Format:**
        - Always non-negative
        - Exactly 2 decimal places
        - Must not exceed the source account's available balance

        **Precision:**
        Stored as a string to avoid floating-point precision issues.
      pattern: '^\d+\.\d{2}$'
      example: "100.00"
```

### Transfer Response Schema

```yaml
# Source: Financial API best practices
TransferResponse:
  type: object
  required:
    - transferId
    - status
    - sourceAccount
    - destinationAccount
    - amount
    - timestamp
  properties:
    transferId:
      type: string
      format: uuid
      description: The unique identifier for this transfer
      example: "550e8400-e29b-41d4-a716-446655440000"
    status:
      type: string
      enum: [completed, failed, pending]
      description: |
        The final status of the transfer.

        **Status Values:**
        - `completed`: Transfer succeeded (funds debited from source, credited to destination)
        - `failed`: Transfer failed (e.g., insufficient funds, account not found)
        - `pending`: Transfer is pending (only for cross-bank transfers when destination bank is unreachable; Phase 3 feature)

        **Phase 2 Note:**
        In Phase 2, `pending` status is not used. Failed cross-bank transfers immediately return `failed`.
        Phase 3 introduces the pending transfer workflow.
      example: "completed"
    sourceAccount:
      type: string
      description: The account funds were debited from
      pattern: '^[A-Z0-9]{8}$'
      example: "EST12345"
    destinationAccount:
      type: string
      description: The account funds were credited to
      pattern: '^[A-Z0-9]{8}$'
      example: "LAT54321"
    amount:
      type: string
      format: decimal
      description: The amount transferred (in source currency)
      pattern: '^\d+\.\d{2}$'
      example: "100.00"
    convertedAmount:
      type: string
      format: decimal
      description: |
        The amount credited to the destination account (in destination currency).

        **Currency Conversion:**
        Only present when source and destination accounts use different currencies.

        **Rate Application:**
        convertedAmount = amount * exchangeRate (rounded to 2 decimal places)

        **Phase 2 Note:**
        For same-bank transfers, source and destination currency are always the same,
        so this field is typically omitted (or equals amount).
      pattern: '^\d+\.\d{2}$'
      example: "108.50"
    exchangeRate:
      type: string
      format: decimal
      description: |
        The exchange rate used for currency conversion (if applicable).

        **Format:**
        Rate from source currency to destination currency.

        **Example:**
        If source is EUR and destination is GBP, a rate of "0.85" means 1 EUR = 0.85 GBP.

        **Capture Time:**
        The rate was captured from the central bank at `rateCapturedAt`.
      pattern: '^\d+\.\d{6}$'
      example: "0.850000"
    rateCapturedAt:
      type: string
      format: date-time
      description: |
        The timestamp when the exchange rate was captured from the central bank.

        **Purpose:**
        Links the exchange rate to a specific point in time for audit and reconciliation.

        **Phase 2 Note:**
        Only present when currency conversion occurred (exchangeRate field is present).
      example: "2026-04-08T12:00:00Z"
    timestamp:
      type: string
      format: date-time
      description: The timestamp when the transfer was completed
      example: "2026-04-08T12:00:05Z"
    errorMessage:
      type: string
      description: |
        Human-readable error message explaining why the transfer failed.

        **Present Only When:**
        status = "failed"

        **Error Scenarios:**
        - Insufficient funds
        - Source account not found
        - Destination account not found
        - Destination bank unreachable (Phase 2 returns failed; Phase 3 uses pending)
      example: "Insufficient funds in source account"
```

### Exchange Rate Response Schema (Central Bank)

```yaml
# Source: Currency conversion API best practices
ExchangeRatesResponse:
  type: object
  required:
    - baseCurrency
    - rates
    - timestamp
  properties:
    baseCurrency:
      type: string
      description: |
        The base currency for all exchange rates.

        **Rate Interpretation:**
        All rates are relative to this base currency.

        **Example:**
        If baseCurrency is "EUR" and rates = {"GBP": "0.85", "USD": "1.08"},
        then:
        - 1 EUR = 0.85 GBP
        - 1 EUR = 1.08 USD

        **Phase 2 Decision:**
        Central bank chooses one base currency (e.g., EUR) and all rates
        are relative to that currency. Banks perform necessary arithmetic.
      pattern: '^[A-Z]{3}$'
      example: "EUR"
    rates:
      type: object
      description: |
        Map of currency codes to exchange rates (all relative to baseCurrency).

        **Rate Format:**
        - String format to avoid precision issues
        - Up to 6 decimal places for finer precision
        - Rates are for 1 unit of baseCurrency

        **Cross-Rate Calculation:**
        To convert from currency A to currency B:
        rate(A → B) = rate(base → B) / rate(base → A)

        **Example:**
        If baseCurrency = "EUR", rates = {"GBP": "0.85", "USD": "1.08"}
        To convert 100 USD to GBP:
        rate(USD → EUR) = 1 / 1.08 = 0.925926
        rate(EUR → GBP) = 0.85
        rate(USD → GBP) = 0.925926 * 0.85 = 0.787037
        100 USD = 78.70 GBP
      additionalProperties:
        type: string
        format: decimal
        pattern: '^\d+\.\d{1,6}$'
        example: "0.850000"
      example:
        GBP: "0.850000"
        USD: "1.080000"
        SEK: "10.500000"
        LVL: "0.680000"
    timestamp:
      type: string
      format: date-time
      description: |
        The timestamp when these exchange rates were last updated.

        **Freshness:**
        Banks should capture this timestamp when querying rates to ensure
        they're using reasonably current rates.

        **Phase 2 Consideration:**
        No explicit TTL is defined in Phase 2. Implementations may choose to
        reject older rates (e.g., > 1 hour old) or apply rate freshness policies.
      example: "2026-04-08T12:00:00Z"
```

### Inter-Bank Transfer Request (JWT Payload)

```yaml
# Source: CONTEXT.md JWT authentication decision
InterBankTransferPayload:
  description: |
    JWT header and payload structure for cross-bank transfers.

    **JWT Header:**
    ```json
    {
      "alg": "ES256",  // Elliptic Curve DSA (recommended for banking)
      "typ": "JWT"
    }
    ```

    **JWT Payload (Claims):**
  type: object
  required:
    - transferId
    - sourceAccount
    - destinationAccount
    - amount
    - sourceBankId
    - destinationBankId
    - timestamp
    - nonce
  properties:
    # Standard JWT claims
    iss:
      type: string
      description: Issuer (source bank ID)
      example: "EST001"
    sub:
      type: string
      description: Subject (the transfer ID)
      example: "550e8400-e29b-41d4-a716-446655440000"
    aud:
      type: string
      description: Audience (destination bank ID)
      example: "LAT002"
    iat:
      type: integer
      description: Issued at timestamp (Unix timestamp)
      example: 1617883200
    exp:
      type: integer
      description: Expiration timestamp (Unix timestamp, e.g., 5 minutes from iat)
      example: 1617883500

    # Custom claims for transfer data
    transferId:
      type: string
      format: uuid
      description: Unique identifier for the transfer
      example: "550e8400-e29b-41d4-a716-446655440000"
    sourceAccount:
      type: string
      pattern: '^[A-Z0-9]{8}$'
      description: Source account number (debit account)
      example: "EST12345"
    destinationAccount:
      type: string
      pattern: '^[A-Z0-9]{8}$'
      description: Destination account number (credit account)
      example: "LAT54321"
    amount:
      type: string
      format: decimal
      pattern: '^\d+\.\d{2}$'
      description: Amount transferred (in source currency)
      example: "100.00"
    convertedAmount:
      type: string
      format: decimal
      pattern: '^\d+\.\d{2}$'
      description: |
        Amount that should be credited (in destination currency).

        Only present when currency conversion occurred.
      example: "85.00"
    sourceCurrency:
      type: string
      pattern: '^[A-Z]{3}$'
      description: Source account currency code
      example: "EUR"
    destinationCurrency:
      type: string
      pattern: '^[A-Z]{3}$'
      description: Destination account currency code
      example: "GBP"
    exchangeRate:
      type: string
      format: decimal
      pattern: '^\d+\.\d{6}$'
      description: |
        Exchange rate used (source → destination).

        Only present when currency conversion occurred.
      example: "0.850000"
    rateCapturedAt:
      type: string
      format: date-time
      description: |
        Timestamp when the exchange rate was captured from the central bank.

        Only present when exchangeRate is present.
      example: "2026-04-08T12:00:00Z"
    sourceBankId:
      type: string
      pattern: '^[A-Z]{3}\d{3}$'
      description: Source bank identifier (from central bank registry)
      example: "EST001"
    destinationBankId:
      type: string
      pattern: '^[A-Z]{3}\d{3}$'
      description: Destination bank identifier (from central bank registry)
      example: "LAT002"
    timestamp:
      type: string
      format: date-time
      description: |
        Server timestamp when the transfer was initiated by the source bank.

        Used for deduplication and audit trail.
      example: "2026-04-08T12:00:00Z"
    nonce:
      type: string
      description: |
        Random nonce to prevent replay attacks.

        **Purpose:**
        Ensures the same JWT cannot be replayed even if exp is not enforced.

        **Format:**
        Cryptographically random string (recommended: 16+ bytes, hex-encoded)
      example: "a1b2c3d4e5f6g7h8"
```

### Inter-Bank Transfer Endpoint Schema

```yaml
# Source: CONTEXT.md inter-bank API decision
# This is the schema for the HTTP request body (not the JWT payload)
InterBankTransferRequest:
  type: object
  required:
    - jwt
  properties:
    jwt:
      type: string
      description: |
        JWT token signed by the source bank's private key containing all transfer details.

        **Verification Flow:**
        1. Destination bank extracts `iss` (source bank ID) from JWT header/payload
        2. Destination bank retrieves source bank's public key from central bank registry
        3. Destination bank verifies JWT signature using the public key
        4. Destination bank validates `aud` matches its own bank ID
        5. Destination bank validates `iats` and `exp` timestamps (within allowed window)
        6. Destination bank extracts transfer details from payload and processes transfer

        **Error Handling:**
        - Invalid signature: 401 Unauthorized
        - Invalid audience: 403 Forbidden
        - Expired JWT: 401 Unauthorized
        - Invalid payload: 400 Bad Request

        **Implementation Note:**
        The actual transfer data is in the JWT payload, but this request wrapper
        allows the JWT to be transparently passed as a single string.
      example: "eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0cmFuc2ZlcklkIjoiNTUwZTg0MDAtZTI5Yi00MWQ0LWE3MTYtNDQ2NjU1NDQwMDAwIiwic291cmNlQWNjb3VudCI6IkVTVDEyMzQ1IiwiZGVzdGluYXRpb25BY2NvdW50IjoiTEFUNTU0MzIxIiwiYW1vdW50IjoiMTAwLjAwIiwic291cmNlQmFua0lkIjoiRVNUMDEiLCJkZXN0aW5hdGlvbkJhbmtJZCI6IkxBVDAwMiIsInRpbWVzdGFtcCI6IjIwMjYtMDQtMDhUMTI6MDA6MDBaIiwibm9uY2UiOiJhMWIyYzNkNGU1ZjZnN2g4In0.signature"
```

### Error Response Schema

```yaml
# Source: Phase 1 error pattern, extended for transfer errors
Error:
  type: object
  required:
    - code
    - message
  properties:
    code:
      type: string
      description: Machine-readable error code for programmatic handling
      example: "INSUFFICIENT_FUNDS"
    message:
      type: string
      description: Human-readable error message
      example: "Insufficient funds in source account EST12345"
    details:
      type: object
      description: |
        Additional error details (optional).

        **Present When:**
        Additional context would help diagnose the issue.

        **Common Fields:**
        - `sourceAccount`: The account that caused the error
        - `destinationAccount`: The account that was being credited
        - `availableBalance`: Actual balance when insufficient funds error
        - `destinationBankId`: Bank ID for connection errors
        - `reasonCode`: Specific sub-reason for retry guidance
      example:
        sourceAccount: "EST12345"
        availableBalance: "50.00"
        requestedAmount: "100.00"
```

### Transfer Status Lookup Schema (Optional)

```yaml
# Source: Optional endpoint for status tracking (useful for debugging)
TransferStatusResponse:
  type: object
  required:
    - transferId
    - status
    - sourceAccount
    - destinationAccount
    - amount
    - timestamp
  properties:
    transferId:
      type: string
      format: uuid
      description: The unique identifier for this transfer
      example: "550e8400-e29b-41d4-a716-446655440000"
    status:
      type: string
      enum: [completed, failed, pending]
      description: Current status of the transfer
      example: "completed"
    sourceAccount:
      type: string
      pattern: '^[A-Z0-9]{8}$'
      example: "EST12345"
    destinationAccount:
      type: string
      pattern: '^[A-Z0-9]{8}$'
      example: "LAT54321"
    amount:
      type: string
      format: decimal
      example: "100.00"
    convertedAmount:
      type: string
      format: decimal
      description: Present only for cross-currency transfers
      example: "85.00"
    exchangeRate:
      type: string
      format: decimal
      description: Present only for cross-currency transfers
      example: "0.850000"
    rateCapturedAt:
      type: string
      format: date-time
      example: "2026-04-08T12:00:00Z"
    timestamp:
      type: string
      format: date-time
      example: "2026-04-08T12:00:05Z"
    errorMessage:
      type: string
      description: Present only when status = "failed"
      example: "Destination bank LAT002 unreachable"
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Floating-point money | Decimal strings | ~2010s | Eliminated precision errors in financial calculations |
| Server-generated transfer IDs | Client-generated transfer IDs | ~2015 | Enabled idempotency without server-side deduplication state |
| Shared secret authentication | JWT with public key verification | ~2018 | No shared secret management; leverages PKI infrastructure |
| Central bank as rate source only | Central bank as rate authority and registry | Modern distributed systems | Consistent rates across all banks; single source of truth |
| Separate same-bank/cross-bank APIs | Single API with internal routing | Microservices era | Simpler API surface; implementation detail hidden from clients |

**Deprecated/outdated:**
- **SOAP-based financial APIs:** Replaced by REST/JSON for simpler integration
- **XML-only responses:** JSON is now standard for modern financial APIs
- **Opaque transaction IDs:** Structured transfer IDs with UUIDs improve traceability

## Open Questions

1. **Exchange rate base currency selection**
   - What we know: Central bank provides rates; banks perform arithmetic; rates are relative to a base currency
   - What's unclear: Which currency should be the base (EUR, USD, or bank's choice)?
   - Recommendation: Choose EUR as base currency (consistent with Estonian banking assignment context), but document that this is a deployment-time decision

2. **Exchange rate freshness policy**
   - What we know: Rates have a `timestamp` field; banks capture rates at transfer time
   - What's unclear: Should banks reject "stale" rates (e.g., > 1 hour old)?
   - Recommendation: Phase 2 doesn't enforce a rate TTL; defer to implementations; Phase 3 may add rate caching policies if central bank unavailability is addressed

3. **JWT algorithm and key size**
   - What we know: JWT signed with source bank's private key; public keys from Phase 1 registry
   - What's unclear: Which algorithm (RS256, ES256, etc.) and key size?
   - Recommendation: Use ES256 (ECDSA with P-256) as it's the default in many JWT libraries; document that the algorithm must match the public key format from Phase 1

4. **Transfer status lookup必要性**
   - What we know: Transfers return status in response; Phase 3 introduces pending transfers
   - What's unclear: Is `GET /transfers/{transferId}` endpoint needed in Phase 2?
   - Recommendation: Include it in Phase 2 for debugging and consistency, even if Phase 2 doesn't have complex status flows; Phase 3 will extend it with pending status

5. **Inter-bank transfer request content**
   - What we know: JWT payload contains transfer data; HTTP body wraps the JWT
   - What's unclear: Should the HTTP body contain anything besides the JWT (e.g., metadata)?
   - Recommendation: Keep HTTP body minimal (only `jwt` field) to avoid payload duplication; all data in JWT for cryptographic integrity

## Environment Availability

> Phase 2 has no external dependencies beyond existing infrastructure. Skip environment availability audit.

## Validation Architecture

The planning config specifies `nyquist_validation: true`, so validation architecture must be included.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | OpenAPI linter (spectral) + Contract validation |
| Config file | `.spectral.yml` (to be created) |
| Quick run command | `spectral lint openapi/central-bank.yaml openapi/branch-bank.yaml` |
| Full suite command | `spectral lint openapi/central-bank.yaml openapi/branch-bank.yaml --fail-on-warn` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| XFER-01 | Same-bank transfers via POST /transfers | contract | `spectral lint openapi/branch-bank.yaml` | ❌ Wave 0 |
| XFER-02 | Cross-bank transfers via POST /transfers | contract | `spectral lint openapi/branch-bank.yaml` | ❌ Wave 0 |
| XFER-03 | Bank prefix routing | contract | `spectral lint openapi/branch-bank.yaml` | ❌ Wave 0 |
| XFER-04 | Currency conversion | contract | `spectral lint openapi/central-bank.yaml openapi/branch-bank.yaml` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `spectral lint openapi/central-bank.yaml openapi/branch-bank.yaml`
- **Per wave merge:** `spectral lint openapi/central-bank.yaml openapi/branch-bank.yaml --fail-on-warn`
- **Phase gate:** Spectral lint passes with zero errors before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `.spectral.yml` — OpenAPI validation rules configuration
- [ ] Spectral CLI installed (`npm install -g @stoplight/spectral-cli`)
- [ ] Custom rules for transfer-specific constraints (e.g., decimal string validation)
- [ ] Test fixture files for transfer examples (optional but recommended)

**Gap reason:** This is a document-focused phase; existing test infrastructure (if any) expects code, not OpenAPI contracts. Contract validation requires Spectral or equivalent tooling.

## Sources

### Primary (HIGH confidence)
- Phase 1 OpenAPI contracts (`openapi/central-bank.yaml`, `openapi/branch-bank.yaml`) - Existing patterns, schema definitions
- CONTEXT.md (Phase 2) - Locked design decisions, endpoint structure, authentication approach
- REQUIREMENTS.md - XFER-01 through XFER-04 requirements

### Secondary (MEDIUM confidence)
- Financial API best practices (training) - Decimal strings for money, idempotency patterns
- OpenAPI 3.1 specification (training) - Schema patterns, component organization
- JWT RFC 7519 (training) - Header/payload structure, standard claims

### Tertiary (LOW confidence)
- *None - all findings derived from project artifacts and established financial API patterns*

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Based on assignment requirements and existing patterns
- Architecture: HIGH - Locked decisions in CONTEXT.md guide the design
- Pitfalls: HIGH - Financial API patterns are well-documented and established

**Research date:** 2026-04-08
**Valid until:** 60 days (stable API contract design; depends on Phase 3 pending transfer requirements)