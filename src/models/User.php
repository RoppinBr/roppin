<?php
class User {
  // Properties
  private $id;
  private $idPessoa;
  private $tipoPessoa;
  private $nome;
  private $cpf;
  private $cnpj;
  private $razaoSocial;
  private $email;
  private $senha; // Em BCRYPT

  public function __construct(array $dados = []) {
    if (!empty($dados)) {
        $this->id = $dados['id'] ?? $dados['idUsuario'] ?? null;
        $this->idPessoa = $dados['idPessoa'] ?? null;
        $this->tipoPessoa = $dados['tipoPessoa'] ?? null;
        $this->nome = $dados['nome'] ?? $dados['razaoSocial'] ?? null;
        $this->cpf = $dados['cpf'] ?? null;
        $this->cnpj = $dados['cnpj'] ?? null;
        $this->razaoSocial = $dados['razaoSocial'] ?? null;
        $this->email = $dados['email'] ?? null;
        $this->senha = $dados['senha'] ?? null;
    }
  }

  public function setId(string $id) {
    $this->id = $id;
  }
  public function getId() {
    return $this->id;
  }

  public function getIdPessoa() {
    return $this->idPessoa;
  }

  public function getTipoPessoa() {
    return $this->tipoPessoa;
  }

  public function setNome(string $nome) {
    $this->nome = $nome;
  }

  public function getNome() {
    return $this->nome;
  }

  public function setCpf(string $cpf) {
    $this->cpf = $cpf;
  }

  public function getCpf() {
    return $this->cpf ?? $this->cnpj;
  }

  public function getCnpj() {
    return $this->cnpj;
  }

  public function getRazaoSocial() {
    return $this->razaoSocial;
  }

  public function setEmail(string $email) {
    $this->email = $email;
  }

  public function getEmail() {
    return $this->email;
  }

  public function setSenha(string $senha) {
    $this->senha = $senha;
  }

  public function getSenha() {
    return $this->senha;
  }
}
?>