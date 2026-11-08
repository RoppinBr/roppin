<?php
require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/PasswordResetToken.php';

class PasswordResetTokenRepository extends BaseRepository
{
    public function createForDocument(string $documento): array | false
    {
        $documento = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $documento));
        $transacaoIniciada = false;

        try {
            $this->db->query('DELETE FROM PasswordResetTokens WHERE expira_em <= UTC_TIMESTAMP()');
            $statement = $this->db->prepare('SELECT idUsuario, COALESCE(NULLIF(nome, \'\'), razaoSocial) AS nome, email FROM Usuario WHERE cpf = ? OR cnpj = ? LIMIT 1');
            $statement->bind_param('ss', $documento, $documento);
            $usuario = $this->fetchOne($statement);

            if (!$usuario) {
                return false;
            }

            $limite = $this->db->prepare('SELECT id FROM PasswordResetTokens WHERE usuario_id = ? AND criado_em > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) LIMIT 1');
            $limite->bind_param('i', $usuario['idUsuario']);
            if ($this->fetchOne($limite)) {
                return ['limitado' => true];
            }

            $token = bin2hex(random_bytes(32));
            $tokenModel = PasswordResetToken::issue((int) $usuario['idUsuario'], $token);

            $this->db->begin_transaction();
            $transacaoIniciada = true;
            $remover = $this->db->prepare('DELETE FROM PasswordResetTokens WHERE usuario_id = ?');
            $usuarioId = $tokenModel->getUsuarioId();
            $remover->bind_param('i', $usuarioId);
            $remover->execute();

            $inserir = $this->db->prepare('INSERT INTO PasswordResetTokens (usuario_id, token_hash, criado_em, expira_em) VALUES (?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))');
            $tokenHash = $tokenModel->getTokenHash();
            $inserir->bind_param('is', $usuarioId, $tokenHash);
            $inserir->execute();
            $this->db->commit();
            $transacaoIniciada = false;

            return ['email' => $usuario['email'], 'nome' => $usuario['nome'], 'token' => $token, 'limitado' => false];
        } catch (Throwable $e) {
            if ($transacaoIniciada) {
                $this->db->rollback();
            }
            error_log('PasswordResetTokenRepository::createForDocument -> ' . $e->getMessage());
            return false;
        }
    }

    public function invalidate(string $token): void
    {
        try {
            $tokenHash = hash('sha256', $token);
            $statement = $this->db->prepare('DELETE FROM PasswordResetTokens WHERE token_hash = ?');
            $statement->bind_param('s', $tokenHash);
            $statement->execute();
        } catch (mysqli_sql_exception $e) {
            error_log('PasswordResetTokenRepository::invalidate -> ' . $e->getMessage());
        }
    }

    public function resetPassword(string $token, string $senha): bool
    {
        $transacaoIniciada = false;

        try {
            $tokenHash = hash('sha256', $token);
            $this->db->begin_transaction();
            $transacaoIniciada = true;

            $consulta = $this->db->prepare('SELECT usuario_id FROM PasswordResetTokens WHERE token_hash = ? AND expira_em > UTC_TIMESTAMP() FOR UPDATE');
            $consulta->bind_param('s', $tokenHash);
            $reset = $this->fetchOne($consulta);

            if (!$reset) {
                $this->db->rollback();
                return false;
            }

            $hashSenha = password_hash($senha, PASSWORD_DEFAULT);
            $atualizar = $this->db->prepare('UPDATE Usuario SET senha = ? WHERE idUsuario = ?');
            $atualizar->bind_param('si', $hashSenha, $reset['usuario_id']);
            $atualizar->execute();

            if ($atualizar->affected_rows !== 1) {
                $this->db->rollback();
                return false;
            }

            $remover = $this->db->prepare('DELETE FROM PasswordResetTokens WHERE usuario_id = ?');
            $remover->bind_param('i', $reset['usuario_id']);
            $remover->execute();
            $this->db->commit();
            $transacaoIniciada = false;
            return true;
        } catch (Throwable $e) {
            if ($transacaoIniciada) {
                $this->db->rollback();
            }
            error_log('PasswordResetTokenRepository::resetPassword -> ' . $e->getMessage());
            return false;
        }
    }
}