<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once './src/scripts/bd_control.php';

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