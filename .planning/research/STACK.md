# Stack Research

## Recommendation

Use OpenAPI 3.1 as the contract source of truth, with JSON Schema-compatible components for request/response models.

## Why

This project is primarily an interop contract between independently built services. The assignment explicitly asks for detailed OpenAPI documentation, so the API spec should be the central artifact rather than an implementation detail.

## What Not To Use

- Ad hoc prose-only API docs, because they are not precise enough for independent implementation.
- Implementation-specific framework assumptions, because the teacher and students may use different stacks.

## Confidence

High: the assignment scope makes the documentation-first direction clear.
