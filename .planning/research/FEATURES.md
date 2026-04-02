# Feature Research

## Table Stakes

- Bank registration in the central bank.
- User registration within a bank.
- Account creation with currency selection.
- Account lookup by number, including owner name or 404.
- Internal transfers within the same bank.
- Cross-bank transfers.
- Currency conversion during transfer when currencies differ.
- Heartbeats from banks to the central bank.
- Removal of banks that stop heartbeating.

## Differentiators

- Cross-bank transfer routing using bank-prefixed account numbers and central-bank discovery.
- Transfer continuity when the central bank is offline.
- Pending transfer handling when the destination bank is temporarily offline.
- Timeout failure reporting when the destination bank never returns online.

## Anti-Features

- End-user UI.
- Full implementation code at this stage.

## Notes

The transfer path is the most complex feature set because it combines discovery, resilience, and eventual delivery semantics.
