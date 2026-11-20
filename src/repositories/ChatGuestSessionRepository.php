<?php
require_once __DIR__ . '/BaseRepository.php';

class ChatGuestSessionRepository extends BaseRepository
{
    private const GUEST_COOKIE_TTL = 30 * 86400;

    public function ttl(): int
    {
        return self::GUEST_COOKIE_TTL;
    }

    public function createForUser(int $userId): string | false
    {
        $token = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $token);

        try {
            $this->db->query('DELETE FROM ChatConvidadoSessao WHERE expiraEm <= UTC_TIMESTAMP()');
            $statement = $this->db->prepare(
                'INSERT INTO ChatConvidadoSessao (tokenHash, idUsuario, expiraEm)
                 VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))'
            );
            $statement->bind_param('si', $tokenHash, $userId);
            $statement->execute();
            return $token;
        } catch (mysqli_sql_exception $e) {
            error_log('ChatGuestSessionRepository::createForUser -> ' . $e->getMessage());
            return false;
        }
    }

    public function findUserIdByToken(string $token): int | false
    {
        if (preg_match('/^[a-f0-9]{32}$/i', $token) !== 1) {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        try {
            $statement = $this->db->prepare(
                'SELECT idUsuario FROM ChatConvidadoSessao
                 WHERE tokenHash = ? AND expiraEm > UTC_TIMESTAMP() LIMIT 1'
            );
            $statement->bind_param('s', $tokenHash);
            $usuario = $this->fetchOne($statement);
            if (!$usuario) {
                return false;
            }

            $userId = (int) $usuario['idUsuario'];
            $update = $this->db->prepare(
                'UPDATE ChatConvidadoSessao SET ultimoUsoEm = UTC_TIMESTAMP() WHERE tokenHash = ?'
            );
            $update->bind_param('s', $tokenHash);
            $update->execute();

            return $userId;
        } catch (mysqli_sql_exception $e) {
            error_log('ChatGuestSessionRepository::findUserIdByToken -> ' . $e->getMessage());
            return false;
        }
    }

    public function deleteByUser(int $userId): void
    {
        try {
            $statement = $this->db->prepare('DELETE FROM ChatConvidadoSessao WHERE idUsuario = ?');
            $statement->bind_param('i', $userId);
            $statement->execute();
        } catch (mysqli_sql_exception $e) {
            error_log('ChatGuestSessionRepository::deleteByUser -> ' . $e->getMessage());
        }
    }
}
