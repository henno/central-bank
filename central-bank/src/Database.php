<?php

namespace CentralBank;

class Database
{
    private \SQLite3 $db;

    public function __construct(string $dbPath)
    {
        $this->db = new \SQLite3($dbPath);
        $this->executeSql('PRAGMA foreign_keys = ON');
        $this->initializeSchema();
        $this->normalizeBankIds();
    }

    private function initializeSchema(): void
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS banks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bank_id TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                address TEXT NOT NULL,
                public_key TEXT NOT NULL,
                last_heartbeat TEXT,
                expires_at TEXT,
                created_at TEXT NOT NULL,
                UNIQUE(address)
            )
        ');

        $this->db->exec('
            CREATE TABLE IF NOT EXISTS bank_id_aliases (
                alias_bank_id TEXT PRIMARY KEY,
                bank_id TEXT NOT NULL,
                FOREIGN KEY(bank_id) REFERENCES banks(bank_id) ON DELETE CASCADE
            )
        ');
    }

    public function registerBank(string $name, string $address, string $publicKey): array
    {
        $now = $this->getCurrentTime();
        $expiresAt = date('c', strtotime($now . ' +30 minutes'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $transactionStarted = false;

            try {
                $this->executeSql('BEGIN IMMEDIATE TRANSACTION');
                $transactionStarted = true;

                // Generate bank ID with a globally unique 3-letter routing prefix.
                $bankId = $this->generateBankId($name, $address);

                $stmt = $this->prepareStatement('
                    INSERT INTO banks (bank_id, name, address, public_key, last_heartbeat, expires_at, created_at)
                    VALUES (:bank_id, :name, :address, :public_key, :last_heartbeat, :expires_at, :created_at)
                ');

                $stmt->bindValue(':bank_id', $bankId, SQLITE3_TEXT);
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':address', $address, SQLITE3_TEXT);
                $stmt->bindValue(':public_key', $publicKey, SQLITE3_TEXT);
                $stmt->bindValue(':last_heartbeat', $now, SQLITE3_TEXT);
                $stmt->bindValue(':expires_at', $expiresAt, SQLITE3_TEXT);
                $stmt->bindValue(':created_at', $now, SQLITE3_TEXT);

                $this->executeStatement($stmt);
                $this->executeSql('COMMIT');
                return ['bankId' => $bankId, 'expiresAt' => $expiresAt];
            } catch (\RuntimeException $e) {
                if ($transactionStarted) {
                    $this->db->exec('ROLLBACK');
                }

                if (str_contains($e->getMessage(), 'UNIQUE constraint failed: banks.address')
                    || str_contains($e->getMessage(), 'UNIQUE constraint failed banks.address')) {
                    throw new \Exception('A bank with this address is already registered', 409);
                }

                if (str_contains($e->getMessage(), 'database is locked')
                    || str_contains($e->getMessage(), 'database table is locked')
                    || str_contains($e->getMessage(), 'database schema is locked')) {
                    usleep(50000);
                    continue;
                }

                if (str_contains($e->getMessage(), 'UNIQUE constraint failed: banks.bank_id')
                    || str_contains($e->getMessage(), 'UNIQUE constraint failed banks.bank_id')) {
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('Failed to allocate a unique bank ID');
    }

    public function getAllBanks(): array
    {
        $this->pruneStaleBanks();
        $now = $this->getCurrentTime();
        $lastSyncedAt = $now;

        $stmt = $this->db->prepare('
            SELECT bank_id, name, address, public_key, last_heartbeat
            FROM banks
            WHERE expires_at > :now
            ORDER BY created_at ASC
        ');

        $stmt->bindValue(':now', $now, SQLITE3_TEXT);
        $result = $stmt->execute();

        $banks = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $banks[] = [
                'bankId' => $row['bank_id'],
                'name' => $row['name'],
                'address' => $row['address'],
                'publicKey' => $row['public_key'],
                'lastHeartbeat' => $row['last_heartbeat'],
                'status' => 'active'
            ];
        }

        return ['banks' => $banks, 'lastSyncedAt' => $lastSyncedAt];
    }

    public function getBank(string $bankId): ?array
    {
        $this->pruneStaleBanks();
        $canonicalBankId = $this->resolveBankId($bankId);

        $bank = $this->fetchBankById($canonicalBankId);
        if (!$bank) {
            $refreshedBankId = $this->resolveBankId($bankId);
            if ($refreshedBankId !== $canonicalBankId) {
                $bank = $this->fetchBankById($refreshedBankId);
            }
        }

        if (!$bank) {
            return null;
        }

        return [
            'bankId' => $bank['bank_id'],
            'name' => $bank['name'],
            'address' => $bank['address'],
            'publicKey' => $bank['public_key'],
            'lastHeartbeat' => $bank['last_heartbeat'],
            'status' => 'active'
        ];
    }

    public function sendHeartbeat(string $bankId): array
    {
        $now = $this->getCurrentTime();
        $expiresAt = date('c', strtotime($now . ' +30 minutes'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $transactionStarted = false;

            try {
                $this->executeSql('BEGIN IMMEDIATE TRANSACTION');
                $transactionStarted = true;

                $this->pruneStaleBanks();
                $canonicalBankId = $this->resolveBankId($bankId);

                $stmt = $this->prepareStatement('
                    UPDATE banks
                    SET last_heartbeat = :now, expires_at = :expires_at
                    WHERE bank_id = :bank_id
                ');

                $stmt->bindValue(':now', $now, SQLITE3_TEXT);
                $stmt->bindValue(':expires_at', $expiresAt, SQLITE3_TEXT);
                $stmt->bindValue(':bank_id', $canonicalBankId, SQLITE3_TEXT);

                $this->executeStatement($stmt);

                if ($this->db->changes() === 0) {
                    throw new \Exception('Bank not found or has been removed due to inactivity', 404);
                }

                $this->executeSql('COMMIT');

                return [
                    'bankId' => $canonicalBankId,
                    'receivedAt' => $now,
                    'expiresAt' => $expiresAt,
                    'status' => 'active'
                ];
            } catch (\RuntimeException $e) {
                if ($transactionStarted) {
                    $this->db->exec('ROLLBACK');
                }

                if (str_contains($e->getMessage(), 'database is locked')
                    || str_contains($e->getMessage(), 'database table is locked')
                    || str_contains($e->getMessage(), 'database schema is locked')) {
                    usleep(50000);
                    continue;
                }

                throw $e;
            } catch (\Throwable $e) {
                if ($transactionStarted) {
                    $this->db->exec('ROLLBACK');
                }
                throw $e;
            }
        }

        throw new \RuntimeException('Failed to process heartbeat');
    }

    private function fetchBankById(string $bankId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT bank_id, name, address, public_key, last_heartbeat
            FROM banks
            WHERE bank_id = :bank_id
            LIMIT 1
        ');

        $stmt->bindValue(':bank_id', $bankId, SQLITE3_TEXT);
        $result = $stmt->execute();

        $bank = $result->fetchArray(SQLITE3_ASSOC);

        return $bank ?: null;
    }

    private function pruneStaleBanks(): void
    {
        $now = $this->getCurrentTime();
        $stmt = $this->db->prepare('
            DELETE FROM banks
            WHERE expires_at < :now
        ');
        $stmt->bindValue(':now', $now, SQLITE3_TEXT);
        $stmt->execute();

        $this->db->exec('
            DELETE FROM bank_id_aliases
            WHERE bank_id NOT IN (SELECT bank_id FROM banks)
        ');
    }

    private function normalizeBankIds(): void
    {
        if (!$this->needsBankIdNormalization()) {
            return;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $transactionStarted = false;

            try {
                $this->executeSql('BEGIN IMMEDIATE TRANSACTION');
                $transactionStarted = true;

                if (!$this->needsBankIdNormalization()) {
                    $this->executeSql('COMMIT');
                    return;
                }

                $result = $this->queryStatement('
                    SELECT id, bank_id, name, address
                    FROM banks
                    ORDER BY created_at ASC, id ASC
                ');

                $usedPrefixes = [];

                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $storedBankId = (string)$row['bank_id'];
                    $currentBankId = strtoupper($storedBankId);
                    $currentPrefix = substr($currentBankId, 0, 3);
                    $hasLegacyPrefix = $currentPrefix === 'HTT';
                    $hasValidFormat = preg_match('/^[A-Z]{3}\d{3}$/', $currentBankId) === 1;
                    $hasUppercaseStorage = $storedBankId === $currentBankId;

                    if ($hasValidFormat && $hasUppercaseStorage && !$hasLegacyPrefix && !isset($usedPrefixes[$currentPrefix])) {
                        $usedPrefixes[$currentPrefix] = true;
                        continue;
                    }

                    $newPrefix = $this->allocateUniquePrefix($row['name'], $row['address'], $usedPrefixes);
                    $newBankId = $newPrefix . '001';

                    if ($newBankId !== $storedBankId) {
                        $update = $this->prepareStatement('
                            UPDATE banks
                            SET bank_id = :bank_id
                            WHERE id = :id
                        ');
                        $update->bindValue(':bank_id', $newBankId, SQLITE3_TEXT);
                        $update->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
                        $this->executeStatement($update);

                        if ($storedBankId === $currentBankId && $currentBankId !== $newBankId) {
                            $alias = $this->prepareStatement('
                                INSERT INTO bank_id_aliases (alias_bank_id, bank_id)
                                VALUES (:alias_bank_id, :bank_id)
                                ON CONFLICT(alias_bank_id) DO UPDATE SET bank_id = excluded.bank_id
                            ');
                            $alias->bindValue(':alias_bank_id', $currentBankId, SQLITE3_TEXT);
                            $alias->bindValue(':bank_id', $newBankId, SQLITE3_TEXT);
                            $this->executeStatement($alias);
                        }
                    }

                    $usedPrefixes[$newPrefix] = true;
                }

                $this->executeSql('COMMIT');
                return;
            } catch (\RuntimeException $e) {
                if ($transactionStarted) {
                    $this->db->exec('ROLLBACK');
                }

                if (str_contains($e->getMessage(), 'database is locked')
                    || str_contains($e->getMessage(), 'database table is locked')
                    || str_contains($e->getMessage(), 'database schema is locked')) {
                    usleep(50000);
                    continue;
                }

                throw $e;
            } catch (\Throwable $e) {
                if ($transactionStarted) {
                    $this->db->exec('ROLLBACK');
                }
                throw $e;
            }
        }

        throw new \RuntimeException('Failed to normalize bank IDs');
    }

    private function generateBankId(string $name, string $address): string
    {
        $prefix = $this->allocateUniquePrefix($name, $address, $this->getUsedPrefixes());
        return $prefix . '001';
    }

    private function allocateUniquePrefix(string $name, string $address, array $usedPrefixes): string
    {
        foreach ($this->getPrefixCandidates($name, $address) as $candidate) {
            if (!isset($usedPrefixes[$candidate])) {
                return $candidate;
            }
        }

        $hash = sha1($name . '|' . $address);
        for ($offset = 0; $offset <= strlen($hash) - 5; $offset++) {
            $candidate = $this->numberToPrefix(hexdec(substr($hash, $offset, 5)));
            if (!isset($usedPrefixes[$candidate])) {
                return $candidate;
            }
        }

        for ($index = 0; $index < 26 * 26 * 26; $index++) {
            $candidate = $this->numberToPrefix($index);
            if (!isset($usedPrefixes[$candidate])) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No bank prefixes available');
    }

    private function getUsedPrefixes(): array
    {
        $result = $this->queryStatement('SELECT DISTINCT SUBSTR(bank_id, 1, 3) AS prefix FROM banks');
        $usedPrefixes = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $prefix = strtoupper((string)($row['prefix'] ?? ''));
            if (preg_match('/^[A-Z]{3}$/', $prefix) === 1) {
                $usedPrefixes[$prefix] = true;
            }
        }

        return $usedPrefixes;
    }

    private function needsBankIdNormalization(): bool
    {
        $legacyResult = $this->queryStatement("
            SELECT COUNT(*) AS count
            FROM banks
            WHERE SUBSTR(bank_id, 1, 3) = 'HTT'
               OR bank_id NOT GLOB '[A-Z][A-Z][A-Z][0-9][0-9][0-9]'
        ");
        $legacyRow = $legacyResult->fetchArray(SQLITE3_ASSOC);
        if (((int)($legacyRow['count'] ?? 0)) > 0) {
            return true;
        }

        $duplicateResult = $this->queryStatement('
            SELECT COUNT(*) AS count
            FROM (
                SELECT SUBSTR(bank_id, 1, 3) AS prefix
                FROM banks
                GROUP BY prefix
                HAVING COUNT(*) > 1
            )
        ');
        $duplicateRow = $duplicateResult->fetchArray(SQLITE3_ASSOC);

        return ((int)($duplicateRow['count'] ?? 0)) > 0;
    }

    private function resolveBankId(string $bankId): string
    {
        $bankId = strtoupper($bankId);

        $stmt = $this->prepareStatement('
            SELECT bank_id
            FROM bank_id_aliases
            WHERE alias_bank_id = :alias_bank_id
            LIMIT 1
        ');
        $stmt->bindValue(':alias_bank_id', $bankId, SQLITE3_TEXT);
        $result = $this->executeStatement($stmt);
        $row = $result->fetchArray(SQLITE3_ASSOC);

        return strtoupper((string)($row['bank_id'] ?? $bankId));
    }

    private function getPrefixCandidates(string $name, string $address): array
    {
        $sources = [
            $this->normalizePrefixSource($name),
            $this->normalizePrefixSource((string)(parse_url($address, PHP_URL_HOST) ?? '')),
        ];

        $candidates = [];

        foreach ($sources as $source) {
            $length = strlen($source);
            if ($length < 3) {
                continue;
            }

            $candidates[] = substr($source, 0, 3);
            for ($offset = 1; $offset <= $length - 3; $offset++) {
                $candidates[] = substr($source, $offset, 3);
            }
        }

        if (empty($candidates)) {
            $candidates[] = 'BNK';
        }

        return array_values(array_unique($candidates));
    }

    private function normalizePrefixSource(string $value): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper($value));
    }

    private function numberToPrefix(int $number): string
    {
        $alphabetSize = 26;
        $number = $number % ($alphabetSize * $alphabetSize * $alphabetSize);

        $first = chr(65 + intdiv($number, $alphabetSize * $alphabetSize));
        $second = chr(65 + intdiv($number, $alphabetSize) % $alphabetSize);
        $third = chr(65 + ($number % $alphabetSize));

        return $first . $second . $third;
    }

    private function prepareStatement(string $sql): \SQLite3Stmt
    {
        $statement = $this->db->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException($this->db->lastErrorMsg());
        }

        return $statement;
    }

    private function queryStatement(string $sql): \SQLite3Result
    {
        $result = $this->db->query($sql);
        if ($result === false) {
            throw new \RuntimeException($this->db->lastErrorMsg());
        }

        return $result;
    }

    private function executeStatement(\SQLite3Stmt $statement)
    {
        $result = $statement->execute();
        if ($result === false) {
            throw new \RuntimeException($this->db->lastErrorMsg());
        }

        return $result;
    }

    private function executeSql(string $sql): void
    {
        if ($this->db->exec($sql) === false) {
            throw new \RuntimeException($this->db->lastErrorMsg());
        }
    }

    private function getCurrentTime(): string
    {
        return date('c');
    }
}
