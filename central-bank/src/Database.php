<?php

namespace CentralBank;

class Database
{
    private \SQLite3 $db;

    public function __construct(string $dbPath)
    {
        $this->db = new \SQLite3($dbPath);
        $this->initializeSchema();
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
    }

    public function registerBank(string $name, string $address, string $publicKey): array
    {
        // Generate bank ID (3-letter country code + 3-digit number)
        $bankId = $this->generateBankId($address);
        $now = $this->getCurrentTime();
        $expiresAt = date('c', strtotime($now . ' +30 minutes'));

        $stmt = $this->db->prepare('
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

        try {
            $stmt->execute();
            return ['bankId' => $bankId, 'expiresAt' => $expiresAt];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed banks.address')) {
                throw new \Exception('A bank with this address is already registered', 409);
            }
            throw $e;
        }
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

        $stmt = $this->db->prepare('
            SELECT bank_id, name, address, public_key, last_heartbeat
            FROM banks
            WHERE bank_id = :bank_id
            LIMIT 1
        ');

        $stmt->bindValue(':bank_id', strtoupper($bankId), SQLITE3_TEXT);
        $result = $stmt->execute();

        $bank = $result->fetchArray(SQLITE3_ASSOC);

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
        $this->pruneStaleBanks();

        $now = $this->getCurrentTime();
        $expiresAt = date('c', strtotime($now . ' +30 minutes'));

        $stmt = $this->db->prepare('
            UPDATE banks
            SET last_heartbeat = :now, expires_at = :expires_at
            WHERE bank_id = :bank_id
        ');

        $stmt->bindValue(':now', $now, SQLITE3_TEXT);
        $stmt->bindValue(':expires_at', $expiresAt, SQLITE3_TEXT);
        $stmt->bindValue(':bank_id', strtoupper($bankId), SQLITE3_TEXT);

        $stmt->execute();

        if ($this->db->changes() === 0) {
            throw new \Exception('Bank not found or has been removed due to inactivity', 404);
        }

        return [
            'bankId' => $bankId,
            'receivedAt' => $now,
            'expiresAt' => $expiresAt,
            'status' => 'active'
        ];
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
    }

    private function generateBankId(string $address): string
    {
        // Extract country code from address or use hash
        $hash = md5($address);
        $prefix = strtoupper(substr($address, 0, 3));
        $prefix = preg_replace('/[^A-Z]/', '', $prefix);
        $prefix = $prefix ?: 'EST';

        // Generate a unique number
        $count = 0;
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM banks WHERE bank_id LIKE :prefix');
        $like = $prefix . '%';
        $stmt->bindValue(':prefix', $like, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $count = (int)($row['count'] ?? 0) + 1;

        return strtoupper(sprintf('%s%03d', substr($prefix, 0, 3), $count));
    }

    private function getCurrentTime(): string
    {
        return date('c');
    }
}