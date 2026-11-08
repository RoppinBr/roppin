<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../repositories/PasswordResetTokenRepository.php';
require_once __DIR__ . '/../scripts/password_reset_mailer.php';

class PasswordResetController
{
    private PasswordResetTokenRepository $tokens;

    public function __construct(PasswordResetTokenRepository $tokens)
    {
        $this->tokens = $tokens;
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
            $appUrl = rtrim(trim((string) getenv('APP_URL')), '/');
            $esquema = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));

            if ($appUrl !== '' && filter_var($appUrl, FILTER_VALIDATE_URL) && in_array($esquema, ['http', 'https'], true)) {
                $resetUrl = $appUrl . '/src/pages/redefinir_senha.php?token=' . rawurlencode($recuperacao['token']);

                if (!sendPasswordResetEmail($recuperacao['email'], $recuperacao['nome'], $resetUrl)) {
                    $this->tokens->invalidate($recuperacao['token']);
                    error_log('PasswordResetController::request -> SMTP não aceitou a mensagem de recuperação.');
                } else {
                    error_log('PasswordResetController::request -> SMTP aceitou a mensagem de recuperação.');
                }
            } else {
                error_log('PasswordResetController::request -> APP_URL inválida.');
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

        if (!$this->tokens->resetPassword($token, $senha)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Link inválido ou expirado. Solicite uma nova recuperação.'], 400);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Senha redefinida. Você já pode entrar com a nova senha.']);
    }
}