# Roppin

Aplicação web de marketplace de serviços. O projeto inclui a página inicial, cadastro e login, edição e exclusão da própria conta e recuperação de senha por link enviado por e-mail.

## Requisitos

- PHP 8.0 ou superior.
- MySQL ou MariaDB e a extensão PHP `mysqli`.
- Extensão PHP `openssl` para conexão SMTP com TLS.
- Navegador com JavaScript habilitado.

O PHPMailer está incluído em `src/libs/PHPMailer`; não é necessário Composer.

## Configuração local

No PowerShell, na pasta raiz do projeto, crie o arquivo local de configuração:

```powershell
Copy-Item .env.example .env
```

Edite o `.env` e preencha as credenciais do banco. Não envie esse arquivo ao Git nem compartilhe suas senhas.

O sistema espera uma tabela `Usuario` com os campos `id`, `nome`, `cpf`, `email` e `senha`. Se ela ainda não existir, um esquema compatível é:

```sql
CREATE TABLE Usuario (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    cpf VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_cpf (cpf),
    UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Inicie o servidor PHP embutido:

```powershell
php -S localhost:8000
```

Acesse <http://localhost:8000> no navegador. Se o PHP não encontrar `mysqli`, habilite `extension=mysqli` no `php.ini` ativo e reinicie o servidor.

## Recuperação de senha

Preencha no `.env` as variáveis `APP_URL` e `SMTP_*` usando `.env.example` como referência. Para Gmail, use `smtp.gmail.com`, porta `587`, criptografia `tls`, o endereço completo em `SMTP_USER` e uma senha de app em `SMTP_PASS`. Não use a senha normal da conta Google.

Em ambiente local, `APP_URL=http://localhost:8000` serve para testar no mesmo computador. Em produção, configure a URL pública HTTPS do site. O servidor também precisa permitir conexões de saída para o SMTP escolhido.

Na primeira solicitação de recuperação, a aplicação cria a tabela `PasswordResetTokens`; o usuário do banco precisa ter permissão `CREATE TABLE`. A resposta não revela se o CPF está cadastrado. O token é armazenado como hash, expira em 30 minutos, só pode ser usado uma vez e há limite de uma solicitação por minuto por conta.

## Estrutura principal

- `index.php`: página inicial, login, cadastro e menu de conta.
- `processar.php`: endpoint das ações de autenticação, conta e recuperação.
- `src/pages/conta.php`: edição e exclusão da conta autenticada.
- `src/pages/redefinir_senha.php`: formulário aberto pelo link de recuperação.
- `src/scripts/bd_control.php`: consultas e operações no banco.
- `src/scripts/password_reset_mailer.php`: envio de e-mail por SMTP.
- `src/libs/PHPMailer`: biblioteca PHPMailer incluída localmente.
- `.env.example`: nomes e exemplos das variáveis de configuração.