<?php
// helpers/PasskeyHelper.php

use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;


class PasskeyHelper {
    private $webAuthn;
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();

        $this->ensureSchema();

        $rpName = 'Promptash';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = strtolower(explode(':', $host)[0]);
        $host = ltrim($host, '.');
        $rpId = preg_replace('/^www\./', '', $host);

        // Enable base64url encoding for challenge / credential data.
        $this->webAuthn = new WebAuthn($rpName, $rpId, null, true);
    }


    private function ensureSchema() {
        try {
            $result = $this->db->query("SHOW COLUMNS FROM passkey_credentials LIKE 'label'");
            if ($result && $result->fetch(\PDO::FETCH_ASSOC)) {
                return;
            }

            $this->db->exec("ALTER TABLE passkey_credentials ADD COLUMN label VARCHAR(100) DEFAULT NULL AFTER sign_count");
        } catch (\PDOException $e) {
            // Ignore failures; schema will be handled manually if needed.
        }
    }

    public function getPasskeysForUser($userId) {
        $stmt = $this->db->prepare('SELECT id, credential_id, created_at, label FROM passkey_credentials WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute([':user_id' => (int)$userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return $this->formatPasskeyRow($row);
        }, $rows ?: []);
    }

    private function formatPasskeyRow($row) {
        if (!is_array($row)) {
            return [];
        }

        $credentialId = $row['credential_id'] ?? '';
        $createdAt = $row['created_at'] ?? null;
        $label = isset($row['label']) ? trim((string)$row['label']) : '';

        $addedFormatted = null;
        if ($createdAt) {
            try {
                $date = new DateTime($createdAt);
                $addedFormatted = $date->format('M j, Y');
            } catch (Exception $e) {
                // Ignore formatting errors.
            }
        }

        $displayName = $label !== '' ? $label : $this->buildPasskeyDisplayName($credentialId);

        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'credential_id' => $credentialId,
            'created_at' => $createdAt,
            'label' => $label !== '' ? $label : null,
            'display_name' => $displayName,
            'added_on' => $createdAt,
            'added_on_formatted' => $addedFormatted,
        ];
    }

    private function buildPasskeyDisplayName($credentialId) {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string)$credentialId);
        if ($clean === '') {
            try {
                $clean = bin2hex(random_bytes(4));
            } catch (\Exception $e) {
                $clean = '0000';
            }
        }

        $tail = strtoupper(substr($clean, -8));
        if ($tail === '') {
            $tail = strtoupper($clean);
        }

        $chunks = trim(chunk_split($tail, 4, '-'), '-');
        $label = 'Passkey ' . $chunks;

        return $label;
    }

    public function getRegistrationArgs($userId, $username, $displayName) {
        $excludeCredentialIds = $this->getCredentialByteBuffersForUser($userId);

        $args = $this->webAuthn->getCreateArgs(
            (string)$userId,
            $username,
            $displayName,
            60,
            false,
            true,
            true,
            $excludeCredentialIds
        );

        // Only the publicKey options are needed on the client.
        return $args->publicKey;
    }

    public function renamePasskey($userId, $passkeyId, $newName) {
        $normalized = $this->normalizeLabel($newName);

        $stmt = $this->db->prepare('UPDATE passkey_credentials SET label = :label WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            ':label' => $normalized,
            ':id' => (int)$passkeyId,
            ':user_id' => (int)$userId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Passkey not found.');
        }

        return $this->getPasskeyById($passkeyId, $userId);
    }

    public function deletePasskey($userId, $passkeyId) {
        $stmt = $this->db->prepare('DELETE FROM passkey_credentials WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            ':id' => (int)$passkeyId,
            ':user_id' => (int)$userId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Passkey not found.');
        }

        return true;
    }

    public function getAuthenticationArgs() {
        $credentialIds = $this->getCredentialByteBuffersForAll();
        $args = $this->webAuthn->getGetArgs($credentialIds, 60, true, true, true, true, true);
        return $args->publicKey;
    }

    private function getPasskeyById($passkeyId, $userId) {
        $stmt = $this->db->prepare('SELECT id, credential_id, created_at, label FROM passkey_credentials WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            ':id' => (int)$passkeyId,
            ':user_id' => (int)$userId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Passkey not found.');
        }

        return $this->formatPasskeyRow($row);
    }

    private function getCredentialByteBuffersForAll() {
        $stmt = $this->db->query('SELECT credential_id FROM passkey_credentials');
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN, 0) : [];

        $buffers = [];
        foreach ($rows as $id) {
            try {
                $buffers[] = ByteBuffer::fromBase64Url($id);
            } catch (WebAuthnException $e) {
                // Skip malformed entries silently.
            }
        }

        return $buffers;
    }

    private function normalizeLabel($label) {
        $label = trim((string)$label);
        $label = strip_tags($label);
        $label = preg_replace('/\s+/u', ' ', $label);

        if ($label === '') {
            throw new Exception('Passkey name cannot be empty.');
        }

        if (strlen($label) > 100) {
            $label = substr($label, 0, 100);
        }

        return $label;
    }

    public function processRegistration($userId, $clientDataJSON, $challenge) {
        $payload = json_decode($clientDataJSON, true);
        if (!is_array($payload)) {
            throw new Exception('Invalid registration payload.');
        }

        $rawId = $payload['rawId'] ?? null;
        $response = $payload['response'] ?? null;
        if (!$rawId || !is_array($response)) {
            throw new Exception('Incomplete registration data.');
        }

        if (empty($response['clientDataJSON']) || empty($response['attestationObject'])) {
            throw new Exception('Missing attestation fields.');
        }

        $clientData = $this->decodeBase64Url($response['clientDataJSON']);
        $attestationObject = $this->decodeBase64Url($response['attestationObject']);
        $challengeBuffer = $this->normalizeChallenge($challenge);

        try {
            $result = $this->webAuthn->processCreate(
                $clientData,
                $attestationObject,
                $challengeBuffer,
                true,
                true,
                false,
                true
            );
        } catch (WebAuthnException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        $credentialIdBinary = $result->credentialId;
        $rawIdBinary = $this->decodeBase64Url($rawId);

        if ($credentialIdBinary !== $rawIdBinary) {
            throw new Exception('Credential identifier mismatch.');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO passkey_credentials (user_id, credential_id, public_key, attestation_object, sign_count, label)
             VALUES (:user_id, :cred_id, :pub_key, :att_obj, :sign_count, :label)
             ON DUPLICATE KEY UPDATE public_key = VALUES(public_key), attestation_object = VALUES(attestation_object), sign_count = VALUES(sign_count)"
        );

        $defaultLabel = $this->buildPasskeyDisplayName($rawId);

        if (!$stmt->execute([
            ':user_id' => (int)$userId,
            ':cred_id' => $rawId,
            ':pub_key' => $result->credentialPublicKey,
            ':att_obj' => base64_encode($attestationObject),
            ':sign_count' => (int)($result->signatureCounter ?? 0),
            ':label' => $defaultLabel,
        ])) {
            throw new Exception('Failed to store passkey.');
        }

        $lookup = $this->db->prepare('SELECT id, credential_id, created_at, label FROM passkey_credentials WHERE credential_id = :cred_id LIMIT 1');
        $lookup->execute([':cred_id' => $rawId]);
        $row = $lookup->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Registered passkey could not be loaded.');
        }

        if (empty($row['label'])) {
            $row['label'] = $defaultLabel;
        }

        return $this->formatPasskeyRow($row);
    }


    public function processAuthentication($clientDataJSON, $challenge) {
        $payload = json_decode($clientDataJSON, true);
        if (!is_array($payload)) {
            throw new Exception('Invalid authentication payload.');
        }

        $rawId = $payload['rawId'] ?? null;
        $response = $payload['response'] ?? null;
        if (!$rawId || !is_array($response)) {
            throw new Exception('Incomplete authentication data.');
        }

        if (empty($response['clientDataJSON']) || empty($response['authenticatorData']) || empty($response['signature'])) {
            throw new Exception('Missing authentication fields.');
        }

        $stmt = $this->db->prepare('SELECT * FROM passkey_credentials WHERE credential_id = :cred_id LIMIT 1');
        $stmt->execute([':cred_id' => $rawId]);
        $credential = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$credential) {
            throw new Exception('Credential not found.');
        }

        $clientData = $this->decodeBase64Url($response['clientDataJSON']);
        $authenticatorData = $this->decodeBase64Url($response['authenticatorData']);
        $signature = $this->decodeBase64Url($response['signature']);
        $challengeBuffer = $this->normalizeChallenge($challenge);

        try {
            $this->webAuthn->processGet(
                $clientData,
                $authenticatorData,
                $signature,
                $credential['public_key'],
                $challengeBuffer,
                (int)$credential['sign_count'],
                true,
                true
            );
        } catch (WebAuthnException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        $newSignCount = $this->webAuthn->getSignatureCounter();
        if ($newSignCount !== null && $newSignCount > (int)$credential['sign_count']) {
            $update = $this->db->prepare('UPDATE passkey_credentials SET sign_count = :sign_count WHERE id = :id');
            $update->execute([
                ':sign_count' => (int)$newSignCount,
                ':id' => (int)$credential['id']
            ]);
        }

        return (int)$credential['user_id'];
    }

    public function challengeToString($challenge) {
        if ($challenge instanceof ByteBuffer) {
            return $challenge->jsonSerialize();
        }

        if (is_string($challenge) && $challenge !== '') {
            return $challenge;
        }

        throw new Exception('Invalid challenge value.');
    }

    private function getCredentialByteBuffersForUser($userId) {
        $stmt = $this->db->prepare('SELECT credential_id FROM passkey_credentials WHERE user_id = :user_id');
        $stmt->execute([':user_id' => (int)$userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        $buffers = [];
        foreach ($rows as $id) {
            try {
                $buffers[] = ByteBuffer::fromBase64Url($id);
            } catch (WebAuthnException $e) {
                // Skip malformed entries silently.
            }
        }

        return $buffers;
    }

    private function decodeBase64Url($value) {
        if (!is_string($value) || $value === '') {
            throw new Exception('Invalid base64url value.');
        }

        try {
            return ByteBuffer::fromBase64Url($value)->getBinaryString();
        } catch (WebAuthnException $e) {
            throw new Exception('Invalid base64url value.');
        }
    }

    private function normalizeChallenge($challenge) {
        if ($challenge instanceof ByteBuffer) {
            return $challenge;
        }

        if (is_string($challenge) && $challenge !== '') {
            try {
                return ByteBuffer::fromBase64Url($challenge);
            } catch (WebAuthnException $e) {
                return new ByteBuffer($challenge);
            }
        }

        throw new Exception('Missing challenge.');
    }
}
?>


