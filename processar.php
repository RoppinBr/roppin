<?php
require_once __DIR__ . '/src/scripts/auth_session.php';
startRoppinSession();

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
    'excluir_usuario' => ['account', 'delete'],
    'listar_especialidades' => ['specialty', 'index'],
    'criar_especialidades_rascunho' => ['specialty', 'createDrafts'],
    'criar_especialidade' => ['specialty', 'create'],
    'atualizar_especialidade' => ['specialty', 'update'],
    'excluir_especialidade' => ['specialty', 'delete'],
    'cadastrar_prestador' => ['provider', 'register'],
    'perfil_prestador' => ['provider', 'profile'],
    'salvar_especialidades_prestador' => ['provider', 'saveSpecialties'],
    'chat_listar_prestadores' => ['chat', 'providers'],
    'chat_iniciar_conversa' => ['chat', 'start'],
    'chat_conversas' => ['chat', 'conversations'],
    'chat_conversa' => ['chat', 'conversation'],
    'chat_enviar' => ['chat', 'send'],
    'chat_encerrar_conversa' => ['chat', 'close'],
    'chat_reabrir_conversa' => ['chat', 'reopen'],
    'chat_avaliar' => ['chat', 'rate'],
    'chat_marcar_lida' => ['chat', 'read'],
    'chat_focar_conversa' => ['chat', 'focus'],
    'chat_limpar_foco' => ['chat', 'blur'],
    'chat_nao_lidas' => ['chat', 'unread']
];

if (!isset($rotas[$acao])) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida.']);
    exit;
}

require_once __DIR__ . '/src/scripts/conexao.php';
require_once __DIR__ . '/src/repositories/UserRepository.php';
require_once __DIR__ . '/src/repositories/RememberMeTokenRepository.php';
require_once __DIR__ . '/src/repositories/PasswordResetTokenRepository.php';
require_once __DIR__ . '/src/repositories/SpecialtyRepository.php';
require_once __DIR__ . '/src/repositories/ProviderRepository.php';
require_once __DIR__ . '/src/repositories/ConversationRepository.php';
require_once __DIR__ . '/src/repositories/MessageRepository.php';
require_once __DIR__ . '/src/repositories/ReviewRepository.php';
require_once __DIR__ . '/src/services/ChatCryptoService.php';
require_once __DIR__ . '/src/services/ChatService.php';
require_once __DIR__ . '/src/controllers/AuthController.php';
require_once __DIR__ . '/src/controllers/AccountController.php';
require_once __DIR__ . '/src/controllers/PasswordResetController.php';
require_once __DIR__ . '/src/controllers/SpecialtyController.php';
require_once __DIR__ . '/src/controllers/ProviderController.php';
require_once __DIR__ . '/src/controllers/ChatController.php';

$db = getConexao();
if (!$db) {
    http_response_code(503);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Serviço temporariamente indisponível.']);
    exit;
}

$users = new UserRepository($db);
$rememberTokens = new RememberMeTokenRepository($db);
$tokens = new PasswordResetTokenRepository($db);
$specialties = new SpecialtyRepository($db);
$providers = new ProviderRepository($db);
$chatService = new ChatService(
    new ConversationRepository($db),
    new MessageRepository($db),
    new ChatCryptoService(),
    new ReviewRepository($db)
);
$controllers = [
    'auth' => new AuthController($users, $rememberTokens),
    'account' => new AccountController($users, $rememberTokens),
    'password_reset' => new PasswordResetController($tokens),
    'specialty' => new SpecialtyController($specialties),
    'provider' => new ProviderController($providers, $specialties),
    'chat' => new ChatController($chatService)
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