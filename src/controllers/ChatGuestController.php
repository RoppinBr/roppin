<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../scripts/chat_guest.php';
require_once __DIR__ . '/../scripts/config.php';
require_once __DIR__ . '/../scripts/password_reset_mailer.php';
require_once __DIR__ . '/../repositories/PasswordResetTokenRepository.php';

class ChatGuestController
{
    private UserRepository $users;
    private ChatGuestSessionRepository $sessions;
    private PasswordResetTokenRepository $tokens;

    public function __construct(
        UserRepository $users,
        ChatGuestSessionRepository $sessions,
        PasswordResetTokenRepository $tokens
    ) {
        $this->users = $users;
        $this->sessions = $sessions;
        $this->tokens = $tokens;
    }

    public function linkEmail(array $input): ControllerResponse
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $receivedToken = (string) ($input['csrf_token'] ?? '');
        if ($sessionToken === '' || !hash_equals($sessionToken, $receivedToken)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 403);
        }

        $nome = trim((string) ($input['nome'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));

        if ($nome === '' || preg_match('/^.{1,120}$/us', $nome) !== 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe nome e e-mail válidos.'], 400);
        }

        $cookie = (string) ($_COOKIE[ROPPIN_GUEST_COOKIE] ?? '');
        $guestId = $this->sessions->findUserIdByToken($cookie);
        if ($guestId === false) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão de visitante expirou. Abra o chat novamente.'], 401);
        }

        $guest = $this->users->findById($guestId);
        if (!$guest || !$guest->isGuest()) {
            clearChatGuestCookie();
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Esta conversa já está vinculada a uma conta.'], 409);
        }

        $existente = $this->users->findByEmail($email);
        if ($existente && (int) $existente->getId() !== $guestId) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Essa conta já existe. Entre na sua conta para continuar.'], 409);
        }

        if (!$this->users->updateGuestContact($guestId, $nome, $email)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível salvar seus dados agora.'], 409);
        }

        $recuperacao = $this->tokens->createForUser($guestId);
        if (!$recuperacao) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível gerar o link de confirmação agora.'], 500);
        }
        if (!empty($recuperacao['limitado'])) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Aguarde um minuto antes de solicitar outro link.'], 429);
        }

        $baseUrl = rtrim(roppinBaseUrl(), '/');
        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->tokens->invalidate($recuperacao['token']);
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível gerar o link de confirmação agora.'], 500);
        }

        $resetUrl = $baseUrl . '/src/pages/redefinir_senha.php?token=' . rawurlencode($recuperacao['token']);
        if (!sendPasswordResetEmail($email, $nome, $resetUrl)) {
            $this->tokens->invalidate($recuperacao['token']);
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível enviar o e-mail. Tente novamente em instantes.'], 503);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Enviamos um link para definir sua senha. Suas mensagens ficarão salvas nesta conta.']);
    }
}
