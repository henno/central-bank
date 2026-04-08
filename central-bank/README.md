# Central Bank API Implementation (PHP)

A PHP implementation of the Central Bank API for the Distributed Banking System.

## Features

- **Bank Registration**: Register new banks with public keys and addresses
- **Bank Directory**: List all active banks with cache metadata
- **Heartbeat Management**: Track bank activity and prune inactive banks (30-minute timeout)
- **Exchange Rates**: Provide current EUR-based exchange rates

## Requirements

- PHP 8.1 or higher
- SQLite3 extension enabled

## Setup

1. Install dependencies (minimal):
```bash
cd central-bank
composer install
```

2. Start the server:
```bash
composer start
```

The API will be available at `http://localhost:8080/api/v1`

## API Endpoints

### Register a Bank
```bash
POST /api/v1/banks
Content-Type: application/json

{
  "name": "Estonia Commercial Bank",
  "address": "https://ecb.banking.example:8443",
  "publicKey": "MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEEVs/o5+..."
}
```

### List All Banks
```bash
GET /api/v1/banks
```

### Get Bank Details
```bash
GET /api/v1/banks/{bankId}
```

### Send Heartbeat
```bash
POST /api/v1/banks/{bankId}/heartbeat
Content-Type: application/json

{
  "timestamp": "2026-04-08T12:00:00Z"
}
```

### Get Exchange Rates
```bash
GET /api/v1/exchange-rates
```

## Timeout Behavior

- Banks that don't send a heartbeat within 30 minutes are automatically removed
- Next bank directory request triggers stale bank pruning
- Removed banks must re-register

## Data Storage

Bank data is stored in `data/banks.db` (SQLite database).

## Development

The implementation follows the OpenAPI 3.1 contract in `openapi/central-bank.yaml`.

All monetary values and exchange rates use decimal strings to avoid floating-point precision issues.

## Notes

- This is a reference implementation for educational purposes
- Exchange rates are currently hardcoded; integrate with an external API in production
- Public key validation is structural only; actual cryptographic verification should be added