<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida.']);
    exit;
}

$acao = $_POST['acao'] ?? '';
$rotas = [
    'login' => ['auth', 'login'],
    'cadastro' => ['auth', 'register'],
    'logout' => ['auth', 'logout'],
    'solicitar_recuperacao' => ['password_reset', 'request'],
    'redefinir_senha' => ['password_reset', 'reset'],
    'atualizar_usuario' => ['account', 'update'],
    'excluir_usuario' => ['account', 'delete']
];

if (!isset($rotas[$acao])) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida.']);
    exit;
}

require_once __DIR__ . '/src/scripts/conexao.php';
require_once __DIR__ . '/src/repositories/UserRepository.php';
require_once __DIR__ . '/src/repositories/PasswordResetTokenRepository.php';
require_once __DIR__ . '/src/controllers/AuthController.php';
require_once __DIR__ . '/src/controllers/AccountController.php';
require_once __DIR__ . '/src/controllers/PasswordResetController.php';

$db = getConexao();
if (!$db) {
    http_response_code(503);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Serviço temporariamente indisponível.']);
    exit;
}

$users = new UserRepository($db);
$tokens = new PasswordResetTokenRepository($db);
$controllers = [
    'auth' => new AuthController($users),
    'account' => new AccountController($users),
    'password_reset' => new PasswordResetController($tokens)
];
[$controllerName, $method] = $rotas[$acao];

try {
    $response = $controllers[$controllerName]->{$method}($_POST);
} catch (Throwable $e) {
    error_log('processar.php -> ' . $e->getMessage());
    $response = new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível processar a solicitação.'], 500);
}

http_response_code($response->getStatusCode());
echo json_encode($response->getBody(), JSON_UNESCAPED_UNICODE);