<?php
require_once __DIR__ . '/Model.php';

class User extends Model
{
    public static function table(): string { return 'Usuario'; }
    public static function primaryKey(): string { return 'idUsuario'; }
    public static function fields(): array
    {
        return ['idUsuario', 'idPessoa', 'tipoPessoa', 'cpf', 'nome', 'cnpj', 'razaoSocial', 'email', 'senha', 'media_reputacao'];
    }

    public function __construct(array $attributes = [])
    {
        if (!isset($attributes['idUsuario']) && isset($attributes['id'])) {
            $attributes['idUsuario'] = $attributes['id'];
        }

        parent::__construct($attributes);
    }

    public function __serialize(): array
    {
        $attributes = $this->attributes;
        unset($attributes['senha']);
        return ['attributes' => $attributes];
    }

    public function __unserialize(array $data): void
    {
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        if ($attributes === []) {
            foreach ($data as $key => $value) {
                $parts = explode("\0", (string) $key);
                $field = end($parts);
                if (in_array($field, static::fields(), true) || $field === 'id') {
                    $attributes[$field] = $value;
                }
            }
        }

        if (!isset($attributes['idUsuario']) && isset($attributes['id'])) {
            $attributes['idUsuario'] = $attributes['id'];
        }

        $allowed = array_flip(static::fields());
        $this->attributes = array_intersect_key($attributes, $allowed);
    }

    public function setId(int | string $id): void
    {
        $this->set('idUsuario', $id);
    }

    public function getId(): mixed
    {
        return $this->get('idUsuario');
    }

    public function getIdPessoa(): mixed
    {
        return $this->get('idPessoa');
    }

    public function getTipoPessoa(): mixed
    {
        return $this->get('tipoPessoa');
    }

    public function setNome(string $nome): void
    {
        $campo = $this->getTipoPessoa() === 'PJ' ? 'razaoSocial' : 'nome';
        $this->set($campo, $nome);
    }

    public function getNome(): mixed
    {
        return $this->get('nome') ?? $this->get('razaoSocial');
    }

    public function setCpf(string $cpf): void
    {
        $this->set('cpf', $cpf);
        $this->set('cnpj', null);
    }

    public function getCpf(): mixed
    {
        return $this->get('cpf') ?? $this->get('cnpj');
    }

    public function setCnpj(string $cnpj): void
    {
        $this->set('cnpj', $cnpj);
        $this->set('cpf', null);
    }

    public function getCnpj(): mixed
    {
        return $this->get('cnpj');
    }

    public function getRazaoSocial(): mixed
    {
        return $this->get('razaoSocial');
    }

    public function setEmail(string $email): void
    {
        $this->set('email', $email);
    }

    public function getEmail(): mixed
    {
        return $this->get('email');
    }

    public function setSenha(string $senha): void
    {
        $this->set('senha', $senha);
    }

    public function getSenha(): mixed
    {
        return $this->get('senha');
    }

    public function getMediaReputacao(): mixed
    {
        return $this->get('media_reputacao');
    }
}