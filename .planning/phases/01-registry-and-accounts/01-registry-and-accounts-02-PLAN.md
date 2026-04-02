---
phase: 01-registry-and-accounts
plan: 02
type: execute
wave: 1
depends_on: []
files_modified:
  - openapi/branch-bank.yaml
autonomous: true
requirements:
  - ACCT-01
  - ACCT-02
  - ACCT-03
  - ACCT-04
must_haves:
  truths:
    - "Users can register at a branch bank"
    - "Users can create an account with a chosen currency"
    - "Account numbers are exactly 8 characters and include the bank prefix"
    - "Unauthenticated account lookup returns the owner name or 404"
  artifacts:
    - path: openapi/branch-bank.yaml
      provides: "OpenAPI 3.1 contract for user, account, and lookup endpoints"
      contains: "paths, components, and examples"
  key_links:
    - from: "Account number format"
      to: "lookup endpoint"
      via: "path parameter validation"
      pattern: "accountNumber"
    - from: "GET /accounts/{accountNumber}"
      to: "owner-name-or-404 response"
      via: "unauthenticated lookup semantics"
      pattern: "404|owner"
---

<objective>
Define the branch-bank contract for user registration, account creation, and unauthenticated account lookup.

Purpose: Branch banks are the user-facing contract surface for account lifecycle operations, so the branch-bank spec must make ownership and account-number rules unambiguous.
Output: `openapi/branch-bank.yaml` documenting user registration, account creation, lookup, and account-number constraints.
</objective>

<execution_context>
@$HOME/.config/opencode/get-shit-done/workflows/execute-plan.md
@$HOME/.config/opencode/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/REQUIREMENTS.md
@.planning/research/SUMMARY.md
@.planning/research/STACK.md
@.planning/research/ARCHITECTURE.md
@.planning/research/FEATURES.md
@.planning/research/PITFALLS.md

<interfaces>
Use these contract constraints directly in the spec:

- OpenAPI 3.1 is the source of truth.
- Use JSON Schema-compatible components for request/response bodies.
- Document account-number length and bank-prefix semantics explicitly.
- Include unauthenticated lookup responses with owner-name and 404 behavior.
- Include examples because the contract must be detailed enough for independent implementation.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 0: Define the branch-bank contract skeleton</name>
  <files>openapi/branch-bank.yaml</files>
  <action>Create the OpenAPI 3.1 document for user registration and account lifecycle flows. Establish info, servers, reusable components, and schema definitions for users, account creation, account records, lookup results, and errors. Keep the spec implementation-agnostic and aligned with the contract-first direction from research.</action>
  <verify><automated>npx --yes @redocly/cli lint openapi/branch-bank.yaml</automated></verify>
  <done>The spec lints cleanly and contains the branch-bank contract skeleton needed by ACCT-01, ACCT-02, ACCT-03, and ACCT-04.</done>
</task>

<task type="auto">
  <name>Task 1: Document account number and lookup semantics</name>
  <files>openapi/branch-bank.yaml</files>
  <action>Fill in POST /users, POST /users/{userId}/accounts, and GET /accounts/{accountNumber} with precise request/response bodies, examples, and 8-character account-number rules. Make it explicit that unauthenticated lookup returns the owner's name when present and 404 when missing.</action>
  <verify><automated>npx --yes @redocly/cli lint openapi/branch-bank.yaml</automated></verify>
  <done>User registration, account creation, account-number constraints, and lookup behavior are documented without ambiguity.</done>
</task>

</tasks>

<verification>
Run OpenAPI linting on the branch-bank spec and confirm the contract exposes user registration, account creation, lookup, and account-number semantics required by the phase.
</verification>

<success_criteria>
- The branch-bank OpenAPI document is valid and lint-clean.
- Users can be registered and accounts can be created with a chosen currency.
- Account numbering and unauthenticated lookup behavior are explicit.
</success_criteria>

<output>
After completion, create `.planning/phases/01-registry-and-accounts/01-registry-and-accounts-SUMMARY.md`
</output>
