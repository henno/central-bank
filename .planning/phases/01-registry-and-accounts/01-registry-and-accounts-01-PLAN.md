---
phase: 01-registry-and-accounts
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - openapi/central-bank.yaml
autonomous: true
requirements:
  - REG-01
  - REG-02
  - REG-03
  - HRTB-01
  - HRTB-02
must_haves:
  truths:
    - "Banks can register with the central bank"
    - "The central bank exposes the current registered-bank directory"
    - "The central bank removes banks that stop heartbeating for 30 minutes"
    - "Branch banks can send heartbeats to refresh registry freshness"
  artifacts:
    - path: openapi/central-bank.yaml
      provides: "OpenAPI 3.1 contract for registry and heartbeat endpoints"
      contains: "paths, components, and examples"
  key_links:
    - from: "POST /banks"
      to: "BankRegistrationRequest"
      via: "request body"
      pattern: "post:\\s*\\n\\s*/banks"
    - from: "POST /banks/{bankId}/heartbeat"
      to: "registry freshness"
      via: "heartbeat timestamp update"
      pattern: "heartbeat"
---

<objective>
Define the central-bank contract for registry and heartbeat lifecycles.

Purpose: The central bank is the discovery anchor for all branch banks, so the registry contract must be precise enough for independently built services to interoperate.
Output: `openapi/central-bank.yaml` documenting registration, listing, heartbeats, and stale-bank pruning behavior.
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
- Keep prose implementation-agnostic; do not assume any framework or runtime.
- Make the 30-minute stale-bank timeout explicit.
- Include examples because the contract must be detailed enough for independent implementation.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 0: Define the central-bank contract skeleton</name>
  <files>openapi/central-bank.yaml</files>
  <action>Create the OpenAPI 3.1 document for bank registry and heartbeat flows. Establish info, servers, reusable components, and schema definitions for bank registration, directory entries, heartbeats, and errors. Keep the spec implementation-agnostic and aligned with the contract-first direction from research.</action>
  <verify><automated>npx --yes @redocly/cli lint openapi/central-bank.yaml</automated></verify>
  <done>The spec lints cleanly and contains the central-bank contract skeleton needed by REG-01, REG-02, REG-03, HRTB-01, and HRTB-02.</done>
</task>

<task type="auto">
  <name>Task 1: Document registry lifecycle semantics</name>
  <files>openapi/central-bank.yaml</files>
  <action>Fill in POST /banks, GET /banks, and POST /banks/{bankId}/heartbeat with precise request/response bodies, examples, and 30-minute stale-bank pruning semantics. Make the registry behavior explicit enough for independent implementation and avoid framework-specific language.</action>
  <verify><automated>npx --yes @redocly/cli lint openapi/central-bank.yaml</automated></verify>
  <done>Registry registration, listing, heartbeat, and pruning behavior are documented without ambiguity.</done>
</task>

</tasks>

<verification>
Run OpenAPI linting on the central-bank spec and confirm the contract exposes the registry, heartbeat, and pruning semantics required by the phase.
</verification>

<success_criteria>
- The central-bank OpenAPI document is valid and lint-clean.
- Banks can be registered, listed, and heartbeated through the contract.
- The 30-minute stale-bank pruning rule is explicit.
</success_criteria>

<output>
After completion, create `.planning/phases/01-registry-and-accounts/01-registry-and-accounts-SUMMARY.md`
</output>
