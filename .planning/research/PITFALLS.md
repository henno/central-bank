# Pitfalls Research

## Pitfall 1: Ambiguous transfer ownership

- Warning sign: account lookup only returns a number with no owner identity.
- Prevention: define unauthenticated lookup response shape precisely, including owner name or 404.
- Address in: requirements and API design.

## Pitfall 2: Central bank as a single point of failure

- Warning sign: all inter-bank routing depends on a live central-bank lookup.
- Prevention: require banks to cache directory data or otherwise continue transfers when the central bank is unavailable.
- Address in: transfer workflow design.

## Pitfall 3: Weak timeout semantics

- Warning sign: pending transfers have no clear failure/timeout state.
- Prevention: define explicit states for pending, failed, and timed-out transfers.
- Address in: requirements and status model.

## Pitfall 4: Currency conversion ambiguity

- Warning sign: no clear source for exchange rates.
- Prevention: define that conversion uses the current rate at transfer time and document the external dependency.
- Address in: API contract and implementation notes.

## Pitfall 5: Bank registry drift

- Warning sign: dead banks remain discoverable after heartbeats stop.
- Prevention: central bank prunes banks after 30 minutes without heartbeat.
- Address in: lifecycle and operational requirements.
