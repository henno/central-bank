<?php

namespace CentralBank;

class Application
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function handle(): void
    {
        header('Content-Type: application/json');

        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $path = $this->getPath();

            switch ($path) {
                case '/api/v1/banks':
                    $this->handleBanks($method);
                    break;

                case '/api/v1/exchange-rates':
                    $this->handleExchangeRates($method);
                    break;

                default:
                    if (preg_match('#^/api/v1/banks/([A-Z]{3}\d{3})$#', $path, $matches)) {
                        $this->handleBank($method, $matches[1]);
                    } elseif (preg_match('#^/api/v1/banks/([A-Z]{3}\d{3})/heartbeat$#', $path, $matches)) {
                        $this->handleHeartbeat($method, $matches[1]);
                    } else {
                        $this->sendError(404, 'Not found', 'ENDPOINT_NOT_FOUND');
                    }
            }
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            $this->sendError(
                $code,
                $e->getMessage(),
                $this->getErrorCode($code, $e->getMessage())
            );
        }
    }

    private function handleBanks(string $method): void
    {
        switch ($method) {
            case 'POST':
                $this->registerBank();
                break;

            case 'GET':
                $this->listBanks();
                break;

            default:
                $this->sendError(405, 'Method not allowed', 'METHOD_NOT_ALLOWED');
        }
    }

    private function registerBank(): void
    {
        $input = $this->getJsonInput();

        $required = ['name', 'address', 'publicKey'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $message = ucfirst($field) . ' is required';
                if ($field === 'name') {
                    $message = 'Bank name is required';
                }
                $this->sendError(400, $message, 'INVALID_REQUEST');
            }
        }

        // Validate address format
        if (!preg_match('#^https?://.+#', $input['address'])) {
            $this->sendError(400, 'Address must be a valid URL', 'INVALID_REQUEST');
        }

        // Validate public key length
        if (strlen($input['publicKey']) < 100) {
            $this->sendError(400, 'Public key is too short', 'INVALID_REQUEST');
        }

        try {
            $result = $this->database->registerBank(
                $input['name'],
                $input['address'],
                $input['publicKey']
            );
            $this->sendResponse(201, $result);
        } catch (\Exception $e) {
            if ($e->getCode() === 409) {
                $this->sendError(409, $e->getMessage(), 'DUPLICATE_BANK');
            }
            throw $e;
        }
    }

    private function listBanks(): void
    {
        $result = $this->database->getAllBanks();
        $this->sendResponse(200, $result);
    }

    private function handleBank(string $method, string $bankId): void
    {
        if ($method !== 'GET') {
            $this->sendError(405, 'Method not allowed', 'METHOD_NOT_ALLOWED');
        }

        $bank = $this->database->getBank($bankId);

        if (!$bank) {
            $this->sendError(404, "Bank with ID '{$bankId}' not found or has been removed", 'BANK_NOT_FOUND');
        }

        $this->sendResponse(200, $bank);
    }

    private function handleHeartbeat(string $method, string $bankId): void
    {
        if ($method !== 'POST') {
            $this->sendError(405, 'Method not allowed', 'METHOD_NOT_ALLOWED');
        }

        try {
            $result = $this->database->sendHeartbeat($bankId);
            $this->sendResponse(200, $result);
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $this->sendError(404, $e->getMessage(), 'BANK_NOT_FOUND');
            }
            throw $e;
        }
    }

    private function handleExchangeRates(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendError(405, 'Method not allowed', 'METHOD_NOT_ALLOWED');
        }

        $rates = $this->getExchangeRates();
        $this->sendResponse(200, $rates);
    }

    private function getExchangeRates(): array
    {
        // In production, this would fetch from an external exchange rate API
        // For now, return hardcoded rates as per the OpenAPI spec
        return [
            'baseCurrency' => 'EUR',
            'rates' => [
                'GBP' => '0.850000',
                'USD' => '1.080000',
                'SEK' => '10.500000',
                'LVL' => '0.680000',
                'EEK' => '15.646600',
            ],
            'timestamp' => date('c')
        ];
    }

    private function getPath(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return $uri ?: '/';
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: [];

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(400, 'Invalid JSON body', 'INVALID_REQUEST');
        }

        return $data;
    }

    private function sendResponse(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function sendError(int $statusCode, string $message, string $code): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'code' => $code,
            'message' => $message
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function getErrorCode(int $statusCode, string $message): string
    {
        $errorCodes = [
            400 => 'INVALID_REQUEST',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            500 => 'INTERNAL_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
        ];

        if (str_contains($message, 'already registered')) {
            return 'DUPLICATE_BANK';
        }

        if (str_contains($message, 'not found')) {
            return 'BANK_NOT_FOUND';
        }

        return $errorCodes[$statusCode] ?? 'UNKNOWN_ERROR';
    }
}