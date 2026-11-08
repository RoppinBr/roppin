<?php 
    require_once './src/scripts/conexao.php';
    require_once './src/models/User.php';

    $db = getConexao();

    if (!$db) {
        error_log("bd_control.php -> Erro crítico: Não foi possível acessar o banco de dados.");
    }

    function getLogin(string $email, string $senha): false | User
    {
        global $db;

        if (!$db) {
            error_log("getLogin -> Tentativa de login sem conexão com o banco.");
            return false;
        }

        $stmt = $db->prepare("SELECT * FROM Usuario WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        try {
            $stmt->execute();

            $resultado = $stmt->get_result();
            $dados = $resultado->fetch_assoc();

            if ($dados && password_verify($senha, $dados['senha'])) {
                $user = new User($dados);
                return $user;
            }

            return false;
        } catch (mysqli_sql_exception $e) {
            error_log("getLogin -> Erro de banco: " . $e->getMessage());
        }

        return false;
    }

    function createUser(string $nome, string $cpf, string $email, string $senha): false | User
    {
        global $db;

        if (!$db) {
            error_log("createUser -> Tentativa de cadastro sem conexão com o banco.");
            return false;
        }

        $hashSenha = password_hash($senha, PASSWORD_DEFAULT); # Em BCRIPT

        try {
            $stmt = $db->prepare("INSERT INTO Usuario (nome, cpf, email, senha) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nome, $cpf, $email, $hashSenha);

            if ($stmt->execute()) {

                $dados = [
                    'id'    => $stmt->insert_id,
                    'nome'  => $nome,
                    'cpf'   => $cpf,
                    'email' => $email,
                    'senha' => $hashSenha
                ];

                return new User($dados);
            }

        } catch (mysqli_sql_exception $e) {
            error_log("createUser -> Erro de banco: " . $e->getMessage());
        }

        return false;
    }

    function updateUser(int $id, string $nome, string $cpf, string $email, string $senha = ''): false | User
    {
        global $db;

        if (!$db) {
            error_log("updateUser -> Tentativa de atualização sem conexão com o banco.");
            return false;
        }

        try {
            if ($senha !== '') {
                $hashSenha = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE Usuario SET nome = ?, cpf = ?, email = ?, senha = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $nome, $cpf, $email, $hashSenha, $id);
            } else {
                $stmt = $db->prepare("UPDATE Usuario SET nome = ?, cpf = ?, email = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nome, $cpf, $email, $id);
            }

            $stmt->execute();

            $consulta = $db->prepare("SELECT id, nome, cpf, email, senha FROM Usuario WHERE id = ? LIMIT 1");
            $consulta->bind_param("i", $id);
            $consulta->execute();
            $dados = $consulta->get_result()->fetch_assoc();

            return $dados ? new User($dados) : false;
        } catch (mysqli_sql_exception $e) {
            error_log("updateUser -> Erro de banco: " . $e->getMessage());
        }

        return false;
    }

    function deleteUser(int $id): bool
    {
        global $db;

        if (!$db) {
            error_log("deleteUser -> Tentativa de exclusão sem conexão com o banco.");
            return false;
        }

        try {
            $stmt = $db->prepare("DELETE FROM Usuario WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            return $stmt->affected_rows === 1;
        } catch (mysqli_sql_exception $e) {
            error_log("deleteUser -> Erro de banco: " . $e->getMessage());
        }

        return false;
    }

    function ensurePasswordResetTable(): bool
    {
        global $db;

        if (!$db) {
            return false;
        }

        try {
            $db->query("CREATE TABLE IF NOT EXISTS PasswordResetTokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                criado_em DATETIME NOT NULL,
                expira_em DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_password_reset_token_hash (token_hash),
                KEY idx_password_reset_user_created (usuario_id, criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return true;
        } catch (mysqli_sql_exception $e) {
            error_log("ensurePasswordResetTable -> Erro de banco: " . $e->getMessage());
            return false;
        }
    }

    function createPasswordReset(string $cpf): array | false
    {
        global $db;

        if (!$db || !ensurePasswordResetTable()) {
            return false;
        }

        $transacaoIniciada = false;

        try {
            $stmt = $db->prepare("SELECT id, nome, email FROM Usuario WHERE cpf = ? LIMIT 1");
            $stmt->bind_param("s", $cpf);
            $stmt->execute();
            $usuario = $stmt->get_result()->fetch_assoc();

            if (!$usuario) {
                return false;
            }

            $limite = $db->prepare("SELECT id FROM PasswordResetTokens WHERE usuario_id = ? AND criado_em > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) LIMIT 1");
            $limite->bind_param("i", $usuario['id']);
            $limite->execute();

            if ($limite->get_result()->fetch_assoc()) {
                return ['limitado' => true];
            }

            $db->begin_transaction();
            $transacaoIniciada = true;
            $remover = $db->prepare("DELETE FROM PasswordResetTokens WHERE usuario_id = ?");
            $remover->bind_param("i", $usuario['id']);
            $remover->execute();

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $inserir = $db->prepare("INSERT INTO PasswordResetTokens (usuario_id, token_hash, criado_em, expira_em) VALUES (?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))");
            $inserir->bind_param("is", $usuario['id'], $tokenHash);
            $inserir->execute();
            $db->commit();
            $transacaoIniciada = false;

            return [
                'email' => $usuario['email'],
                'nome' => $usuario['nome'],
                'token' => $token,
                'limitado' => false
            ];
        } catch (mysqli_sql_exception $e) {
            if ($transacaoIniciada) {
                $db->rollback();
            }
            error_log("createPasswordReset -> Erro de banco: " . $e->getMessage());
        } catch (Throwable $e) {
            if ($transacaoIniciada) {
                $db->rollback();
            }
            error_log("createPasswordReset -> Erro: " . $e->getMessage());
        }

        return false;
    }

    function invalidatePasswordReset(string $token): void
    {
        global $db;

        if (!$db || !ensurePasswordResetTable()) {
            return;
        }

        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $db->prepare("DELETE FROM PasswordResetTokens WHERE token_hash = ?");
            $stmt->bind_param("s", $tokenHash);
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            error_log("invalidatePasswordReset -> Erro de banco: " . $e->getMessage());
        }
    }

    function resetPasswordWithToken(string $token, string $senha): bool
    {
        global $db;

        if (!$db || !ensurePasswordResetTable()) {
            return false;
        }

        $transacaoIniciada = false;

        try {
            $tokenHash = hash('sha256', $token);
            $db->begin_transaction();
            $transacaoIniciada = true;

            $consulta = $db->prepare("SELECT usuario_id FROM PasswordResetTokens WHERE token_hash = ? AND expira_em > UTC_TIMESTAMP() FOR UPDATE");
            $consulta->bind_param("s", $tokenHash);
            $consulta->execute();
            $reset = $consulta->get_result()->fetch_assoc();

            if (!$reset) {
                $db->rollback();
                return false;
            }

            $hashSenha = password_hash($senha, PASSWORD_DEFAULT);
            $atualizar = $db->prepare("UPDATE Usuario SET senha = ? WHERE id = ?");
            $atualizar->bind_param("si", $hashSenha, $reset['usuario_id']);
            $atualizar->execute();

            if ($atualizar->affected_rows !== 1) {
                $db->rollback();
                return false;
            }

            $remover = $db->prepare("DELETE FROM PasswordResetTokens WHERE usuario_id = ?");
            $remover->bind_param("i", $reset['usuario_id']);
            $remover->execute();
            $db->commit();
            $transacaoIniciada = false;

            return true;
        } catch (mysqli_sql_exception $e) {
            if ($transacaoIniciada) {
                $db->rollback();
            }
            error_log("resetPasswordWithToken -> Erro de banco: " . $e->getMessage());
        } catch (Throwable $e) {
            if ($transacaoIniciada) {
                $db->rollback();
            }
            error_log("resetPasswordWithToken -> Erro: " . $e->getMessage());
        }

        return false;
    }

    // createUser("Otávio Custódio Zampar", "11111111111", "otavio.zampar@aluno.cps.sp.gov.br", "teste123");
?>