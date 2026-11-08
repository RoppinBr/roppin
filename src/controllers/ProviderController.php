<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../repositories/ProviderRepository.php';
require_once __DIR__ . '/../repositories/SpecialtyRepository.php';

class ProviderController
{
    private ProviderRepository $providers;
    private SpecialtyRepository $specialties;

    public function __construct(ProviderRepository $providers, SpecialtyRepository $specialties)
    {
        $this->providers = $providers;
        $this->specialties = $specialties;
    }

    public function profile(array $input = []): ControllerResponse
    {
        $user = $_SESSION['usuario_logado'] ?? null;
        if (!$user instanceof User) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
        }

        $userId = (int) $user->getId();
        $provider = $this->providers->findByUserId($userId);

        return new ControllerResponse([
            'sucesso' => true,
            'cadastrado' => $provider !== false,
            'prestador' => $provider ? $provider->toArray() : null,
            'especialidades' => $provider ? $this->providers->findSpecialtiesByUserId($userId) : []
        ]);
    }

    public function register(array $input): ControllerResponse
    {
        $user = $_SESSION['usuario_logado'] ?? null;
        if (!$user instanceof User) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
        }

        if (!$this->validCsrfToken($input['csrf_token'] ?? '')) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página.'], 403);
        }

        $hourlyRate = filter_var($input['valor'] ?? null, FILTER_VALIDATE_FLOAT);
        $billingPeriod = $input['periodo_cobranca'] ?? '';

        if ($hourlyRate === false || $hourlyRate <= 0 || $hourlyRate > 99999999.99) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe um valor cobrado válido maior que zero.'], 400);
        }

        if (!in_array($billingPeriod, ['hora', 'dia', 'servico'], true)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Selecione um período de cobrança válido.'], 400);
        }

        $userId = (int) $user->getId();
        if ($this->providers->exists($userId)) {
            $provider = $this->providers->updatePricing($userId, $hourlyRate, $billingPeriod);
            if (!$provider) {
                return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível atualizar o valor de cobrança.'], 409);
            }

            return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Valor e período de cobrança atualizados.', 'prestador' => $provider->toArray()]);
        }

        $specialtyIds = json_decode($input['especialidade_ids'] ?? '[]', true);
        if (!is_array($specialtyIds) || $specialtyIds === []) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Adicione pelo menos uma especialidade antes de concluir o cadastro.'], 400);
        }

        foreach ($specialtyIds as $specialtyId) {
            if (!is_int($specialtyId) || $specialtyId <= 0 || !$this->specialties->findById($specialtyId)) {
                return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Uma especialidade selecionada não existe.'], 400);
            }
        }

        $provider = $this->providers->create($userId, $hourlyRate, $billingPeriod, $specialtyIds, $this->specialties);
        if (!$provider) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível concluir o cadastro de prestador.'], 409);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Cadastro de prestador concluído.', 'prestador' => $provider->toArray()]);
    }

    public function saveSpecialties(array $input): ControllerResponse
    {
        $user = $_SESSION['usuario_logado'] ?? null;
        if (!$user instanceof User) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
        }

        if (!$this->validCsrfToken($input['csrf_token'] ?? '')) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página.'], 403);
        }

        $userId = (int) $user->getId();
        if (!$this->providers->exists($userId)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Conclua primeiro o cadastro de prestador.'], 409);
        }

        $specialtyIds = json_decode($input['especialidade_ids'] ?? '[]', true);
        $newNames = json_decode($input['novas_especialidades'] ?? '[]', true);
        if (!is_array($specialtyIds) || !is_array($newNames)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'A lista de especialidades é inválida.'], 400);
        }

        foreach ($specialtyIds as $id) {
            if (!is_int($id) || $id <= 0 || !$this->specialties->findById($id)) {
                return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Uma especialidade selecionada não existe.'], 400);
            }
        }

        foreach ($newNames as $name) {
            if (!is_string($name) || !$this->specialties->isValidName(trim($name))) {
                return new ControllerResponse(['sucesso' => false, 'mensagem' => 'O nome de uma nova especialidade é inválido.'], 400);
            }
        }

        $saved = $this->providers->saveSpecialties($userId, $specialtyIds, $newNames, $this->specialties);
        if ($saved === false) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível salvar as especialidades. Tente novamente.'], 409);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Especialidades salvas.', 'especialidades' => $saved]);
    }

    private function validCsrfToken(string $token): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
}