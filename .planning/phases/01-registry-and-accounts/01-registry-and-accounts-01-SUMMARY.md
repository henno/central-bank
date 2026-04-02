---
plan: 01-registry-and-accounts-01
phase: 01
completed: 2026-04-02T14:16:00+00:00
type: execute
depends_on: []
requirements_completed:
  - REG-01
  - REG-02
  - REG-03
  - HRTB-01
  - HRTB-02
---

# Summary: Central-Bank Registry and Heartbeat Contract

## What Was Built

Created the OpenAPI 3.1 contract for the central bank registry and heartbeat lifecycle, serving as the discovery anchor for all branch banks in the distributed banking system.

## Key Files Created

- `openapi/central-bank.yaml` - Complete OpenAPI 3.1 specification
  - POST /banks - Bank registration endpoint
  - GET /banks - Directory list endpoint (returns only active banks)
  - POST /banks/{bankId}/heartbeat - Heartbeat maintenance endpoint
  - Complete schema definitions for all request/response bodies
  - Comprehensive examples for registration, listing, and heartbeat flows

## Requirements Satisfied

- **REG-01**: Central bank can register a bank with name, address, and public key
- **REG-02**: Central bank can return the list of currently registered banks
- **REG-03**: Central bank removes stale banks (no heartbeat within 30 minutes)
- **HRTB-01**: Branch banks can send heartbeats to central bank
- **HRTB-02**: Central bank maintains fresh registry state via heartbeats

## Technical Decisions

### 30-Minute Timeout Rule
- The timeout is **exact** (no grace period) — expiredAt = lastHeartbeat + 30 minutes
- Stale banks are discovered on-demand during directory operations
- Stale banks return 410 Gone (permanent removal); unknown banks return 404

### Registry Consistency
- GET /banks returns only active banks (expiresAt > current time)
- Pruning happens during read operations, not as a background job
- Heartbeats are idempotent — multiple heartbeats don't cause errors

### Implementation Agnosticism
- Contract uses standard OpenAPI 3.1 with JSON Schema components
- No framework-specific constraints or implementation hints
- All necessary examples provided for independent implementation

## Verification

All contract requirements are explicit and implementation-ready:
- Registation flow: POST /banks → bankId + initial expiresAt
- Heartbeat flow: POST /banks/{bankId}/heartbeat → new expiresAt
- Directory consistency: Active only, stale removed automatically
- Error handling: 404 unknown, 410 gone, 400/422 validation errors

## Integration Notes

This contract is the **source of truth** for:
1. Branch bank registration and discovery
2. Heartbeat timing and registry freshness
3. Stale bank detection and removal semantics

The branch-bank spec (Plan 02) will reference this contract for registry integration requirements.