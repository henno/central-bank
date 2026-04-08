---
phase: 03-resilient-delivery
plan: 01
type: execute
wave: 1
depends_on: []
files_modified: [openapi/central-bank.yaml]
autonomous: true
requirements: [XFER-05]
user_setup: []

must_haves:
  truths:
    - "Central bank can provide lastSyncedAt timestamp for cache freshness tracking"
    - "Bank directory response includes timestamp indicating when data was last refreshed"
    - "Cached directory data can be used during central bank outages"
  artifacts:
    - path: "openapi/central-bank.yaml"
      provides: "GET /banks endpoint with lastSyncedAt timestamp"
      min_lines: 50
      contains: "BankDirectory schema with lastSyncedAt property"
  key_links:
    - from: "GET /banks response"
      to: "BankDirectory.lastSyncedAt"
      via: "schema reference"
      pattern: "lastSyncedAt.*timestamp"
---

<objective>
Add cache metadata to central bank directory for resilient operation during outages

Purpose: Enable branch banks to cache the bank directory locally and track cache freshness using a server-provided timestamp (per CONTEXT.md XFER-05 decision)

Output: Updated central-bank.yaml with lastSyncedAt field in BankDirectory schema
</objective>

<execution_context>
@$HOME/.config/opencode/get-shit-done/workflows/execute-plan.md
@$HOME/.config/opencode/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/03-resilient-delivery/03-CONTEXT.md
@openapi/central-bank.yaml
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add lastSyncedAt field to BankDirectory schema and endpoint responses</name>
  <files>openapi/central-bank.yaml</files>
  <action>
    Update openapi/central-bank.yaml:

    1. Modify BankDirectory schema (around line 378) to add lastSyncedAt property after the banks array:
       ```yaml
       lastSyncedAt:
         type: string
         format: date-time
         description: |
           The timestamp when the bank directory was last refreshed.

           **Cache Freshness Tracking (XFER-05):**
           Branch banks cache the full directory locally and use this timestamp
           to track cache freshness. When the central bank is unavailable,
           branch banks can continue routing transfers using cached data.

           **Caching Strategy:**
           Banks download the full directory on startup and periodically refresh.
           This timestamp indicates when the directory was last updated from
           the authoritative source.
         example: "2026-04-08T12:00:00Z"
       ```
       Make lastSyncedAt required (move it to required array after banks).

    2. Add lastSyncedAt to the example in GET /banks response (around line 119):
       ```yaml
       value:
         banks:
           - bankId: "EST001"
             name: "Estonia Commercial Bank"
             address: "https://ecb.banking.example:8443"
             publicKey: "MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEEVs/o5+UW6c1x0O5JqZ2LxZ9m7Y3X5Z1C9xK9Y8Z7vL9Z1A=="
             lastHeartbeat: "2026-04-02T11:10:00Z"
             status: "active"
           - bankId: "LAT002"
             name: "Latvia Savings Bank"
             address: "https://lsb.banking.example:8443"
             publicKey: "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzK9Y8Z7vL9Z1C9xK9Y8Z7vL9Z1A=="
             lastHeartbeat: "2026-04-02T11:12:00Z"
             status: "active"
         lastSyncedAt: "2026-04-08T12:00:00Z"
       ```

    3. Update the description of GET /banks endpoint (around line 103) to mention caching:
       Add to description: "Includes a lastSyncedAt timestamp for cache freshness tracking, enabling branch banks to operate with cached directory data when the central bank is temporarily unavailable (XFER-05)."

    This implements the central bank caching strategy from CONTEXT.md where banks cache the directory locally and track freshness via lastSyncedAt.
  </action>
  <verify>
    <automated>swagger-cli validate openapi/central-bank.yaml</automated>
  </verify>
  <done>
    BankDirectory schema includes lastSyncedAt as required field with proper description, GET /banks response example includes lastSyncedAt, validation passes
  </done>
</task>

</tasks>

<verification>
Run swagger-cli validation on both API contracts after this plan to ensure schema validity.
</verification>

<success_criteria>
1. GET /banks endpoint returns lastSyncedAt timestamp in all responses
2. BankDirectory schema documents the caching strategy for unavailability scenarios
3. Example response demonstrates the correct format for cache timestamp tracking
4. Validation passes without errors
</success_criteria>

<output>
After completion, create `.planning/phases/03-resilient-delivery/03-resilient-delivery-01-SUMMARY.md`
</output>