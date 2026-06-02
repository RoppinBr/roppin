<?php
class User {
  // Properties
  private $id;
  private $nome;
  private $cpf;
  private $email;
  private $senha; // Em BCRYPT

  public function __construct(array $dados = []) {
    if (!empty($dados)) {
        $this->id = $dados['id'] ?? null;
        $this->nome = $dados['nome'] ?? null;
        $this->cpf = $dados['cpf'] ?? null;
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
    return $this->cpf;
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