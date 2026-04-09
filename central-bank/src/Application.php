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
        $addressHost = parse_url($input['address'], PHP_URL_HOST);
        if (!preg_match('#^https?://.+#', $input['address']) || !is_string($addressHost) || $addressHost === '') {
            $this->sendError(400, 'Address must be a valid URL', 'INVALID_REQUEST');
        }

        try {
            $this->validateBankAddress($input['address']);
        } catch (\Exception $e) {
            $this->sendError(400, $e->getMessage(), 'INVALID_REQUEST');
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

    private function validateBankAddress(string $address): void
    {
        $parts = parse_url($address);
        $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string)($parts['host'] ?? '')) : '';

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \Exception('Address must be a valid URL');
        }

        if (!$this->isPublicBankHost($host)) {
            throw new \Exception('Address must use a publicly reachable host');
        }

        $healthUrl = $this->buildHealthUrl($parts);
        $statusCode = $this->fetchHealthStatusCode($healthUrl);

        if ($statusCode === null) {
            throw new \Exception('Bank health endpoint is not reachable');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \Exception('Bank health endpoint must return a 2xx response');
        }
    }

    private function isPublicBankHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        $ips = $this->resolveHostIps($host);
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function resolveHostIps(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        if (function_exists('dns_get_record')) {
            foreach ([DNS_A, defined('DNS_AAAA') ? DNS_AAAA : 0] as $type) {
                if ($type === 0) {
                    continue;
                }

                $records = dns_get_record($host, $type) ?: [];
                foreach ($records as $record) {
                    $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                    if (is_string($ip) && $ip !== '') {
                        $ips[] = $ip;
                    }
                }
            }
        }

        if ($ips === []) {
            $ipv4Records = gethostbynamel($host) ?: [];
            foreach ($ipv4Records as $ip) {
                if (is_string($ip) && $ip !== '') {
                    $ips[] = $ip;
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function buildHealthUrl(array $parts): string
    {
        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        $host = (string)($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = rtrim((string)($parts['path'] ?? ''), '/');

        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }

        return $scheme . '://' . $host . $port . $path . '/health';
    }

    private function fetchHealthStatusCode(string $healthUrl): ?int
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: CentralBank/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $headers = [];
        $previousHandler = set_error_handler(static function (): bool {
            return true;
        });

        try {
            file_get_contents($healthUrl, false, $context);
            $headers = $http_response_header ?? [];
        } finally {
            restore_error_handler();
        }

        return $this->extractStatusCode($headers);
    }

    private function extractStatusCode(array $headers): ?int
    {
        $statusCode = null;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                $statusCode = (int)$matches[1];
            }
        }

        return $statusCode;
    }

    private function getPath(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Production serves the API under /central-bank, but routing expects /api/v1 paths.
        $uri = preg_replace('#^/central-bank#', '', $uri);
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
