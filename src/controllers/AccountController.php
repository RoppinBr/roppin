<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class AccountController
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function update(array $input): ControllerResponse
    {
        $usuario = $this->authenticatedUser();
        if (!$usuario) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
        }

        if (!$this->validCsrfToken($input['csrf_token'] ?? '')) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 403);
        }

        $nome = trim($input['nome'] ?? '');
        $documento = trim($input['cpf'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha = $input['senha'] ?? '';
        $confirmacaoSenha = $input['confirmar_senha'] ?? '';

        if ($nome === '' || $documento === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe nome, documento e um e-mail válido.'], 400);
        }

        if ($senha !== '' || $confirmacaoSenha !== '') {
            if (strlen($senha) < 8) {
                return new ControllerResponse(['sucesso' => false, 'mensagem' => 'A nova senha precisa ter pelo menos 8 caracteres.'], 400);
            }

            if (!hash_equals($senha, $confirmacaoSenha)) {
                return new ControllerResponse(['sucesso' => false, 'mensagem' => 'A confirmação da nova senha não confere.'], 400);
            }
        }

        $atualizado = $this->users->update((int) $usuario->getId(), $nome, $documento, $email, $senha);
        if (!$atualizado) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível atualizar. Verifique se o e-mail ou documento já estão em uso.'], 409);
        }

        $_SESSION['usuario_logado'] = $atualizado;
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Dados atualizados com sucesso!']);
    }

    public function delete(array $input): ControllerResponse
    {
        $usuario = $this->authenticatedUser();
        if (!$usuario) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
        }

        if (!$this->validCsrfToken($input['csrf_token'] ?? '')) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 403);
        }

        $senhaAtual = $input['senha_atual'] ?? '';
        if ($senhaAtual === '' || !$this->users->findByEmailAndPassword($usuario->getEmail(), $senhaAtual)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'A senha atual não confere. A conta não foi excluída.'], 403);
        }

        if (!$this->users->delete((int) $usuario->getId())) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível excluir sua conta.'], 409);
        }

        $_SESSION = [];
        session_destroy();
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Conta excluída com sucesso.']);
    }

    private function authenticatedUser(): User | false
    {
        $usuario = $_SESSION['usuario_logado'] ?? null;
        return $usuario instanceof User ? $usuario : false;
    }

    private function validCsrfToken(string $tokenRecebido): bool
    {
        $tokenSessao = $_SESSION['csrf_token'] ?? '';
        return $tokenSessao !== '' && hash_equals($tokenSessao, $tokenRecebido);
    }
}