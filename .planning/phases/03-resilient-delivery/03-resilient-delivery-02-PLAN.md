---
phase: 03-resilient-delivery
plan: 02
type: execute
wave: 2
depends_on: [03-resilient-delivery-01]
files_modified: [openapi/branch-bank.yaml]
autonomous: true
requirements: [XFER-06, XFER-07]
user_setup: []

must_haves:
  truths:
    - "Transfer status enum includes 'failed_timeout' for expired pending transfers"
    - "New error codes define unavailable service scenarios and timeout handling"
    - "Transfer responses can include diagnostic fields for pending transfers"
  artifacts:
    - path: "openapi/branch-bank.yaml"
      provides: "Expanded transfer status enum and new error response codes"
      min_lines: 100
      contains: "failed_timeout status, new error codes in examples"
  key_links:
    - from: "POST /transfers responses"
      to: "Error schema with new codes"
      via: "error examples"
      pattern: "(DESTINATION_BANK_UNAVAILABLE|CENTRAL_BANK_UNAVAILABLE|TRANSFER_TIMEOUT|TRANSFER_ALREADY_PENDING)"
    - from: "TransferStatusResponse/TransferResponse"
      to: "status enum"
      via: "schema definition"
      pattern: "failed_timeout"
---

<objective>
Add pending transfer status, timeout handling, and new error codes to branch bank contract

Purpose: Implement XFER-06 (pending transfers when destination unavailable) and XFER-07 (automatic timeout with refund) by expanding transfer status enums and adding new error codes per CONTEXT.md decisions

Output: Updated branch-bank.yaml with failed_timeout status, new error codes, and pending transfer diagnostics
</objective>

<execution_context>
@$HOME/.config/opencode/get-shit-done/workflows/execute-plan.md
@$HOME/.config/opencode/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/03-resilient-delivery/03-CONTEXT.md
@openapi/branch-bank.yaml
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add failed_timeout status to transfer status enums</name>
  <files>openapi/branch-bank.yaml</files>
  <action>
    Update openapi/branch-bank.yaml to expand transfer status enum with failed_timeout:

    1. TransferResponse schema status enum (around line 787):
       Change from: `enum: [completed, failed, pending]`
       To: `enum: [completed, failed, pending, failed_timeout]`
       Update description to:
       ```
       completed: Transfer succeeded
       failed: Transfer failed permanently
       pending: Transfer is being retried (destination bank unavailable)
       failed_timeout: Transfer failed due to timeout expiration with automatic refund
       ```

    2. TransferStatusResponse schema status enum (around line 892):
       Change from: `enum: [completed, failed, pending]`
       To: `enum: [completed, failed, pending, failed_timeout]`
       Update description to match TransferResponse.

    3. Add a pending response example to GET /transfers/{transferId} (around line 516):
       Add new example before existing examples:
       ```yaml
       pendingTransfer:
         summary: Transfer pending (destination bank unavailable)
         value:
           transferId: "770e4033-j50e-64i7-f370-001122334455"
           status: "pending"
           sourceAccount: "EST12345"
           destinationAccount: "LAT22222"
           amount: "500.00"
           pendingSince: "2026-04-08T14:00:00Z"
           nextRetryAt: "2026-04-08T14:01:00Z"
           retryCount: 1
           timestamp: "2026-04-08T14:00:05Z"
       ```

    4. Add a timeout-failed response example to GET /transfers/{transferId} (after failedTransfer example):
       ```yaml
       timeoutFailedTransfer:
         summary: Transfer failed due to timeout
         value:
           transferId: "880e3944-j64f-85h8-e261-001122334455"
           status: "failed_timeout"
           sourceAccount: "EST11111"
           destinationAccount: "LAT22222"
           amount: "1000.00"
           timestamp: "2026-04-08T18:00:00Z"
           errorMessage: "Transfer timed out after 4 hours. Funds refunded to source account."
       ```

    Note: pendingSince, nextRetryAt, retryCount fields will be added in Task 2 as optional diagnostic fields.
  </action>
  <verify>
    <automated>swagger-cli validate openapi/branch-bank.yaml</automated>
  </verify>
  <done>
    Transfer status enums include failed_timeout, examples demonstrate pending and timeout scenarios
  </done>
</task>

<task type="auto">
  <name>Task 2: Add diagnostic fields for pending transfers and new error codes</name>
  <files>openapi/branch-bank.yaml</files>
  <action>
    Update openapi/branch-bank.yaml with pending transfer diagnostics and error codes:

    1. Add optional diagnostic fields to TransferStatusResponse schema (after timestamp property, around line 927):
       ```yaml
       pendingSince:
         type: string
         format: date-time
         description: |
           Present only when status = pending. Indicates when the transfer was first marked as pending.
           Used to calculate timeout expiration (4-hour window).
         example: "2026-04-08T14:00:00Z"
       nextRetryAt:
         type: string
         format: date-time
         description: |
           Present only when status = pending. Indicates when the next retry attempt is scheduled.
           Follows exponential backoff schedule (1m → 2m → 4m → ... → 1h).
         example: "2026-04-08T14:01:00Z"
       retryCount:
         type: integer
         minimum: 0
         description: |
           Present only when status = pending. Number of retry attempts made so far.
         example: 1
       ```

    2. Add new error response examples to POST /transfers (around line 409):

       After the duplicateTransfer example, add:
       ```yaml
       transferAlreadyPending:
         summary: Transfer already pending
         value:
           code: "TRANSFER_ALREADY_PENDING"
           message: "Transfer with ID '550e8400-e29b-41d4-a716-446655440000' is already pending. Cannot submit duplicate transfer."
       ```

       Replace the generic 503 error example (around line 436) with:
       ```yaml
       destinationBankUnavailable:
         summary: Destination bank unavailable
         value:
           code: "DESTINATION_BANK_UNAVAILABLE"
           message: "Destination bank is temporarily unavailable. Transfer has been queued for retry."
       centralBankUnavailable:
         summary: Central bank unavailable
         value:
           code: "CENTRAL_BANK_UNAVAILABLE"
           message: "Central bank is temporarily unavailable. Using cached directory data for routing."
       ```

    3. Add timeout error example to GET /transfers/{transferId} 423 response (add new response section after 404):
       ```yaml
       '423':
         description: Transfer is locked
         content:
           application/json:
             schema:
               $ref: '#/components/schemas/Error'
             examples:
               transferTimeout:
                 summary: Transfer timed out
                 value:
                   code: "TRANSFER_TIMEOUT"
                   message: "Transfer has timed out and cannot be modified or retried. Status is failed_timeout with refund processed."
       ```

    This implements XFER-06 retry strategy (pending status with diagnostics) and XFER-07 timeout handling (failed_timeout status with 423 error for modification attempts).
  </action>
  <verify>
    <automated>swagger-cli validate openapi/branch-bank.yaml</automated>
  </verify>
  <done>
    Pending transfers include diagnostic fields, all new error codes documented with examples, validation passes
  </done>
</task>

<task type="auto">
  <name>Task 3: Update POST /transfers documentation to describe resilient behavior</name>
  <files>openapi/branch-bank.yaml</files>
  <action>
    Update the description of POST /transfers endpoint (around line 332) to document resilient delivery behavior:

    Replace current description with:
    ```
    Initiates a fund transfer from a source account to a destination account.

    **Routing (D-01):**
    Branch bank determines routing by inspecting destination account's bank prefix (first 3 characters).
    Same-bank transfers execute directly; cross-bank transfers route via POST /transfers/receive.

    **Idempotency (D-05):**
    Each transfer requires a unique transferId (UUID format). Duplicate transferId values are rejected.

    **Currency Conversion (XFER-04):**
    Cross-bank transfers convert currency at current exchange rate from central bank (D-03).

    **Cross-Bank Authentication (D-04):**
    Inter-bank transfers use JWT signed with source bank's private key (ES256 algorithm).

    **Resilient Delivery (Phase 3):**

    **Pending Transfers (XFER-06):**
    When destination bank is unavailable during cross-bank transfer:
    - Status is set to "pending" with immediate fund deduction from source account
    - Transfer is queued with exponential backoff retry (1m → 2m → 4m → ... → 1h)
    - Funds remain locked until transfer succeeds, fails permanently, or times out
    - Duplicate transferId submission returns 409: TRANSFER_ALREADY_PENDING

    **Central Bank Unavailability (XFER-05):**
    When central bank directory service is unavailable:
    - Branch banks use cached bank directory data for routing
    - Returns 503: CENTRAL_BANK_UNAVAILABLE (transfer proceeds using cache)

    **Timeout Handling (XFER-07):**
    Pending transfers expire after 4 hours:
    - Status changes to "failed_timeout"
    - Funds are automatically refunded to source account
    - Modification attempts return 423: TRANSFER_TIMEOUT
    ```
    This updates the endpoint description to comprehensively document all resilient delivery behaviors including pending transfers, retry strategy, and timeout handling.
  </action>
  <verify>
    <automated>swagger-cli validate openapi/branch-bank.yaml</automated>
  </verify>
  <done>
    POST /transfers description documents all resilient delivery behaviors, validation passes
  </done>
</task>

</tasks>

<verification>
Run swagger-cli validation on both API contracts after this plan to ensure schema validity.
Also run Spectral validation: spectral lint openapi/branch-bank.yaml
</verification>

<success_criteria>
1. Transfer status enums include failed_timeout for expired pending transfers
2. New error codes defined: DESTINATION_BANK_UNAVAILABLE, CENTRAL_BANK_UNAVAILABLE, TRANSFER_TIMEOUT, TRANSFER_ALREADY_PENDING
3. Pending transfer responses include optional diagnostic fields (pendingSince, nextRetryAt, retryCount)
4. All error codes have examples demonstrating when they occur
5. POST /transfers description documents complete resilient delivery behavior
6. Validation passes without errors
</success_criteria>

<output>
After completion, create `.planning/phases/03-resilient-delivery/03-resilient-delivery-02-SUMMARY.md`
</output>