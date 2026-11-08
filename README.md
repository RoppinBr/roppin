### Campos no .env

- DB_HOST
- DB_USER
- DB_PASS
- DB_NAME

### Recuperação de senha por e-mail

Configure no `.env` as variáveis SMTP e `APP_URL` mostradas em `.env.example`. Em produção, use a URL pública HTTPS da aplicação e as credenciais SMTP do provedor de e-mail. A recuperação usa PHPMailer instalado em `src/libs/PHPMailer`, sem Composer.

Na primeira solicitação, a aplicação cria a tabela `PasswordResetTokens`; o usuário do banco precisa ter permissão para criar tabelas. Os links expiram em 30 minutos, só podem ser usados uma vez e têm limite de uma solicitação por minuto por conta.

### Acesso ao banco

- MySql:
  - Host: db_roppin.mysql.dbaas.com.br
  - Banco: db_roppin
  - Port: 3306