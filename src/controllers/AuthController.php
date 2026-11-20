<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/RememberMeTokenRepository.php';
require_once __DIR__ . '/../repositories/ChatGuestSessionRepository.php';
require_once __DIR__ . '/../repositories/ChatGuestTransferRepository.php';
require_once __DIR__ . '/../scripts/chat_guest.php';

class AuthController
{
    private UserRepository $users;
    private RememberMeTokenRepository $rememberTokens;
    private ?ChatGuestSessionRepository $guestSessions;
    private ?ChatGuestTransferRepository $guestTransfers;

    public function __construct(
        UserRepository $users,
        RememberMeTokenRepository $rememberTokens,
        ?ChatGuestSessionRepository $guestSessions = null,
        ?ChatGuestTransferRepository $guestTransfers = null
    ) {
        $this->users = $users;
        $this->rememberTokens = $rememberTokens;
        $this->guestSessions = $guestSessions;
        $this->guestTransfers = $guestTransfers;
    }

    public function login(array $input): ControllerResponse
    {
        $email = trim($input['email'] ?? '');
        $senha = $input['senha'] ?? '';

        if ($email === '' || $senha === '') {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Preencha todos os campos!'], 400);
        }

        $usuario = $this->users->findByEmailAndPassword($email, $senha);
        if (!$usuario) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'E-mail ou senha incorretos.'], 401);
        }

        $guest = $this->activeGuest();
        if ($guest && (int) $guest->getId() !== (int) $usuario->getId()) {
            if (!$this->guestTransfers || !$this->guestTransfers->transferToUser((int) $guest->getId(), (int) $usuario->getId())) {
                return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível transferir as conversas deste visitante.'], 409);
            }
            $this->clearGuestIdentity();
        } elseif ($guest) {
            $this->clearGuestIdentity();
        }

        $this->rememberTokens->revoke($_COOKIE[ROPPIN_REMEMBER_COOKIE] ?? '');
        clearRememberCookie();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['usuario_logado'] = $usuario;

        if (filter_var($input['lembrar_me'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $token = $this->rememberTokens->issue((int) $usuario->getId());
            if ($token) {
                setRememberCookie($token['cookie'], $token['expires']);
            }
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Login efetuado com sucesso!']);
    }

    public function register(array $input): ControllerResponse
    {
        $nome = trim($input['nome'] ?? '');
        $documento = trim($input['documento'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha = $input['senha'] ?? '';

        if ($nome === '' || $documento === '' || $email === '' || $senha === '') {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Todos os campos são obrigatórios!'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe um e-mail válido.'], 400);
        }

        $guest = $this->activeGuest();
        $existente = $this->users->findByEmail($email);
        if ($existente && (!$guest || (int) $existente->getId() !== (int) $guest->getId())) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Esse e-mail já está associado a uma conta. Entre para continuar.'], 409);
        }

        if ($guest) {
            $usuario = $this->users->promoteGuest((int) $guest->getId(), $nome, $documento, $email, $senha);
            if ($usuario) {
                $this->clearGuestIdentity();
            }
        } else {
            $usuario = $this->users->create($nome, $documento, $email, $senha);
        }

        if (!$usuario) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Erro ao criar conta. Verifique se o CPF ou E-mail já existem.'], 409);
        }

        $this->rememberTokens->revoke($_COOKIE[ROPPIN_REMEMBER_COOKIE] ?? '');
        clearRememberCookie();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['usuario_logado'] = $usuario;
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Conta criada com sucesso!']);
    }

    private function activeGuest(): User | false
    {
        if (!$this->guestSessions) {
            return false;
        }

        $cookie = (string) ($_COOKIE[ROPPIN_GUEST_COOKIE] ?? '');
        $guestId = $this->guestSessions->findUserIdByToken($cookie);
        if ($guestId === false) {
            return false;
        }

        $guest = $this->users->findById($guestId);
        return $guest && $guest->isGuest() ? $guest : false;
    }

    private function clearGuestIdentity(): void
    {
        if ($this->guestSessions) {
            $cookie = (string) ($_COOKIE[ROPPIN_GUEST_COOKIE] ?? '');
            $id = $this->guestSessions->findUserIdByToken($cookie);
            if ($id !== false) {
                $this->guestSessions->deleteByUser($id);
            }
        }
        clearChatGuestCookie();
    }

    public function logout(): ControllerResponse
    {
        $this->rememberTokens->revoke($_COOKIE[ROPPIN_REMEMBER_COOKIE] ?? '');
        clearRememberCookie();
        $_SESSION = [];
        session_destroy();
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Saída efetuada com sucesso!']);
    }
}