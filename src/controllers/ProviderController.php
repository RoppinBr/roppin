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

    public function register(array $input): ControllerResponse
    {
        $user = $_SESSION['usuario_logado'] ?? null;
        if (!$user instanceof User) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
        }

        $csrfSession = $_SESSION['csrf_token'] ?? '';
        $csrfInput = $input['csrf_token'] ?? '';
        if ($csrfSession === '' || !hash_equals($csrfSession, $csrfInput)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página.'], 403);
        }

        $specialtyId = filter_var($input['idEspecialidade'] ?? null, FILTER_VALIDATE_INT);
        $hourlyRate = filter_var($input['valor_hora'] ?? null, FILTER_VALIDATE_FLOAT);

        if (!$specialtyId || !$this->specialties->findById($specialtyId)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Selecione uma especialidade cadastrada.'], 400);
        }

        if ($hourlyRate === false || $hourlyRate <= 0 || $hourlyRate > 99999999.99) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe um valor por hora maior que zero.'], 400);
        }

        $userId = (int) $user->getId();
        if ($this->providers->exists($userId)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Esta conta já está cadastrada como prestador.'], 409);
        }

        $provider = $this->providers->create($userId, $specialtyId, $hourlyRate);
        if (!$provider) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível concluir o cadastro de prestador.'], 409);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Cadastro de prestador concluído.', 'prestador' => $provider->toArray()]);
    }
}