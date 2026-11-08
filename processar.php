<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once './src/scripts/bd_control.php';
require_once './src/scripts/password_reset_mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $acao = $_POST['acao'] ?? '';

    // ---- AÇÃO: LOGIN ----
    if ($acao === 'login') {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Preencha todos os campos!']);
            exit;
        }

        $usuario = getLogin($email, $senha);

        if ($usuario) {
            $_SESSION['usuario_logado'] = $usuario;
            
            echo json_encode(['sucesso' => true, 'mensagem' => 'Login efetuado com sucesso!']);
        } else {
            echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail ou senha incorretos.']);
        }
        exit;
    }

    // ---- AÇÃO: CADASTRO ----
    if ($acao === 'cadastro') {
        $nome  = trim($_POST['nome'] ?? '');
        $cpf   = trim($_POST['documento'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($nome) || empty($cpf) || empty($email) || empty($senha)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Todos os campos são obrigatórios!']);
            exit;
        }

        $novoUsuario = createUser($nome, $cpf, $email, $senha);

        if ($novoUsuario) {
            $_SESSION['usuario_logado'] = $novoUsuario;

            echo json_encode(['sucesso' => true, 'mensagem' => 'Conta criada com sucesso!']);
        } else {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao criar conta. Verifique se o CPF ou E-mail já existem.']);
        }
        exit;
    }

    // ---- AÇÕES: RECUPERAÇÃO DE SENHA ----
    if ($acao === 'solicitar_recuperacao') {
        $cpf = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', trim($_POST['cpf'] ?? '')));
        $recuperacao = $cpf !== '' ? createPasswordReset($cpf) : false;

        if (is_array($recuperacao) && empty($recuperacao['limitado'])) {
            $appUrl = rtrim(trim((string) getenv('APP_URL')), '/');
            $esquema = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));

            if ($appUrl !== '' && filter_var($appUrl, FILTER_VALIDATE_URL) && in_array($esquema, ['http', 'https'], true)) {
                $resetUrl = $appUrl . '/src/pages/redefinir_senha.php?token=' . rawurlencode($recuperacao['token']);

                if (!sendPasswordResetEmail($recuperacao['email'], $recuperacao['nome'], $resetUrl)) {
                    invalidatePasswordReset($recuperacao['token']);
                }
            } else {
                error_log('solicitar_recuperacao -> APP_URL não está configurada como uma URL http ou https válida.');
                invalidatePasswordReset($recuperacao['token']);
            }
        }

        echo json_encode(['sucesso' => true, 'mensagem' => 'Se o CPF estiver cadastrado, enviaremos um link de recuperação para o e-mail da conta.']);
        exit;
    }

    if ($acao === 'redefinir_senha') {
        $token = trim($_POST['token'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (!preg_match('/^[a-f0-9]{64}$/i', $token) || strlen($senha) < 8) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Link inválido ou expirado. Solicite uma nova recuperação.']);
            exit;
        }

        if (resetPasswordWithToken($token, $senha)) {
            echo json_encode(['sucesso' => true, 'mensagem' => 'Senha redefinida. Você já pode entrar com a nova senha.']);
        } else {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Link inválido ou expirado. Solicite uma nova recuperação.']);
        }
        exit;
    }

    // ---- AÇÕES: ATUALIZAR E EXCLUIR USUÁRIO ----
    if ($acao === 'atualizar_usuario' || $acao === 'excluir_usuario') {
        $tokenSessao = $_SESSION['csrf_token'] ?? '';
        $tokenRecebido = $_POST['csrf_token'] ?? '';

        if (!isset($_SESSION['usuario_logado']) || !($_SESSION['usuario_logado'] instanceof User)) {
            http_response_code(401);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para continuar.']);
            exit;
        }

        if ($tokenSessao === '' || !hash_equals($tokenSessao, $tokenRecebido)) {
            http_response_code(403);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.']);
            exit;
        }

        $usuarioAtual = $_SESSION['usuario_logado'];
        $idUsuario = (int) $usuarioAtual->getId();

        if ($acao === 'atualizar_usuario') {
            $nome = trim($_POST['nome'] ?? '');
            $cpf = trim($_POST['cpf'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';

            if ($nome === '' || $cpf === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Informe nome, documento e um e-mail válido.']);
                exit;
            }

            $usuarioAtualizado = updateUser($idUsuario, $nome, $cpf, $email, $senha);

            if ($usuarioAtualizado) {
                $_SESSION['usuario_logado'] = $usuarioAtualizado;
                echo json_encode(['sucesso' => true, 'mensagem' => 'Dados atualizados com sucesso!']);
            } else {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível atualizar. Verifique se o e-mail ou documento já estão em uso.']);
            }
            exit;
        }

        if (deleteUser($idUsuario)) {
            $_SESSION = [];
            session_destroy();
            echo json_encode(['sucesso' => true, 'mensagem' => 'Conta excluída com sucesso.']);
        } else {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível excluir sua conta.']);
        }
        exit;
    }

    // ---- AÇÃO: LOGOUT ----
    if ($acao === 'logout') {
        $_SESSION = array();
        session_destroy();

        echo json_encode(['sucesso' => true, 'mensagem' => 'Saída efetuada com sucesso!']);
        exit;
    }
}

// Se alguém tentar acessar esse arquivo direto pelo navegador:
echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida.']);

?>