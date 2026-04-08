# Roadmap: Distributed Banking System

## Phase 1: Registry and Accounts

Goal: Define the central-bank directory and core account lifecycle APIs.

Requirements: REG-01, REG-02, REG-03, ACCT-01, ACCT-02, ACCT-03, ACCT-04, HRTB-01, HRTB-02

Success criteria:
1. The API documents how banks register with the central bank.
2. The API documents heartbeat behavior and stale-bank pruning.
3. The API documents user registration, account creation, and account lookup.
4. The account number format is explicit and testable.

**Plans:** 2 plans

Plans:
- [x] 01-registry-and-accounts-01-PLAN.md — Central-bank registry, directory, heartbeat, and pruning contract
- [x] 01-registry-and-accounts-02-PLAN.md — Branch-bank user registration, account creation, and lookup contract

## Phase 2: Same-Bank and Cross-Bank Transfers

Goal: Define the transfer contract for local and inter-bank payments.

Requirements: XFER-01, XFER-02, XFER-03, XFER-04

Success criteria:
1. Same-bank transfer flow is described end to end.
2. Cross-bank routing via bank prefix is documented.
3. Currency conversion behavior is defined.
4. Transfer request and response schemas are precise enough to implement.

**Plans:** 1 plan

Plans:
- [ ] 02-same-bank-and-cross-bank-transfers-01-PLAN.md — Transfer endpoints, exchange rates, and inter-bank authentication

## Phase 3: Resilient Delivery

Goal: Define how transfers survive temporary service outages.

Requirements: XFER-05, XFER-06, XFER-07

Success criteria:
1. The contract explains how transfers proceed when the central bank is down.
2. The contract explains pending transfer handling when the destination bank is down.
3. Timeout failure semantics are explicit.
4. State transitions for transfer outcomes are unambiguous.
