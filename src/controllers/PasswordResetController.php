<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../repositories/PasswordResetTokenRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../scripts/auth_session.php';
require_once __DIR__ . '/../scripts/config.php';
require_once __DIR__ . '/../scripts/password_reset_mailer.php';

class PasswordResetController
{
    private PasswordResetTokenRepository $tokens;
    private ?UserRepository $users;

    public function __construct(PasswordResetTokenRepository $tokens, ?UserRepository $users = null)
    {
        $this->tokens = $tokens;
        $this->users = $users;
    }

    public function request(array $input): ControllerResponse
    {
        $documento = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', trim($input['cpf'] ?? '')));
        $recuperacao = $documento !== '' ? $this->tokens->createForDocument($documento) : false;

        if ($recuperacao === false) {
            error_log('PasswordResetController::request -> nenhum token criado (documento sem conta correspondente ou falha interna).');
        } elseif (!empty($recuperacao['limitado'])) {
            error_log('PasswordResetController::request -> solicitação limitada pelo intervalo de 1 minuto.');
        } else {
            $baseUrl = rtrim(roppinBaseUrl(), '/');

            if ($baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                $resetUrl = $baseUrl . '/src/pages/redefinir_senha.php?token=' . rawurlencode($recuperacao['token']);

                if (!sendPasswordResetEmail($recuperacao['email'], $recuperacao['nome'], $resetUrl)) {
                    $this->tokens->invalidate($recuperacao['token']);
                    error_log('PasswordResetController::request -> SMTP não aceitou a mensagem de recuperação.');
                } else {
                    error_log('PasswordResetController::request -> SMTP aceitou a mensagem de recuperação.');
                }
            } else {
                error_log('PasswordResetController::request -> não foi possível derivar a URL base da requisição.');
                $this->tokens->invalidate($recuperacao['token']);
            }
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Se o CPF estiver cadastrado, enviaremos um link de recuperação para o e-mail da conta.']);
    }

    public function reset(array $input): ControllerResponse
    {
        $token = trim($input['token'] ?? '');
        $senha = $input['senha'] ?? '';

        if (!preg_match('/^[a-f0-9]{64}$/i', $token) || strlen($senha) < 8) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Link inválido ou expirado. Solicite uma nova recuperação.'], 400);
        }

        $usuario = $this->tokens->resetPasswordWithUser($token, $senha);
        if (!$usuario) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Link inválido ou expirado. Solicite uma nova recuperação.'], 400);
        }

        if ($usuario['eraConvidado']) {
            $conta = $this->users ? $this->users->findById((int) $usuario['idUsuario']) : false;
            $conta = $conta ?: new User([
                'idUsuario' => $usuario['idUsuario'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'ehConvidado' => 0
            ]);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['usuario_logado'] = $conta;
            clearChatGuestCookie();

            return new ControllerResponse([
                'sucesso' => true,
                'mensagem' => 'Conta criada! Suas conversas foram preservadas.',
                'redirecionar' => '../../src/pages/mensagens.php'
            ]);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Senha redefinida. Você já pode entrar com a nova senha.']);
    }
}