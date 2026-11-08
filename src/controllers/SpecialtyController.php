<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../repositories/SpecialtyRepository.php';

class SpecialtyController
{
    private SpecialtyRepository $specialties;

    public function __construct(SpecialtyRepository $specialties)
    {
        $this->specialties = $specialties;
    }

    public function index(array $input = []): ControllerResponse
    {
        $specialties = array_map(static fn(Specialty $specialty): array => $specialty->toArray(), $this->specialties->findAll());
        return new ControllerResponse(['sucesso' => true, 'especialidades' => $specialties]);
    }

    public function createDrafts(array $input): ControllerResponse
    {
        if ($response = $this->authorizeWrite($input)) {
            return $response;
        }

        $names = json_decode($input['novas_especialidades'] ?? '[]', true);
        if (!is_array($names)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'A lista de especialidades é inválida.'], 400);
        }

        foreach ($names as $name) {
            if (!is_string($name) || !$this->validName(trim($name))) {
                return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe nomes de especialidade válidos com até 100 caracteres.'], 400);
            }
        }

        $specialties = $this->specialties->createOrFindMany($names);
        if ($specialties === false) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível salvar as novas especialidades.'], 409);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Especialidades salvas.', 'especialidades' => $specialties]);
    }

    public function create(array $input): ControllerResponse
    {
        if ($response = $this->authorizeWrite($input)) {
            return $response;
        }

        $name = trim($input['nomeEspecialidade'] ?? '');
        if (!$this->validName($name)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe um nome com até 100 caracteres.'], 400);
        }

        if ($this->specialties->nameExists($name)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Essa especialidade já está cadastrada.'], 409);
        }

        $specialty = $this->specialties->create($name);
        if (!$specialty) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível cadastrar a especialidade.'], 500);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Especialidade cadastrada.', 'especialidade' => $specialty->toArray()]);
    }

    public function update(array $input): ControllerResponse
    {
        if ($response = $this->authorizeWrite($input)) {
            return $response;
        }

        $id = filter_var($input['idEspecialidade'] ?? null, FILTER_VALIDATE_INT);
        $name = trim($input['nomeEspecialidade'] ?? '');
        if (!$id || !$this->validName($name)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe uma especialidade válida.'], 400);
        }

        if ($this->specialties->nameExists($name, $id)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Essa especialidade já está cadastrada.'], 409);
        }

        $specialty = $this->specialties->update($id, $name);
        if (!$specialty) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Especialidade não encontrada.'], 404);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Especialidade atualizada.', 'especialidade' => $specialty->toArray()]);
    }

    public function delete(array $input): ControllerResponse
    {
        if ($response = $this->authorizeWrite($input)) {
            return $response;
        }

        $id = filter_var($input['idEspecialidade'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || !$this->specialties->findById($id)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Especialidade não encontrada.'], 404);
        }

        if ($this->specialties->hasProviders($id)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não é possível excluir uma especialidade usada por prestadores.'], 409);
        }

        if (!$this->specialties->delete($id)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível excluir a especialidade.'], 409);
        }

        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Especialidade excluída.']);
    }

    private function authorizeWrite(array $input): ControllerResponse | false
    {
        if (!$this->authenticatedUser()) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
        }

        $tokenSessao = $_SESSION['csrf_token'] ?? '';
        $tokenRecebido = $input['csrf_token'] ?? '';
        if ($tokenSessao === '' || !hash_equals($tokenSessao, $tokenRecebido)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página.'], 403);
        }

        return false;
    }

    private function authenticatedUser(): User | false
    {
        $usuario = $_SESSION['usuario_logado'] ?? null;
        return $usuario instanceof User ? $usuario : false;
    }

    private function validName(string $name): bool
    {
        $length = preg_match_all('/./us', $name);
        return $name !== '' && $length !== false && $length <= 100;
    }
}