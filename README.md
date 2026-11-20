# Roppin

Aplicação web de marketplace de serviços. O projeto inclui a página inicial, cadastro e login, edição e exclusão da própria conta, recuperação de senha por link enviado por e-mail e chat interno entre cliente e prestador.

## Requisitos

- PHP 8.0 ou superior.
- MySQL ou MariaDB e a extensão PHP `mysqli`.
- Extensão PHP `openssl` para SMTP com TLS e para a criptografia do chat.
- Navegador com JavaScript habilitado.
- Permissão de escrita em `storage/chat` para os anexos do chat (Sprint 3).

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

`initial_migration.sql` cria `Usuario`, `Especialidade`, `Tomador`, `Prestador`, `Chamado`, `Pagamento`, `Avaliacao` e `Mensagem`. Uma migration deixa `Usuario.idPessoa` opcional porque o schema atual ainda não define uma tabela `Pessoa`; isso permite o cadastro até que essa relação seja implementada. Migrations posteriores criam `PasswordResetTokens` e convertem a especialidade singular do prestador em uma relação N:N via `PrestadorEspecialidade`, preservando os vínculos existentes. Migrations aplicadas não devem ser editadas.

Inicie o servidor PHP embutido:

```powershell
php -S localhost:8000
```

Acesse <http://localhost:8000> no navegador. Se o PHP não encontrar `mysqli`, habilite `extension=mysqli` no `php.ini` ativo e reinicie o servidor.

## Recuperação de senha

Preencha no `.env` as variáveis `SMTP_*` usando `.env.example` como referência. Para Gmail, use `smtp.gmail.com`, porta `587`, criptografia `tls`, o endereço completo em `SMTP_USER` e uma senha de app em `SMTP_PASS`. Não use a senha normal da conta Google.

A URL pública do site é derivada da própria requisição (scheme, host e porta), então não é necessário configurar `APP_URL`. Em produção, atrás de proxy, o servidor deve repassar `HTTPS`/`X-Forwarded-Proto` corretamente e manter `SERVER_NAME` com o domínio público. O servidor também precisa permitir conexões de saída para o SMTP escolhido.

`PasswordResetTokens` é criada pela migration, não durante uma requisição da página. O usuário do banco precisa ter permissão `CREATE TABLE` ao aplicar as migrations. A resposta não revela se o CPF está cadastrado. O token é armazenado como hash, expira em 30 minutos, só pode ser usado uma vez e há limite de uma solicitação por minuto por conta.

## Primeira visita e busca

A home exibe um tutorial guiado na primeira visita do navegador. Ele mostra, nesta ordem, como entrar, criar conta, buscar um serviço, iniciar uma conversa e acompanhar as mensagens pelo menu. O controle é o cookie `roppin_tutorial_visto` (validade de 1 ano); para rever o tour, abra `index.php?tour=1`. A página de mensagens tem um tour próprio, controlado por `roppin_chat_tutorial_visto` (reveja com `src/pages/mensagens.php?tour=1`), que destaca a lista de conversas, o botão **Nova**, a busca e a área da conversa. Nesse tour, a busca por `Eletricista` mostra apenas um profissional fictício marcado como **Exemplo** e abre uma conversa de demonstração com ele na lista e no histórico, tudo apenas visual. Ao concluir ou pular o tour, o profissional e a conversa de exemplo desaparecem da tela sem gravar nada no banco.

A barra de busca sugere especialidades enquanto o usuário digita. A comparação ignora caixa e acentos e descarta caracteres não alfanuméricos, então `mecanico` encontra `Mecãnico` e `ar-condicionado` encontra `Instalador de Ar-Condicionado`. O mesmo critério é usado pelo filtro de prestadores no chat.

## Chat interno

O Sprint 1 do chat está implementado em texto. O cliente pode começar **sem conta**: ao clicar em **Buscar com IA**, a página cria um usuário convidado (`ehConvidado = 1`), guarda um token opaco no cookie HttpOnly `roppin_convidado` (30 dias) e mantém a conversa no mesmo banco das contas comuns. O prestador continua exigindo conta real. Depois da primeira resposta do prestador, um aviso oferece nome + e-mail: o sistema envia um link para definir senha e promove a mesma linha de `Usuario`, preservando conversa, mensagens e avaliações. Se o visitante fizer cadastro ou login em conta existente, a conversa é promovida ou transferida em transação. O histórico, os não lidos e a confirmação de leitura funcionam por polling de 3 a 15 segundos, sem WebSocket. Ambas as partes podem **Encerrar** a conversa pelo botão no cabeçalho; depois disso o envio é bloqueado e o botão passa a **Reabrir**. Uma mensagem de sistema cifrada registra cada mudança de estado.

- `src/pages/mensagens.php` é a página dedicada; `src/pages/conta.php` reutiliza o mesmo painel na aba **Mensagens** (`conta.php?aba=mensagens`).
- O menu desktop, o menu mobile e o dropdown da conta exibem o item **Mensagens**. O badge de não lidas é atualizado a cada 30 segundos e pausa quando a aba do navegador está oculta.
- `src/scripts/chat.js` cuida da inbox, envio, polling, deduplicação de mensagens, encerramento/reabertura e seletor de prestadores. Ao abrir uma conversa no desktop, a lista de conversas permanece visível à esquerda; no mobile, o botão **Conversas** volta para a lista. `src/scripts/chat_badge.js` atualiza o badge.
- A conversa ativa com o mesmo prestador é reutilizada; uma nova conversa só é criada quando a anterior estiver encerrada.
- Com a conversa **encerrada**, cliente e prestador podem se avaliar uma única vez cada, pelo botão **Avaliar** no cabeçalho. A nota vai de 1 a 5 estrelas, fica em `Avaliacao` (vinculada à conversa pelo `tokenPublico`) e recalcula `media_reputacao` do avaliado. A ação `chat_avaliar` usa o mesmo token opaco e exige CSRF; a aba mostra as notas dada e recebida.
- A URL de `mensagens.php` não expõe o id do banco. Cada conversa tem um `tokenPublico` aleatório de 32 caracteres no banco; ele trafega nas ações do chat e fica gravado na sessão (`chat_conversa_token`) enquanto a conversa está aberta. Ao recarregar a página, a conversa é reaberta pela sessão. O botão **Conversas** limpa esse estado.
- As mensagens são gravadas com AES-256-GCM. A chave fica em `CHAT_ENCRYPTION_KEY` (32 bytes em base64). Se a chave estiver ausente ou inválida, o chat falha de forma controlada e não grava texto puro. Gere uma chave local com:

```powershell
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

- `storage/chat` fica fora do Git e é protegido por `storage/.htaccess`. Os anexos, o download por `anexo.php`, o bloqueio/denúncia, o e-mail de aviso e o bot de triagem pertencem aos próximos sprints e ainda não estão ativos.
- `CHAT_ENABLED=false` desativa o chat sem remover dados. `CHAT_POLL_INTERVAL_MS` ajusta o polling (mínimo de 3000 ms). `CHAT_MAX_MESSAGES_PER_MINUTE` e `CHAT_MAX_NEW_CONVERSATIONS_PER_DAY` limitam envios e aberturas por sessão.

## Models e repositories

Os models representam as entidades do schema: `User`, `PasswordResetToken`, `Specialty`, `Requester`, `Provider`, `ServiceCall`, `Payment`, `Review`, `Message`, `Conversation`, `ConversationParticipant`, `MessageAttachment`, `UserBlock`, `Report` e `EmailNotification`. `CrudRepository` fornece leitura, criação, atualização e exclusão para os models de marketplace com tabela, chave e campos validados por allowlist. Usuários, tokens, especialidades, prestadores e chat usam repositories próprios por terem regras específicas. `BaseRepository` compartilha a conexão e operações comuns. Controllers coordenam a lógica da requisição; SQL fica nos repositories.

`processar.php` valida o método HTTP, localiza a ação e serializa a resposta. As ações ficam em `AuthController`, `AccountController`, `PasswordResetController`, `SpecialtyController`, `ProviderController`, `ChatController` e `ChatGuestController`. Para adicionar um fluxo, crie o método no controller correspondente e registre a ação no mapa de rotas do endpoint. As ações do chat usam `chat_listar_prestadores`, `chat_iniciar_conversa`, `chat_conversas`, `chat_conversa`, `chat_enviar`, `chat_encerrar_conversa`, `chat_reabrir_conversa`, `chat_avaliar`, `chat_marcar_lida`, `chat_focar_conversa`, `chat_limpar_foco`, `chat_nao_lidas` e `chat_vincular_email`. As ações de uma conversa recebem `token` (o `tokenPublico`), nunca o id numérico; envio, início, leitura, avaliação, foco e mudança de estado exigem o `csrf_token` da sessão.

Na aba **Cadastro de prestador**, o usuário define o valor cobrado e o período (`hora`, `dia` ou `servico`) e seleciona várias especialidades. O mesmo formulário cadastra ou atualiza esses valores. O modal filtra por texto, mostra as selecionadas no topo e mantém novas opções em rascunho até **Salvar especialidades**. Para quem ainda não é prestador, esse botão grava nomes novos no catálogo e mantém as seleções no formulário; **Cadastrar como prestador** cria o perfil e todos os vínculos em uma transação. Para prestadores existentes, salvar o modal atualiza os vínculos e cria nomes novos atomicamente. O servidor valida os IDs e permite um cadastro de prestador por usuário. No momento, usuários cadastrados como prestadores podem adicionar especialidades ao catálogo; o schema ainda não possui perfis ou permissões administrativas.

## Estrutura principal

- `index.php`: página inicial, login, cadastro e menu de conta.
- `processar.php`: front controller e mapa de ações JSON.
- `src/controllers`: lógica das ações de autenticação, conta, recuperação, especialidades, prestadores e chat.
- `src/pages/conta.php`: edição e exclusão da conta autenticada, cadastro de prestador e aba de mensagens.
- `src/pages/mensagens.php`: página dedicada do chat.
- `src/pages/partials/chat_inbox.php`: inbox compartilhada entre a página e a aba da conta.
- `src/pages/redefinir_senha.php`: formulário aberto pelo link de recuperação.
- `src/scripts/migrate.php`: runner CLI das migrations.
- `src/scripts/chat.js`: polling, envio e seletor de prestadores do chat.
- `src/scripts/chat_badge.js`: badge de mensagens não lidas no menu.
- `src/services`: regras e criptografia do chat.
- `database/migrations`: alterações incrementais do schema.
- `src/repositories`: consultas e operações separadas por tabela.
- `src/scripts/bd_control.php`: adaptador legado para operações de conta.
- `src/scripts/password_reset_mailer.php`: envio de e-mail por SMTP.
- `src/libs/PHPMailer`: biblioteca PHPMailer incluída localmente.
- `storage/chat`: armazenamento protegido dos anexos do chat (fora do Git).
- `.env.example`: nomes e exemplos das variáveis de configuração.