# Architecture Research

## Components

- Central bank directory service: registers banks, stores public keys, exposes lookup and registry endpoints, and prunes stale banks.
- Branch bank service: manages users, accounts, balances, transfer orchestration, and heartbeats.
- Inter-bank transfer interface: used by banks to resolve recipients and deliver transfers.
- Exchange-rate dependency: provides current rates for cross-currency transfers.

## Data Flow

1. A branch bank uses the central bank to discover another bank by account prefix.
2. The branch bank verifies the destination bank using its public key.
3. The source bank submits the transfer, either directly or via a pending workflow if the destination bank is unavailable.
4. The central bank receives periodic heartbeats and removes banks that stop reporting.

## Build Order

1. Define the central-bank and branch-bank APIs.
2. Define account numbering and lookup semantics.
3. Define transfer and pending-transfer workflows.
4. Define heartbeat and registry lifecycles.

## Confidence

High on the overall component split, medium on the exact transfer recovery semantics until the OpenAPI is finalized.
