# Distributed Banking System

## What This Is

A distributed banking system for a university assignment about a central bank and separately implemented branch banks. The central bank acts like a DNS directory for banks, storing public keys and helping banks discover one another so they can authenticate cross-bank transfers.

The immediate goal is detailed OpenAPI documentation for both the central bank and any branch bank implementation. No application implementation is needed yet.

## Core Value

Banks must be able to discover, authenticate, and transfer money between each other reliably, even when the central bank or the destination bank is temporarily unavailable.

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] Central bank can register banks and expose registered bank directory data.
- [ ] Branch banks can register users and let users create accounts with a chosen currency.
- [ ] Users can transfer funds within a bank and across banks, including currency conversion at the current exchange rate.
- [ ] Cross-bank transfers can continue when the central bank is down, and pending transfers resolve correctly when the destination bank is temporarily unavailable.
- [ ] Account lookup can reveal whether an account exists and who owns it, even for unauthenticated requests.
- [ ] Branch banks send heartbeats to the central bank and inactive banks are removed after timeout.

### Out of Scope

- Full application implementation — the current deliverable is OpenAPI documentation only.
- User-facing UI — no UI has been requested.

## Context

This is a school assignment for the course "Hajusrakendused". The teacher will implement the central bank, while each student implements one branch bank.

The system needs a clear protocol boundary so independently built banks can interoperate. Key concerns are discovery, authentication, inter-bank routing, pending transfers, timeout handling, and account number conventions.

## Constraints

- **API contract**: OpenAPI first — documentation must be detailed enough to implement both central and branch banks.
- **Account format**: Account numbers are 8 characters long, and the first 3 characters identify the bank.
- **Resilience**: Transfers must work even if the central bank is temporarily unavailable.
- **Operational**: The central bank removes banks that have not heartbeated within 30 minutes.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Focus on OpenAPI documentation before implementation | The assignment explicitly prioritizes the contract layer first | Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-04-02 after initialization*
