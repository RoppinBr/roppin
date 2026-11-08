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

O banco informado em `DB_NAME` precisa existir antes da primeira execução. Se necessário, crie-o no MySQL:

```sql
CREATE DATABASE db_roppin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Depois, aplique as migrations a partir da raiz do projeto:

```powershell
php src/scripts/migrate.php
```

O runner aplica `initial_migration.sql` como schema inicial e os arquivos `database/migrations/*.sql` em ordem. O histórico e o checksum de cada arquivo ficam em `schema_migrations`; quando uma migration já aplicada for editada, o runner para com erro. Para mudar o schema, crie uma nova migration numerada em vez de alterar uma antiga. O comando pode ser executado novamente: migrations já registradas serão ignoradas.

`initial_migration.sql` cria `Usuario`, `Especialidade`, `Tomador`, `Prestador`, `Chamado`, `Pagamento`, `Avaliacao` e `Mensagem`. Uma migration deixa `Usuario.idPessoa` opcional porque o schema atual ainda não define uma tabela `Pessoa`; isso permite o cadastro até que essa relação seja implementada. `PasswordResetTokens` é criada em sua própria migration SQL porque o schema inicial já foi aplicado; migrations aplicadas não devem ser editadas.

Inicie o servidor PHP embutido:

```powershell
php -S localhost:8000
```

Acesse <http://localhost:8000> no navegador. Se o PHP não encontrar `mysqli`, habilite `extension=mysqli` no `php.ini` ativo e reinicie o servidor.

## Recuperação de senha

Preencha no `.env` as variáveis `APP_URL` e `SMTP_*` usando `.env.example` como referência. Para Gmail, use `smtp.gmail.com`, porta `587`, criptografia `tls`, o endereço completo em `SMTP_USER` e uma senha de app em `SMTP_PASS`. Não use a senha normal da conta Google.

Em ambiente local, `APP_URL=http://localhost:8000` serve para testar no mesmo computador. Em produção, configure a URL pública HTTPS do site. O servidor também precisa permitir conexões de saída para o SMTP escolhido.

`PasswordResetTokens` é criada pela migration, não durante uma requisição da página. O usuário do banco precisa ter permissão `CREATE TABLE` ao aplicar as migrations. A resposta não revela se o CPF está cadastrado. O token é armazenado como hash, expira em 30 minutos, só pode ser usado uma vez e há limite de uma solicitação por minuto por conta.

## Models e repositories

Os models representam as entidades do schema: `User`, `PasswordResetToken`, `Specialty`, `Requester`, `Provider`, `ServiceCall`, `Payment`, `Review` e `Message`. `CrudRepository` fornece leitura, criação, atualização e exclusão para os models de marketplace com tabela, chave e campos validados por allowlist. Usuários e tokens usam repositories próprios (`UserRepository` e `PasswordResetTokenRepository`) porque têm regras específicas de senha e expiração. `BaseRepository` compartilha a conexão e operações comuns. Controllers coordenam a lógica da requisição; SQL fica nos repositories.

`processar.php` valida o método HTTP, localiza a ação e serializa a resposta. As ações ficam em `AuthController`, `AccountController` e `PasswordResetController`. Para adicionar um fluxo, crie o método no controller correspondente e registre a ação no mapa de rotas do endpoint.

## Estrutura principal

- `index.php`: página inicial, login, cadastro e menu de conta.
- `processar.php`: front controller e mapa de ações JSON.
- `src/controllers`: lógica das ações de autenticação, conta e recuperação.
- `src/pages/conta.php`: edição e exclusão da conta autenticada.
- `src/pages/redefinir_senha.php`: formulário aberto pelo link de recuperação.
- `src/scripts/migrate.php`: runner CLI das migrations.
- `database/migrations`: alterações incrementais do schema.
- `src/repositories`: consultas e operações separadas por tabela.
- `src/scripts/bd_control.php`: adaptador legado para operações de conta.
- `src/scripts/password_reset_mailer.php`: envio de e-mail por SMTP.
- `src/libs/PHPMailer`: biblioteca PHPMailer incluída localmente.
- `.env.example`: nomes e exemplos das variáveis de configuração.