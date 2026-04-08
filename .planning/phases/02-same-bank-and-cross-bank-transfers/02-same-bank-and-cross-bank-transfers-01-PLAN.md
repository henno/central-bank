---
phase: 02-same-bank-and-cross-bank-transfers
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - .spectral.yml
  - openapi/central-bank.yaml
  - openapi/branch-bank.yaml
autonomous: true
requirements:
  - XFER-01
  - XFER-02
  - XFER-03
  - XFER-04

must_haves:
  truths:
    - "User can initiate same-bank transfers via POST /transfers"
    - "User can initiate cross-bank transfers via POST /transfers"
    - "Cross-bank transfers route based on destination account's bank prefix"
    - "Cross-bank transfers perform currency conversion using central bank rates"
    - "Transfer operations are idempotent via client-provided transferId"
    - "Inter-bank transfers authenticate via JWT signed by source bank"
  artifacts:
    - path: ".spectral.yml"
      provides: "OpenAPI validation rules"
      contains: "spectral CLI rules"
    - path: "openapi/central-bank.yaml"
      provides: "GET /exchange-rates endpoint"
      exports: ["GET /exchange-rates", "ExchangeRatesResponse schema"]
    - path: "openapi/branch-bank.yaml"
      provides: "Transfer endpoints and schemas"
      exports: ["POST /transfers", "POST /transfers/receive", "GET /transfers/{transferId}", "TransferRequest", "TransferResponse", "InterBankTransferRequest"]
  key_links:
    - from: "POST /transfers"
      to: "destinationAccount[0:3]"
      via: "bank prefix extraction"
      pattern: "destinationAccount.*substring.*0.*3"
    - from: "POST /transfers"
      to: "GET /exchange-rates"
      via: "currency conversion"
      pattern: "fetch.*exchange-rates"
    - from: "POST /transfers/receive"
      to: "public key registry"
      via: "JWT verification"
      pattern: "public.*key.*verify.*jwt"
---

<objective>
Extend the OpenAPI contracts to support both same-bank and cross-bank fund transfers with currency conversion capabilities.

Purpose: Enable secure, idempotent fund transfers within and between banks using a single API surface with internal routing based on bank prefixes.

Output: Complete OpenAPI specifications for transfer operations with full schema definitions, examples, and validation rules.
</objective>

<execution_context>
@$HOME/.config/opencode/get-shit-done/workflows/execute-plan.md
@$HOME/.config/opencode/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/phases/02-same-bank-and-cross-bank-transfers/CONTEXT.md
@.planning/phases/02-same-bank-and-cross-bank-transfers/02-RESEARCH.md
@.planning/phases/01-registry-and-accounts/01-registry-and-accounts-01-SUMMARY.md
@.planning/phases/01-registry-and-accounts/01-registry-and-accounts-02-SUMMARY.md
@openapi/central-bank.yaml
@openapi/branch-bank.yaml

## Phase 1 Patterns to Follow

From Phase 1 SUMMARYs:
- **Pattern 1:** OpenAPI 3.1 with JSON Schema-compatible components
- **Pattern 2:** Comprehensive examples for all request/response bodies
- **Pattern 3:** Explicit account-number validation via pattern regex (`^[A-Z0-9]{8}$`)
- **Pattern 4:** Decimal strings for monetary amounts (pattern `^-?\d+\.\d{2}$`)
- **Pattern 5:** Timestamps always server-side, format `date-time` (RFC 3339)
- **Pattern 6:** User IDs follow pattern `^user-[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$`
- **Pattern 7:** Bank IDs follow pattern `^[A-Z]{3}\d{3}$`

## Existing Schema References

From `openapi/central-bank.yaml`:
- `BankDetails` schema contains `publicKey`, `bankId`, `address`, `lastHeartbeat`, `status`
- `Error` schema structure: `{code, message, details?}`

From `openapi/branch-bank.yaml`:
- `AccountLookupResponse` returns `{accountNumber, ownerName, currency}`
- Account number pattern: `^[A-Z0-9]{8}$`
- Currency pattern: `^[A-Z]{3}$`

## Locked Design Decisions (D-01 through D-05)

**D-01 (CONTEXT.md):** Single transfer endpoint `POST /transfers` with internal routing
- Routing based on destination account number's bank prefix (first 3 characters)
- Same-bank if prefix matches source bank, cross-bank otherwise

**D-02 (CONTEXT.md):** Inter-bank transfer uses `POST /transfers/receive` on destination bank
- Called by source banks (authenticated via JWT)
- JWT signed with source bank's private key

**D-03 (CONTEXT.md):** Central bank provides exchange rates via `GET /exchange-rates`
- Source bank queries rates at transfer request time
- Rate captured in transfer record for audit trail

**D-04 (CONTEXT.md):** Cross-bank authentication via JWT signed with source bank's private key
- Destination bank retrieves source bank's public key from central bank registry
- JWT payload contains: transferId, sourceAccount, destinationAccount, amount, timestamp, exchangeRate (if applicable)

**D-05 (CONTEXT.md):** Idempotency via client-provided transferId
- Server rejects requests with duplicate transferId values
- Applies to both same-bank and cross-bank transfers

## Decision Points Resolved (from 02-RESEARCH.md)

**DEC-01 (Exchange rate base currency):** Use EUR as base currency (consistent with Estonian banking context), but document that this is a deployment-time decision

**DEC-02 (Exchange rate freshness):** Phase 2 doesn't enforce rate TTL; defer to implementations; Phase 3 may add rate caching policies

**DEC-03 (JWT algorithm):** Use ES256 (ECDSA with P-256) - documented in JWT header example

**DEC-04 (Transfer status lookup):** Include `GET /transfers/{transferId}` in Phase 2 for debugging and consistency

**DEC-05 (Inter-bank request content):** HTTP body contains only `jwt` field; all data in JWT payload for cryptographic integrity

## Schema Specifications

**ExchangeRatesResponse (from RESEARCH.md lines 395-470):**
```yaml
baseCurrency: string (pattern: ^[A-Z]{3}$, example: "EUR")
rates: object (key: currency code, value: decimal string pattern ^\d+\.\d{1,6}$)
timestamp: date-time
```

**TransferRequest (from RESEARCH.md lines 210-277):**
```yaml
transferId: string (format: uuid)
sourceAccount: string (pattern: ^[A-Z0-9]{8}$)
destinationAccount: string (pattern: ^[A-Z0-9]{8}$)
amount: string (format: decimal, pattern ^\d+\.\d{2}$)
```

**TransferResponse (from RESEARCH.md lines 278-393):**
```yaml
transferId: string (format: uuid)
status: string (enum: [completed, failed, pending])
sourceAccount: string (pattern: ^[A-Z0-9]{8}$)
destinationAccount: string (pattern: ^[A-Z0-9]{8}$)
amount: string (format: decimal)
convertedAmount?: string (format: decimal, pattern ^\d+\.\d{2}$) - only for cross-currency
exchangeRate?: string (format: decimal, pattern ^\d+\.\d{6}$) - only for cross-currency
rateCapturedAt?: date-time - only when exchangeRate present
timestamp: date-time
errorMessage?: string - only when status="failed"
```

**InterBankTransferRequest (from RESEARCH.md lines 611-644):**
```yaml
jwt: string (JWT token signed by source bank)
```

**JWT Payload structure (from RESEARCH.md lines 472-609):**
- Standard claims: iss, sub, aud, iat, exp
- Custom claims: transferId, sourceAccount, destinationAccount, amount, convertedAmount?, sourceCurrency, destinationCurrency, exchangeRate?, rateCapturedAt?, sourceBankId, destinationBankId, timestamp, nonce
</context>

<tasks>

<task type="auto">
  <name>Task 1: Set up Spectral validation rules</name>
  <files>.spectral.yml</files>
  <action>
    Create `.spectral.yml` configuration file for OpenAPI 3.1 contract validation.

    **Configuration Requirements:**
    - Use `@stoplight/spectral-rulesets` for standard OpenAPI rules
    - Enable `@stoplight/spectral-ruleset-resolver` for external rulesets
    - Enable `@stoplight/spectral-ruleset-bundled` for core rules
    
    **Custom Rules to Add:**
    1. `decimal-string-money`: Validate that monetary amounts use `format: decimal` and `pattern` matching `^\d+\.\d{2}$` or `^-\d+\.\d{2}$`
    2. `account-number-pattern`: Validate that account numbers use `pattern: ^[A-Z0-9]{8}$`
    3. `currency-code-pattern`: Validate that currency codes use `pattern: ^[A-Z]{3}$`
    4. `transfer-id-required`: Ensure transfer-related schemas include `transferId` field with `format: uuid`
    5. `timestamp-server-side`: Ensure timestamp fields have `format: date-time` and description mentioning server-side storage
    
    **Severity Levels:**
    - Schema validation errors: ERROR
    - Missing examples: WARN
    - Documentation gaps: WARN
    
    **Spectral CLI Commands:**
    Quick check: `spectral lint openapi/*.yaml --format stylish`
    Strict check: `spectral lint openapi/*.yaml --fail-on-warn --format json`
    
    Reference: Stoplight Spectral documentation https://docs.stoplight.io/docs/spectral/
  </action>
  <verify>
    <automated>spectral lint --version && spectral lint .spectral.yml --format stylish</automated>
  </verify>
  <done>
    - `.spectral.yml` file created with standard and custom rules
    - All custom rules target transfer-specific constraints (decimal strings, account numbers, currency codes)
    - Spectral CLI validation runs without error
  </done>
</task>

<task type="auto">
  <name>Task 2: Add exchange rates endpoint to central bank contract</name>
  <files>openapi/central-bank.yaml</files>
  <action>
    Add `GET /exchange-rates` endpoint to `openapi/central-bank.yaml` following the EXCHANGE-RATES tag pattern.

    **Changes Required:**

    1. **Add new tag:**
    ```yaml
    tags:
      - name: Registry
      - name: Heartbeat
      - name: ExchangeRates  # NEW
        description: Currency exchange rate operations
    ```

    2. **Add new path:**
    ```yaml
    /exchange-rates:
      get:
        tags: [ExchangeRates]
        summary: Get current exchange rates
        description: |
          Returns the current exchange rates for all supported currencies.
          
          **Rate Structure:**
          - Rates are relative to the base currency (EUR per DEC-01)
          - Rates are decimal strings to avoid precision issues
          - Rates include up to 6 decimal places for finer precision
          
          **Cross-Rate Calculation:**
          To convert from currency A to currency B:
          rate(A → B) = rate(base → B) / rate(base → A)
          
          **Freshness:**
          The timestamp indicates when these rates were last updated.
          Banks should capture this timestamp when querying for audit purposes.
        operationId: getExchangeRates
        security: []
        responses:
          '200':
            description: Exchange rates retrieved successfully
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/ExchangeRatesResponse'
                examples:
                  currentRates:
                    summary: Current exchange rates
                    value:
                      baseCurrency: "EUR"
                      rates:
                        GBP: "0.850000"
                        USD: "1.080000"
                        SEK: "10.500000"
                        LVL: "0.680000"
                      timestamp: "2026-04-08T12:00:00Z"
          '500':
            description: Internal server error
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
          '503':
            description: Service temporarily unavailable (rates being updated)
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
    ```

    3. **Add ExchangeRatesResponse schema:**
    ```yaml
    components/schemas:
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
              
              **Base Currency Decision (DEC-01):**
              EUR is used as the base currency (consistent with Estonian banking context).
              This is a deployment-time decision that can be changed per implementation.
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
              description: Exchange rate relative to base currency
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
              
              **Rate Freshness Policy (DEC-02):**
              Phase 2 doesn't enforce a rate TTL. Implementations may choose to
              reject older rates (e.g., > 1 hour old) or apply rate freshness policies.
              Phase 3 may add rate caching policies if central bank unavailability is addressed.
            example: "2026-04-08T12:00:00Z"
    ```

    4. **Update info description** to mention exchange rates endpoint

    **Validation:**
    - Follow Phase 1 pattern for schema organization (in components/schemas)
    - Use decimal strings for rates (pattern `^\d+\.\d{1,6}$`)
    - Include comprehensive examples
    - Server-side timestamp (no client control)
    - Reference DEC-01 and DEC-02 in descriptions
  </action>
  <verify>
    <automated>spectral lint openapi/central-bank.yaml --format stylish</automated>
  </verify>
  <done>
    - `GET /exchange-rates` endpoint added to central bank contract
    - `ExchangeRatesResponse` schema defined with baseCurrency, rates, timestamp
    - All monetary values use decimal strings with proper patterns
    - Examples cover EUR base currency with GBP, USD, SEK, LVL rates
    - Spectral lint passes without errors
  </done>
</task>

<task type="auto">
  <name>Task 3: Add transfer endpoints and schemas to branch bank contract</name>
  <files>openapi/branch-bank.yaml</files>
  <action>
    Add transfer endpoints and schemas to `openapi/branch-bank.yaml` following Phase 1 patterns and locked design decisions D-01 through D-05.

    **Changes Required:**

    1. **Add new tag:**
    ```yaml
    tags:
      - name: Users
      - name: Accounts
      - name: Transfers  # NEW
        description: Fund transfer operations (same-bank and cross-bank)
    ```

    2. **Add path: POST /transfers**
    ```yaml
    /transfers:
      post:
        tags: [Transfers]
        summary: Initiate a fund transfer
        description: |
          Initiates a fund transfer from a source account to a destination account.
          
          **Routing Logic (D-01):**
          The branch bank determines whether the transfer is same-bank or cross-bank
          by inspecting the destination account number's bank prefix (first 3 characters):
          - If prefix matches source bank: same-bank transfer (local processing)
          - If prefix differs: cross-bank transfer (routes to destination bank via POST /transfers/receive)
          
          **Idempotency (D-05):**
          Clients must provide a unique `transferId`. The server rejects any subsequent
          request with the same transferId to prevent duplicate transfers. transferId
          must be globally unique across all transfers from this source bank.
          
          **Currency Conversion (XFER-04):**
          For cross-bank transfers where source and destination accounts use different
          currencies, the source bank:
          1. Queries current exchange rates from central bank (GET /exchange-rates)
          2. Calculates the converted amount using the rate
          3. Captures the rate and timestamp for audit trail
          4. Includes convertedAmount, exchangeRate, and rateCapturedAt in transfer record
          
          **Cross-Bank Authentication (D-04):**
          For cross-bank transfers, the source bank:
          1. Signs the transfer details as a JWT using its private key
          2. Sends the JWT to the destination bank via POST /transfers/receive
          3. Destination bank verifies the JWT using the source bank's public key (from central bank registry)
          
          **Error Handling:**
          - Insufficient funds: 422 Unprocessable Entity with INSUFFICIENT_FUNDS code
          - Source account not found: 404 Not Found
          - Destination account not found: 404 Not Found
          - Duplicate transferId: 409 Conflict with DUPLICATE_TRANSFER code
          - Bank prefix validation failure: 400 Bad Request
        operationId: initiateTransfer
        security:
          - BearerAuth: []
        requestBody:
          required: true
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TransferRequest'
              examples:
                sameBankTransfer:
                  summary: Same-bank transfer (EST to EST)
                  value:
                    transferId: "550e8400-e29b-41d4-a716-446655440000"
                    sourceAccount: "EST12345"
                    destinationAccount: "EST54321"
                    amount: "100.00"
                crossBankTransfer:
                  summary: Cross-bank transfer (EST to LAT)
                  value:
                    transferId: "660e9511-f30c-52e5-b827-557766551111"
                    sourceAccount: "EST12345"
                    destinationAccount: "LAT54321"
                    amount: "100.00"
                crossBankWithConversion:
                  summary: Cross-bank transfer with currency conversion (EUR to GBP)
                  value:
                    transferId: "770e0622-g41d-63f6-c938-338877662222"
                    sourceAccount: "EST67890"
                    destinationAccount: "GBR12345"
                    amount: "100.00"
        responses:
          '201':
            description: Transfer initiated successfully (completed for same-bank, sent for cross-bank)
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/TransferResponse'
                examples:
                  sameBankCompleted:
                    summary: Same-bank transfer completed
                    value:
                      transferId: "550e8400-e29b-41d4-a716-446655440000"
                      status: "completed"
                      sourceAccount: "EST12345"
                      destinationAccount: "EST54321"
                      amount: "100.00"
                      timestamp: "2026-04-08T12:00:05Z"
                  crossBankCompleted:
                    summary: Cross-bank transfer completed
                    value:
                      transferId: "660e9511-f30c-52e5-b827-557766551111"
                      status: "completed"
                      sourceAccount: "EST12345"
                      destinationAccount: "LAT54321"
                      amount: "100.00"
                      timestamp: "2026-04-08T12:00:10Z"
                  crossBankWithConversion:
                    summary: Cross-bank transfer with currency conversion
                    value:
                      transferId: "770e0622-g41d-63f6-c938-338877662222"
                      status: "completed"
                      sourceAccount: "EST67890"
                      destinationAccount: "GBR12345"
                      amount: "100.00"
                      convertedAmount: "85.00"
                      exchangeRate: "0.850000"
                      rateCapturedAt: "2026-04-08T12:00:00Z"
                      timestamp: "2026-04-08T12:00:15Z"
          '400':
            description: Invalid request
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  invalidRequest:
                    summary: Malformed request
                    value:
                      code: "INVALID_REQUEST"
                      message: "transferId is required"
                  invalidAccountNumber:
                    summary: Invalid account number format
                    value:
                      code: "INVALID_ACCOUNT_NUMBER"
                      message: "Account number must be exactly 8 characters"
          '401':
            description: Unauthorized
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  unauthorized:
                    summary: Authentication required
                    value:
                      code: "UNAUTHORIZED"
                      message: "Authentication is required to initiate transfers"
          '404':
            description: Account not found
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  sourceAccountNotFound:
                    summary: Source account does not exist
                    value:
                      code: "SOURCE_ACCOUNT_NOT_FOUND"
                      message: "Source account 'EST12345' not found"
                  destinationAccountNotFound:
                    summary: Destination account does not exist
                    value:
                      code: "DESTINATION_ACCOUNT_NOT_FOUND"
                      message: "Destination account 'LAT54321' not found"
          '409':
            description: Duplicate transfer
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  duplicateTransfer:
                    summary: Transfer ID already used
                    value:
                      code: "DUPLICATE_TRANSFER"
                      message: "A transfer with ID '550e8400-e29b-41d4-a716-446655440000' already exists"
          '422':
            description: Unprocessable entity
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  insufficientFunds:
                    summary: Insufficient funds in source account
                    value:
                      code: "INSUFFICIENT_FUNDS"
                      message: "Insufficient funds in source account EST12345"
                      details:
                        sourceAccount: "EST12345"
                        availableBalance: "50.00"
                        requestedAmount: "100.00"
                  bankPrefixMismatch:
                    summary: Bank prefix validation failed
                    value:
                      code: "INVALID_BANK_PREFIX"
                      message: "Bank prefix 'XXX' is not registered"
          '503':
            description: Service temporarily unavailable
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  unavailable:
                    summary: Central bank unreachable for rates
                    value:
                      code: "SERVICE_UNAVAILABLE"
                      message: "Central bank exchange rate service is temporarily unavailable"
    ```

    3. **Add path: POST /transfers/receive**
    ```yaml
    /transfers/receive:
      post:
        tags: [Transfers]
        summary: Receive cross-bank transfer from another bank
        description: |
          Receives and processes a cross-bank transfer initiated by another bank.
          
          **Purpose (D-02):**
          This endpoint is called by source banks to deliver cross-bank transfers to
          the destination bank. It is distinct from user-initiated transfers and is
          authenticated via JWT signed by the source bank.
          
          **Authentication Flow (D-04):**
          1. Source bank signs transfer request payload with its private key
          2. Destination bank retrieves source bank's public key from central bank registry (cached allowed)
          3. Destination bank verifies JWT signature
          4. Destination bank validates `aud` matches its own bank ID
          5. Destination bank validates `iat` and `exp` timestamps (within allowed window)
          6. Destination bank extracts transfer details from JWT payload and credits destination account
          
          **JWT Payload Structure (DEC-05):**
          The HTTP request body contains only the JWT string. All transfer details are
          in the JWT payload for cryptographic integrity. JWT must include:
          - Standard claims: iss (source bank ID), sub (transferId), aud (destination bank ID), iat, exp
          - Custom claims: transferId, sourceAccount, destinationAccount, amount, convertedAmount?,
            sourceCurrency, destinationCurrency, exchangeRate?, rateCapturedAt?,
            sourceBankId, destinationBankId, timestamp, nonce
          
          **JWT Algorithm (DEC-03):**
          Use ES256 (ECDSA with P-256) as specified in JWT header:
          ```json
          {
            "alg": "ES256",
            "typ": "JWT"
          }
          ```
          
          **Error Handling:**
          - Invalid JWT signature: 401 Unauthorized with INVALID_JWT_SIGNATURE code
          - Invalid audience: 403 Forbidden with INVALID_AUDIENCE code
          - Expired JWT: 401 Unauthorized with EXPIRED_JWT code
          - Destination account not found: 404 Not Found
          - Invalid bank prefix: 400 Bad Request
        operationId: receiveInterBankTransfer
        security: []
        requestBody:
          required: true
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/InterBankTransferRequest'
              examples:
                interBankTransfer:
                  summary: Cross-bank transfer JWT
                  value:
                    jwt: "eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0cmFuc2ZlcklkIjoiNTUwZTg0MDAtZTI5Yi00MWQ0LWE3MTYtNDQ2NjU1NDQwMDAwIiwic291cmNlQWNjb3VudCI6IkVTVDEyMzQ1IiwiZGVzdGluYXRpb25BY2NvdW50IjoiTEFUNTU0MzIxIiwiYW1vdW50IjoiMTAwLjAwIiwic291cmNlQmFua0lkIjoiRVNUMDEiLCJkZXN0aW5hdGlvbkJhbmtJZCI6IkxBVDAwMiIsInRpbWVzdGFtcCI6IjIwMjYtMDQtMDhUMTI6MDA6MDBaIiwibm9uY2UiOiJhMWIyYzNkNGU1ZjZnN2g4In0.signature"
        responses:
          '200':
            description: Transfer received and processed successfully
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/InterBankTransferResponse'
                examples:
                  transferAccepted:
                    summary: Cross-bank transfer accepted
                    value:
                      transferId: "550e8400-e29b-41d4-a716-446655440000"
                      status: "completed"
                      destinationAccount: "LAT54321"
                      amount: "100.00"
                      timestamp: "2026-04-08T12:00:15Z"
          '400':
            description: Invalid request
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  invalidJwt:
                    summary: Invalid JWT format
                    value:
                      code: "INVALID_JWT"
                      message: "JWT token is malformed or invalid"
          '401':
            description: Unauthorized
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  invalidSignature:
                    summary: Invalid JWT signature
                    value:
                      code: "INVALID_JWT_SIGNATURE"
                      message: "JWT signature verification failed"
                  expiredJwt:
                    summary: JWT expired
                    value:
                      code: "EXPIRED_JWT"
                      message: "JWT has expired"
          '403':
            description: Forbidden
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  invalidAudience:
                    summary: Invalid audience
                    value:
                      code: "INVALID_AUDIENCE"
                      message: "JWT audience does not match this bank ID"
          '404':
            description: Account not found
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  destinationAccountNotFound:
                    summary: Destination account does not exist
                    value:
                      code: "DESTINATION_ACCOUNT_NOT_FOUND"
                      message: "Destination account 'LAT54321' not found"
    ```

    4. **Add path: GET /transfers/{transferId} (DEC-04)**
    ```yaml
    /transfers/{transferId}:
      get:
        tags: [Transfers]
        summary: Get transfer status by ID
        description: |
          Retrieves the current status and details of a transfer.
          
          **Purpose (DEC-04):**
          This endpoint allows clients to query transfer status for debugging and
          verification. In Phase 2, transfers are either completed or failed.
          Phase 3 will extend this with pending status handling.
          
          **Status Values:**
          - `completed`: Transfer succeeded (funds debited from source, credited to destination)
          - `failed`: Transfer failed (e.g., insufficient funds, account not found)
          - `pending`: Transfer is pending (Phase 3 feature - not used in Phase 2)
          
          **Authorization:**
          Only the transfer initiator should be able to query transfer status.
          Authentication is required via Bearer token.
        operationId: getTransferStatus
        security:
          - BearerAuth: []
        parameters:
          - name: transferId
            in: path
            required: true
            description: The unique identifier of the transfer
            schema:
              type: string
              format: uuid
              example: "550e8400-e29b-41d4-a716-446655440000"
        responses:
          '200':
            description: Transfer status retrieved successfully
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/TransferStatusResponse'
                examples:
                  completedTransfer:
                    summary: Transfer completed
                    value:
                      transferId: "550e8400-e29b-41d4-a716-446655440000"
                      status: "completed"
                      sourceAccount: "EST12345"
                      destinationAccount: "LAT54321"
                      amount: "100.00"
                      timestamp: "2026-04-08T12:00:15Z"
                  failedTransfer:
                    summary: Transfer failed
                    value:
                      transferId: "880e3944-j64f-85h8-e261-001122334455"
                      status: "failed"
                      sourceAccount: "EST11111"
                      destinationAccount: "LAT22222"
                      amount: "1000.00"
                      timestamp: "2026-04-08T12:01:00Z"
                      errorMessage: "Insufficient funds in source account"
          '401':
            description: Unauthorized
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  unauthorized:
                    summary: Authentication required
                    value:
                      code: "UNAUTHORIZED"
                      message: "Authentication is required to query transfer status"
          '404':
            description: Transfer not found
            content:
              application/json:
                schema:
                  $ref: '#/components/schemas/Error'
                examples:
                  transferNotFound:
                    summary: Transfer does not exist
                    value:
                      code: "TRANSFER_NOT_FOUND"
                      message: "Transfer with ID '550e8400-e29b-41d4-a716-446655440000' not found"
    ```

    5. **Add transfer schemas to components/schemas:**

    ```yaml
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
            
            **Idempotency (D-05):**
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
            
            **Routing Logic (D-01):**
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
            
            **Precision (Phase 1 Pattern 4):**
            Stored as a string to avoid floating-point precision issues.
          pattern: '^\d+\.\d{2}$'
          example: "100.00"
    
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
            
            **Currency Conversion (XFER-04):**
            Only present when source and destination accounts use different currencies.
            
            **Rate Application:**
            convertedAmount = amount * exchangeRate (rounded to 2 decimal places)
            
            **Phase 2 Note:**
            For same-bank transfers, source and destination currency are always the same,
            so this field is typically omitted (or equals amount).
          pattern: '^\d+\.\d{2}$'
          example: "85.00"
        exchangeRate:
          type: string
          format: decimal
          description: |
            The exchange rate used for currency conversion (if applicable).
            
            **Format:**
            Rate from source currency to destination currency.
            
            **Example:**
            If source is EUR and destination is GBP, a rate of "0.85" means 1 EUR = 0.85 GBP.
            
            **Capture Time (D-03):**
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
          example: "Insufficient funds in source account EST12345"
    
    InterBankTransferRequest:
      type: object
      required:
        - jwt
      properties:
        jwt:
          type: string
          description: |
            JWT token signed by the source bank's private key containing all transfer details.
            
            **Verification Flow (D-04):**
            1. Destination bank extracts `iss` (source bank ID) from JWT header/payload
            2. Destination bank retrieves source bank's public key from central bank registry
            3. Destination bank verifies JWT signature using the public key
            4. Destination bank validates `aud` matches its own bank ID
            5. Destination bank validates `iat` and `exp` timestamps (within allowed window)
            6. Destination bank extracts transfer details from payload and processes transfer
            
            **Error Handling:**
            - Invalid signature: 401 Unauthorized
            - Invalid audience: 403 Forbidden
            - Expired JWT: 401 Unauthorized
            - Invalid payload: 400 Bad Request
            
            **Implementation Note (DEC-05):**
            The actual transfer data is in the JWT payload, but this request wrapper
            allows the JWT to be transparently passed as a single string.
          example: "eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0cmFuc2ZlcklkIjoiNTUwZTg0MDAtZTI5Yi00MWQ0LWE3MTYtNDQ2NjU1NDQwMDAwIiwic291cmNlQWNjb3VudCI6IkVTVDEyMzQ1IiwiZGVzdGluYXRpb25BY2NvdW50IjoiTEFUNTU0MzIxIiwiYW1vdW50IjoiMTAwLjAwIiwic291cmNlQmFua0lkIjoiRVNUMDEiLCJkZXN0aW5hdGlvbkJhbmtJZCI6IkxBVDAwMiIsInRpbWVzdGFtcCI6IjIwMjYtMDQtMDhUMTI6MDA6MDBaIiwibm9uY2UiOiJhMWIyYzNkNGU1ZjZnN2g4In0.signature"
    
    InterBankTransferResponse:
      type: object
      required:
        - transferId
        - status
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
          enum: [completed, failed]
          description: The status of the received transfer
          example: "completed"
        destinationAccount:
          type: string
          description: The account funds were credited to
          pattern: '^[A-Z0-9]{8}$'
          example: "LAT54321"
        amount:
          type: string
          format: decimal
          description: The amount credited (in destination currency)
          pattern: '^\d+\.\d{2}$'
          example: "100.00"
        timestamp:
          type: string
          format: date-time
          description: The timestamp when the transfer was processed
          example: "2026-04-08T12:00:15Z"
    
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
          description: Source account number
          example: "EST12345"
        destinationAccount:
          type: string
          pattern: '^[A-Z0-9]{8}$'
          description: Destination account number
          example: "LAT54321"
        amount:
          type: string
          format: decimal
          description: Amount transferred (in source currency)
          example: "100.00"
        convertedAmount:
          type: string
          format: decimal
          description: Present only for cross-currency transfers
          pattern: '^\d+\.\d{2}$'
          example: "85.00"
        exchangeRate:
          type: string
          format: decimal
          description: Present only for cross-currency transfers
          pattern: '^\d+\.\d{6}$'
          example: "0.850000"
        rateCapturedAt:
          type: string
          format: date-time
          description: Present only when exchangeRate is present
          example: "2026-04-08T12:00:00Z"
        timestamp:
          type: string
          format: date-time
          description: Timestamp of the transfer completion or failure
          example: "2026-04-08T12:00:15Z"
        errorMessage:
          type: string
          description: Present only when status = "failed"
          example: "Insufficient funds in source account EST12345"
    
    # Extend existing Error schema to include details (optional, from Phase 1)
    # Error schema already exists; reference it for consistency
    ```

    6. **Update info description** to mention transfer endpoints

    **Validation Requirements:**
    - Follow Phase 1 patterns for schema organization
    - Use decimal strings for all monetary amounts (pattern `^\d+\.\d{2}$`)
    - Account numbers use pattern `^[A-Z0-9]{8}$`
    - Currency codes use pattern `^[A-Z]{3}$`
    - transferId uses format: uuid
    - Timestamps use format: date-time (server-side)
    - Include comprehensive examples for all request/response bodies
    - Reference locked decisions D-01 through D-05 in descriptions
    - Reference DEC-01 through DEC-05 implementation notes

    **Error Cases to Cover:**
    - 400: Invalid request (malformed data, invalid patterns)
    - 401: Unauthorized (missing/invalid authentication, invalid JWT signature, expired JWT)
    - 403: Forbidden (invalid JWT audience)
    - 404: Not found (source account, destination account, bank prefix, transfer ID)
    - 409: Conflict (duplicate transferId)
    - 422: Unprocessable entity (insufficient funds, bank prefix mismatch)
    - 500/503: Server errors
  </action>
  <verify>
    <automated>spectral lint openapi/branch-bank.yaml --format stylish</automated>
  </verify>
  <done>
    - `POST /transfers` endpoint added with single-surface API design (D-01)
    - `POST /transfers/receive` endpoint added for inter-bank transfers (D-02)
    - `GET /transfers/{transferId}` endpoint added for status queries (DEC-04)
    - TransferRequest schema with transferId for idempotency (D-05)
    - TransferResponse schema with currency conversion fields (XFER-04)
    - InterBankTransferRequest schema with JWT wrapper (DEC-05)
    - TransferStatusResponse schema for status queries
    - All schemas follow Phase 1 patterns (decimal strings, pattern validation)
    - Comprehensive examples for all request/response bodies
    - Error cases include 400, 401, 403, 404, 409, 422, 500, 503
    - Spectral lint passes without errors
    - References to locked decisions D-01 through D-05 documented
    - References to DEC-01 through DEC-05 documented
  </done>
</task>

</tasks>

<verification>
After completing all tasks, verify:

1. **Spectral Validation:** Run `spectral lint openapi/central-bank.yaml openapi/branch-bank.yaml --fail-on-warn` and ensure zero errors
2. **Schema Completeness:** All transfer schemas include required fields and proper validation patterns
3. **Requirement Coverage:** Each of XFER-01 through XFER-04 is addressed in endpoint descriptions
4. **Decision References:** All locked decisions (D-01 through D-05) are referenced in descriptions
5. **Example Coverage:** All endpoints have comprehensive examples covering happy paths and error cases
6. **Pattern Consistency:** All schemas follow Phase 1 patterns (decimal strings, server-side timestamps, pattern validation)

**Automated Verification Command:**
```bash
spectral lint openapi/*.yaml --fail-on-warn
```
</verification>

<success_criteria>
1. Central bank contract includes `GET /exchange-rates` endpoint with ExchangeRatesResponse schema
2. Branch bank contract includes three transfer endpoints:
   - `POST /transfers` (user-initiated, single-surface API with internal routing)
   - `POST /transfers/receive` (inter-bank, JWT-authenticated)
   - `GET /transfers/{transferId}` (status lookup)
3. TransferRequest schema requires transferId, sourceAccount, destinationAccount, amount
4. TransferResponse schema includes currency conversion fields (convertedAmount, exchangeRate, rateCapturedAt)
5. All monetary amounts use decimal strings with `^\d+\.\d{2}$` pattern
6. Account numbers use `^[A-Z0-9]{8}$` pattern (consistent with Phase 1)
7. Currency codes use `^[A-Z]{3}$` pattern (ISO 4217)
8. transferId uses UUID format for idempotency
9. All timestamps use date-time format with server-side semantics
10. Spectral lint passes with zero errors on both contract files
11. All requirements XFER-01 through XFER-04 are satisfied
12. All locked decisions (D-01 through D-05) are implemented
13. All decision points (DEC-01 through DEC-05) are resolved in contract
</success_criteria>

<output>
After completion, create `.planning/phases/02-same-bank-and-cross-bank-transfers/02-same-bank-and-cross-bank-transfers-01-SUMMARY.md`

The summary should document:
- All schemas added with their field names and patterns
- All endpoints added with their routing logic and error cases
- How XFER-01 through XFER-04 are satisfied
- How locked decisions (D-01 through D-05) are implemented
- How decision points (DEC-01 through DEC-05) are resolved
- Spectral validation results
- Any deviations from the plan
</output>