<?php
require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/RememberMeToken.php';

class RememberMeTokenRepository extends BaseRepository
{
    private const COOKIE_LIFETIME = 2592000;

    public function issue(int $userId): array | false
    {
        try {
            $selector = bin2hex(random_bytes(16));
            $validator = bin2hex(random_bytes(32));
            $validatorHash = hash('sha256', $validator);
            $statement = $this->db->prepare('INSERT INTO RememberMeTokens (selector, usuario_id, validator_hash, criado_em, expira_em) VALUES (?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))');
            $statement->bind_param('sis', $selector, $userId, $validatorHash);
            $statement->execute();

            return [
                'cookie' => $selector . '.' . $validator,
                'expires' => time() + self::COOKIE_LIFETIME
            ];
        } catch (Throwable $e) {
            error_log('RememberMeTokenRepository::issue -> ' . $e->getMessage());
            return false;
        }
    }

    public function consume(string $cookie): array | false
    {
        if (!preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/', $cookie, $parts)) {
            return false;
        }

        $selector = $parts[1];
        $validator = $parts[2];
        $transactionStarted = false;

        try {
            $this->db->begin_transaction();
            $transactionStarted = true;

            $statement = $this->db->prepare('SELECT usuario_id, validator_hash, UNIX_TIMESTAMP(expira_em) AS expira_timestamp, expira_em > UTC_TIMESTAMP() AS ativo FROM RememberMeTokens WHERE selector = ? FOR UPDATE');
            $statement->bind_param('s', $selector);
            $row = $this->fetchOne($statement);

            if (!$row) {
                $this->db->rollback();
                return false;
            }

            if (!(bool) $row['ativo']) {
                $delete = $this->db->prepare('DELETE FROM RememberMeTokens WHERE selector = ?');
                $delete->bind_param('s', $selector);
                $delete->execute();
                $this->db->commit();
                $transactionStarted = false;
                return false;
            }

            if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) {
                $this->db->rollback();
                return false;
            }

            $nextValidator = bin2hex(random_bytes(32));
            $nextHash = hash('sha256', $nextValidator);
            $rotate = $this->db->prepare('UPDATE RememberMeTokens SET validator_hash = ? WHERE selector = ?');
            $rotate->bind_param('ss', $nextHash, $selector);
            $rotate->execute();
            $this->db->commit();
            $transactionStarted = false;

            return [
                'userId' => (int) $row['usuario_id'],
                'cookie' => $selector . '.' . $nextValidator,
                'expires' => (int) $row['expira_timestamp']
            ];
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            error_log('RememberMeTokenRepository::consume -> ' . $e->getMessage());
            return false;
        }
    }

    public function revoke(string $cookie): void
    {
        if (!preg_match('/^([a-f0-9]{32})\.[a-f0-9]{64}$/', $cookie, $parts)) {
            return;
        }

        try {
            $statement = $this->db->prepare('DELETE FROM RememberMeTokens WHERE selector = ?');
            $statement->bind_param('s', $parts[1]);
            $statement->execute();
        } catch (mysqli_sql_exception $e) {
            error_log('RememberMeTokenRepository::revoke -> ' . $e->getMessage());
        }
    }

    public function revokeAllForUser(int $userId): void
    {
        try {
            $statement = $this->db->prepare('DELETE FROM RememberMeTokens WHERE usuario_id = ?');
            $statement->bind_param('i', $userId);
            $statement->execute();
        } catch (mysqli_sql_exception $e) {
            error_log('RememberMeTokenRepository::revokeAllForUser -> ' . $e->getMessage());
        }
    }
}