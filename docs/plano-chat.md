# Plano de implementação — Chat Roppin

**Status:** proposta para revisão  
**Data:** 19/11/2026  
**Escopo:** chat interno cliente ↔ prestador com assistente (bot) de triagem, página de mensagens e aba na conta. Canais externos e IA generativa ficam preparados para fases seguintes.

---

## 1. Resumo executivo

O MVP entrega uma caixa de mensagens interna no estilo WhatsApp Web:

- O **cliente** inicia a conversa pelo botão de busca da home ou pela página de mensagens.
- O **bot** faz a triagem por regras (sem IA generativa), identifica a especialidade e sugere até 3 prestadores.
- O cliente escolhe um prestador e a **mesma conversa é transferida** para ele; o bot para de responder.
- **Cliente e prestador** trocam texto e anexos, com não lidos, confirmação de leitura, “digitando...” e notificação por e-mail.
- O chat fica em uma página dedicada (`mensagens.php`) e em uma nova aba **Mensagens** na conta.
- A atualização é por **polling a cada 3–5 segundos**, sem WebSocket.
- A modelagem já prevê **canal por participante** e uma interface de bot, para plugar Telegram/WhatsApp e IA real depois.
- **Criptografia:** TLS em trânsito + AES-256-GCM em repouso no MVP; E2EE real fica para uma fase futura (ver seção 9).

**Estimativa do MVP:** 7 a 10 dias de desenvolvimento, divididos em 3 sprints.  
**Fase 2 (canais):** +5 a 8 dias. **Fase 3 (IA real):** +3 a 5 dias.

---

## 2. Decisões fechadas

| Tema | Decisão |
|---|---|
| Papel do bot | Triagem e recomendação de prestadores |
| Fluxo bot → humano | Mesmo thread, com transferência para o prestador |
| Vínculo com Chamado | Conversa livre; `Chamado` é opcional e pode ser vinculado depois |
| Onde o chat vive | Página de mensagens + aba na conta |
| Tempo real | Polling de 3–5 s |
| IA no MVP | Sem IA real; regras determinísticas atrás de uma interface |
| Recursos do MVP | Histórico, não lidos, digitação, leitura, anexos, e-mail, bloquear/denunciar |
| Quem inicia | Cliente inicia; prestador apenas responde |
| Canais externos | Fase 2, com arquitetura pronta; Telegram primeiro e WhatsApp Cloud API oficial depois |
| Vínculo de canal | OTP/deep link no canal |
| Bot nos canais | Mesmo fluxo de triagem do site |
| Perfil público do prestador | Fora do MVP; entrada pelos cartões do bot |
| Exclusão de conta | Cascata: apaga as conversas e mensagens do usuário |
| Storage de anexos | `storage/chat` na raiz do projeto, ignorado no Git, servido por `anexo.php` |
| Cron | Sem cron no servidor; dispatcher oportunista + gatilho externo opcional |
| E-mail | Uma notificação por conversa na primeira mensagem não lida |
| Anexos | JPG, PNG, WebP, PDF, DOC e DOCX até 5 MB |
| Conversa por par | Reutiliza a conversa ativa com o mesmo prestador |
| Criptografia | TLS + AES-256-GCM em repouso (decidido); E2EE real fica para fase futura |

---

## 3. Escopo por fase

### Fase 1 — MVP

- Conversa 1:1 cliente ↔ prestador.
- Conversa com o bot e transferência no mesmo thread.
- Lista de conversas, histórico paginado, busca simples por nome.
- Contador de não lidas por conversa e total no menu.
- Confirmação de leitura e indicador “digitando...”.
- Anexos de imagem (JPG, PNG, WebP), PDF, DOC e DOCX, até 5 MB.
- Bloquear e denunciar usuário.
- Notificação por e-mail na primeira mensagem não lida de cada conversa.
- Aba **Mensagens** em `conta.php` e item **Mensagens** no menu.
- Base pronta para canais e IA (interfaces e campos de canal).
- Criptografia em trânsito (TLS) e em repouso com AES-256-GCM para mensagens e anexos.

### Fase 2 — Canais externos

- Telegram Bot API (webhook).
- WhatsApp Cloud API oficial da Meta (webhook + templates).
- Caixa de entrada omnichannel: a mensagem chega no canal e aparece no chat interno; a resposta sai pelo canal de preferência do participante.
- Vínculo de conta por deep link (Telegram) ou OTP (WhatsApp).
- Entrega com retry e idempotência.
- Mídia recebida e enviada pelos canais.

### Fase 3 — IA real e evolução

- LLM no lugar das regras, com function calling para buscar prestadores.
- Moderação automática de conteúdo.
- Geolocalização para ordenar prestadores por distância.
- Preferências de notificação e push do navegador.
- Painel administrativo de denúncias e métricas.

### Fora de escopo (explícito)

- Chamadas de áudio/vídeo.
- Conversas em grupo.
- Edição e exclusão de mensagens.
- Push notifications no MVP.
- Painel admin no MVP.
- Geolocalização no MVP.
- WhatsApp não oficial (Evolution/Baileys).
- Cadastro automático de usuário por canal externo.

---

## 4. Arquitetura

```
Navegador (mensagens.php / aba da conta / badge do menu)
   │  fetch POST processar.php  (polling 3–5 s)
   ▼
ChatController
   │
   ├── ChatService ────────────── ConversationRepository
   │        │                    MessageRepository
   │        │                    ChatSafetyRepository
   │        │
   │        ├── ChatbotService (regras; interface para LLM)
   │        ├── ChatCryptoService (AES-256-GCM em repouso)
   │        ├── ChatAttachmentService ── storage/chat (raiz, protegido)
   │        └── ChatNotificationService ── NotificacaoEmail (outbox)
   │                                              │
   │                                              └── dispatcher oportunista / gatilho externo ── SMTP
   │
   └── (fase 2) ChannelRegistry ── TelegramAdapter / WhatsAppCloudAdapter
```

### Princípios

- Reaproveitar o padrão existente: `processar.php` → controller → service/repository → model.
- Nada de Composer/framework; tudo em PHP 8 puro, como o restante do projeto.
- Toda ação de escrita é `POST` com `csrf_token`.
- Leitura de anexo é um endpoint próprio (`anexo.php`) com checagem de sessão e participação.
- O bot é uma camada de serviço; trocar regras por LLM não deve mexer no controller nem no banco.
- O polling é stateless; o servidor não mantém conexão aberta.
- Uploads ficam em `storage/chat` na raiz, com acesso negado por `.htaccess`/Nginx, nomes aleatórios sem extensão executável e download somente via PHP.

---

## 5. Modelo de dados

### 5.1 Visão geral

| Tabela | Papel |
|---|---|
| `Conversa` | Thread entre cliente, prestador (opcional) e bot |
| `ConversaParticipante` | Leitura, presença, digitação e canal de cada participante |
| `Mensagem` (alterada) | Mensagens de usuário, bot e sistema |
| `MensagemAnexo` | Metadados dos arquivos enviados |
| `UsuarioBloqueio` | Bloqueios entre usuários |
| `Denuncia` | Denúncias de conversa/mensagem |
| `NotificacaoEmail` | Fila de e-mails transacionais |
| `CanalVinculo` / `CanalVerificacao` | Fase 2: vínculo com Telegram/WhatsApp |

### 5.2 `Conversa`

```sql
CREATE TABLE IF NOT EXISTS Conversa (
    idConversa INT AUTO_INCREMENT PRIMARY KEY,
    idCliente INT NOT NULL,
    idPrestador INT NULL,
    idChamado INT NULL,
    idEspecialidade INT NULL,
    canal VARCHAR(20) NOT NULL DEFAULT 'site',
    estado VARCHAR(20) NOT NULL DEFAULT 'bot',
    assunto VARCHAR(150) NULL,
    contexto JSON NULL,
    ultimaMensagemEm DATETIME NULL,
    ultimaMensagemPreview TEXT NULL,
    criadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conversa_cliente
        FOREIGN KEY (idCliente) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    CONSTRAINT fk_conversa_prestador
        FOREIGN KEY (idPrestador) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    CONSTRAINT fk_conversa_chamado
        FOREIGN KEY (idChamado) REFERENCES Chamado(idChamado) ON DELETE SET NULL,
    CONSTRAINT fk_conversa_especialidade
        FOREIGN KEY (idEspecialidade) REFERENCES Especialidade(idEspecialidade) ON DELETE SET NULL,
    KEY idx_conversa_cliente (idCliente, atualizadaEm),
    KEY idx_conversa_prestador (idPrestador, atualizadaEm),
    KEY idx_conversa_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Estados:**

- `bot`: conversa com o assistente; `idPrestador` é `NULL`.
- `ativa`: prestador já assumiu; cliente e prestador podem trocar mensagens.
- `encerrada`: somente leitura (reservado para fases futuras; o MVP não expõe botão de encerrar).

**Observações:**

- `contexto` guarda o estado da triagem em JSON (etapa, especialidade sugerida, IDs sugeridos).
- `ultimaMensagemPreview` é denormalizado para a lista de conversas não precisar de `JOIN`/subconsulta a cada item. Com criptografia em repouso, o valor é gravado como `enc:v1:<base64>` e descriptografado em memória ao montar a inbox; mensagens legadas ficam em texto puro.
- `idChamado` fica pronto para o fluxo futuro de contratação; o MVP não cria Chamado pelo chat.
- A exclusão de usuário cascateia as conversas e mensagens, conforme decidido (LGPD). Se no futuro for necessário preservar histórico, trocar para `SET NULL` + anonimização.

### 5.3 `ConversaParticipante`

```sql
CREATE TABLE IF NOT EXISTS ConversaParticipante (
    idConversa INT NOT NULL,
    idUsuario INT NOT NULL,
    papel VARCHAR(10) NOT NULL, -- 'cliente' | 'prestador'
    canal VARCHAR(20) NOT NULL DEFAULT 'site',
    ultimaMensagemLida INT NULL,
    lidaEm DATETIME NULL,
    ultimoVistoEm DATETIME NULL,
    digitandoEm DATETIME NULL,
    PRIMARY KEY (idConversa, idUsuario),
    CONSTRAINT fk_participante_conversa
        FOREIGN KEY (idConversa) REFERENCES Conversa(idConversa) ON DELETE CASCADE,
    CONSTRAINT fk_participante_usuario
        FOREIGN KEY (idUsuario) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    KEY idx_participante_usuario (idUsuario, idConversa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- Uma linha por participante humano. O bot **não** tem linha.
- `ultimaMensagemLida` + `lidaEm` resolvem não lidos e confirmação de leitura.
- `ultimoVistoEm` alimenta a regra de “offline para e-mail”.
- `digitandoEm` expira em 6 segundos.
- `canal` já nasce com `site` e será usado na fase 2 para rotear a resposta do participante.

### 5.4 Alteração de `Mensagem`

```sql
ALTER TABLE Mensagem
    DROP FOREIGN KEY FK_Mensagem_Remetente,
    DROP FOREIGN KEY FK_Mensagem_Destinatario;

ALTER TABLE Mensagem
    ADD COLUMN idConversa INT NULL AFTER idMensagem,
    ADD COLUMN tipoRemetente VARCHAR(10) NOT NULL DEFAULT 'usuario' AFTER idConversa,
    ADD COLUMN canal VARCHAR(20) NOT NULL DEFAULT 'site' AFTER tipoRemetente,
    ADD COLUMN idExterno VARCHAR(191) NULL AFTER canal,
    ADD COLUMN criptografia VARCHAR(20) NOT NULL DEFAULT 'nenhuma' AFTER idExterno,
    ADD COLUMN metadados JSON NULL AFTER hora,
    ADD COLUMN criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER metadados,
    MODIFY COLUMN textoMensagem TEXT NULL,
    MODIFY COLUMN data DATE NULL,
    MODIFY COLUMN hora TIME NULL,
    MODIFY COLUMN idRemetente INT NULL,
    MODIFY COLUMN idDestinatario INT NULL,
    ADD UNIQUE KEY uq_mensagem_id_externo (idExterno),
    ADD KEY idx_mensagem_conversa (idConversa, idMensagem),
    ADD KEY idx_mensagem_remetente_data (idRemetente, criadoEm);
```

**Backfill de mensagens antigas** (executar dentro da migration, antes de tornar `idConversa` obrigatório):

```sql
-- 1. Cria uma conversa encerrada por par de usuários que já trocou mensagem.
INSERT INTO Conversa (idCliente, idPrestador, estado, criadaEm, atualizadaEm)
SELECT
    LEAST(idRemetente, idDestinatario),
    GREATEST(idRemetente, idDestinatario),
    'encerrada',
    MIN(STR_TO_DATE(CONCAT(data, ' ', hora), '%Y-%m-%d %H:%i:%s')),
    MAX(STR_TO_DATE(CONCAT(data, ' ', hora), '%Y-%m-%d %H:%i:%s'))
FROM Mensagem
WHERE idConversa IS NULL
GROUP BY LEAST(idRemetente, idDestinatario), GREATEST(idRemetente, idDestinatario);

-- 2. Liga cada mensagem antiga à conversa criada.
UPDATE Mensagem m
JOIN Conversa c
    ON c.idCliente = LEAST(m.idRemetente, m.idDestinatario)
   AND c.idPrestador = GREATEST(m.idRemetente, m.idDestinatario)
SET m.idConversa = c.idConversa
WHERE m.idConversa IS NULL;

-- 3. Preenche o novo timestamp.
UPDATE Mensagem
SET criadoEm = STR_TO_DATE(CONCAT(data, ' ', hora), '%Y-%m-%d %H:%i:%s')
WHERE data IS NOT NULL AND hora IS NOT NULL;
```

Depois:

```sql
ALTER TABLE Mensagem
    MODIFY COLUMN idConversa INT NOT NULL,
    ADD CONSTRAINT fk_mensagem_conversa
        FOREIGN KEY (idConversa) REFERENCES Conversa(idConversa) ON DELETE CASCADE,
    ADD CONSTRAINT FK_Mensagem_Remetente
        FOREIGN KEY (idRemetente) REFERENCES Usuario(idUsuario) ON DELETE SET NULL,
    ADD CONSTRAINT FK_Mensagem_Destinatario
        FOREIGN KEY (idDestinatario) REFERENCES Usuario(idUsuario) ON DELETE SET NULL;
```

**Campos novos:**

- `tipoRemetente`: `usuario`, `bot` ou `sistema`.
- `idRemetente`: `NULL` quando for bot ou sistema.
- `textoMensagem`: pode ser `NULL` em mensagem só com anexo.
- `metadados`: JSON para cartões de sugestão do bot (prestadores, especialidade, etc.).
- `canal` + `idExterno`: prontos para a fase 2 (idempotência de webhooks).
- `criptografia`: `nenhuma` para legado e `aes-256-gcm` para mensagens novas.
- `data`/`hora`: mantidos por compatibilidade; o código novo usa `criadoEm`.
- **Criptografia em repouso:** o `textoMensagem` de mensagens novas guarda `base64(nonce + tag + ciphertext)`; o `ChatCryptoService` descriptografa para o bot, e-mail e API. A busca de conteúdo no servidor deixa de existir (a inbox usa preview descriptografado em memória). Mensagens antigas com `criptografia='nenhuma'` continuam legíveis sem decriptação.

### 5.5 `MensagemAnexo`

```sql
CREATE TABLE IF NOT EXISTS MensagemAnexo (
    idAnexo INT AUTO_INCREMENT PRIMARY KEY,
    idMensagem INT NOT NULL,
    nomeOriginal VARCHAR(255) NOT NULL,
    caminhoRelativo VARCHAR(255) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    tamanho INT NOT NULL,
    criptografia VARCHAR(20) NOT NULL DEFAULT 'nenhuma',
    criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_anexo_mensagem
        FOREIGN KEY (idMensagem) REFERENCES Mensagem(idMensagem) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- Um anexo por mensagem no MVP (a tabela já suporta vários).
- `caminhoRelativo` é relativo a `CHAT_UPLOAD_DIR` (`storage/chat`), nunca absoluto.
- O nome original é apenas para exibição; o arquivo em disco usa `<32 hex>.bin`, sem extensão executável.
- O arquivo `.bin` guarda `nonce + tag + ciphertext` (`criptografia='aes-256-gcm'`); o download descriptografa em memória antes de enviar. Arquivos legados ficam com `criptografia='nenhuma'`.

### 5.6 `UsuarioBloqueio`

```sql
CREATE TABLE IF NOT EXISTS UsuarioBloqueio (
    idBloqueador INT NOT NULL,
    idBloqueado INT NOT NULL,
    motivo VARCHAR(255) NULL,
    criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (idBloqueador, idBloqueado),
    CONSTRAINT fk_bloqueio_bloqueador
        FOREIGN KEY (idBloqueador) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    CONSTRAINT fk_bloqueio_bloqueado
        FOREIGN KEY (idBloqueado) REFERENCES Usuario(idUsuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5.7 `Denuncia`

```sql
CREATE TABLE IF NOT EXISTS Denuncia (
    idDenuncia INT AUTO_INCREMENT PRIMARY KEY,
    idDenunciante INT NOT NULL,
    idDenunciado INT NOT NULL,
    idConversa INT NULL,
    idMensagem INT NULL,
    motivo VARCHAR(40) NOT NULL,
    descricao TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'aberta',
    criadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    analisadaEm DATETIME NULL,
    CONSTRAINT fk_denuncia_denunciante
        FOREIGN KEY (idDenunciante) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    CONSTRAINT fk_denuncia_denunciado
        FOREIGN KEY (idDenunciado) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    CONSTRAINT fk_denuncia_conversa
        FOREIGN KEY (idConversa) REFERENCES Conversa(idConversa) ON DELETE SET NULL,
    CONSTRAINT fk_denuncia_mensagem
        FOREIGN KEY (idMensagem) REFERENCES Mensagem(idMensagem) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Motivos sugeridos:** `assedio`, `golpe`, `spam`, `conteudo_ilegal`, `outro`.  
**Importante:** o projeto não tem perfil de administrador. No MVP a denúncia só é registrada; o tratamento é manual via SQL. Um painel admin exige uma migration de papéis/permissões (fase 3).

### 5.8 `NotificacaoEmail`

```sql
CREATE TABLE IF NOT EXISTS NotificacaoEmail (
    idNotificacao INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL,
    idConversa INT NOT NULL,
    idMensagem INT NULL,
    tipo VARCHAR(30) NOT NULL DEFAULT 'nova_mensagem',
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    tentativas TINYINT NOT NULL DEFAULT 0,
    ultimoErro TEXT NULL,
    criadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviadaEm DATETIME NULL,
    CONSTRAINT fk_notificacao_usuario
        FOREIGN KEY (idUsuario) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    CONSTRAINT fk_notificacao_conversa
        FOREIGN KEY (idConversa) REFERENCES Conversa(idConversa) ON DELETE CASCADE,
    CONSTRAINT fk_notificacao_mensagem
        FOREIGN KEY (idMensagem) REFERENCES Mensagem(idMensagem) ON DELETE SET NULL,
    KEY idx_notificacao_status (status, criadaEm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5.9 Fase 2 — canais

```sql
CREATE TABLE IF NOT EXISTS CanalVinculo (
    idVinculo INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL,
    canal VARCHAR(20) NOT NULL, -- 'telegram' | 'whatsapp'
    identificadorExterno VARCHAR(100) NOT NULL,
    verificadoEm DATETIME NOT NULL,
    criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_canal_identificador (canal, identificadorExterno),
    UNIQUE KEY uq_usuario_canal (idUsuario, canal),
    CONSTRAINT fk_canal_vinculo_usuario
        FOREIGN KEY (idUsuario) REFERENCES Usuario(idUsuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS CanalVerificacao (
    idVerificacao INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL,
    canal VARCHAR(20) NOT NULL,
    identificadorExterno VARCHAR(100) NOT NULL,
    codigoHash CHAR(64) NOT NULL,
    expiraEm DATETIME NOT NULL,
    usadoEm DATETIME NULL,
    criadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_canal_verificacao_usuario
        FOREIGN KEY (idUsuario) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    KEY idx_canal_verificacao_busca (canal, identificadorExterno, usadoEm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- `ConversaParticipante.canal` define por onde o participante recebe.
- O envio para canal externo resolve o destinatário em `CanalVinculo`.
- `Mensagem.idExterno` garante idempotência de webhooks.

### 5.10 Migrations

| Arquivo | Conteúdo |
|---|---|
| `202611190001_chat_core.sql` | `Conversa`, `ConversaParticipante`, alteração/backfill de `Mensagem` |
| `202611190002_chat_attachments.sql` | `MensagemAnexo` |
| `202611190003_chat_safety.sql` | `UsuarioBloqueio`, `Denuncia` |
| `202611190004_chat_email_outbox.sql` | `NotificacaoEmail` |
| Fase 2: `2026..._chat_channels.sql` | `CanalVinculo`, `CanalVerificacao` e índices de canal |

**Antes de aplicar:** verificar se `Mensagem` tem dados reais. Se tiver, fazer backup. O backfill acima preserva as mensagens antigas como conversas encerradas.

---

## 6. Fluxos

### 6.1 Entrada do cliente

1. **Busca da home:** o botão “Buscar com IA” deixa de apenas filtrar especialidades e passa a abrir o chat com o bot, levando o texto digitado como primeira mensagem.
   - Se o usuário estiver logado: `mensagens.php?novo=1&q=<texto>`.
   - Se não estiver: abre o modal de login e guarda a busca em `sessionStorage`; após o login, redireciona para o chat.
2. **Página de mensagens:** botão “Nova conversa” abre a conversa com o bot.
3. **Cartão de prestador sugerido pelo bot:** botão “Conversar” transfere a conversa para aquele prestador.
4. **Perfil público do prestador:** não existe hoje. Fica como evolução recomendada (seção 15).

### 6.2 Triagem do bot (regras)

Estados em `Conversa.contexto`:

| Etapa | O que o bot faz | Próximo passo |
|---|---|---|
| `saudacao` | Cumprimenta e pergunta o que o cliente precisa | Cliente responde |
| `necessidade` | Normaliza o texto, pontua especialidades e escolhe a melhor | Se ambíguo, lista opções; se claro, busca prestadores |
| `sugestoes` | Mostra até 3 prestadores em cartões (nome, especialidade, valor/período, reputação) | Cliente clica em “Conversar” ou digita o número |
| `transferida` | Envia mensagem de transferência e encerra a atuação do bot | Prestador assume |

**Algoritmo de match (MVP, determinístico):**

1. Normalizar texto: minúsculas, remover acentos, remover pontuação.
2. Remover stopwords comuns (`preciso`, `quero`, `de`, `para`, `um`, `uma`, `me`, `ajuda`...).
3. Pontuar cada especialidade:
   - nome exato: 100 pontos;
   - todas as palavras do nome presentes no texto: 50;
   - sobreposição de tokens: 10 por token;
   - dicionário de sinônimos em `src/config/chatbot_keywords.php` (ex.: `faxina` → limpeza, `tomada`/`fiação` → eletricista): 5 por termo.
4. Se a maior pontuação for claramente maior que a segunda, segue direto; se houver empate ou pontuação baixa, o bot lista as 5 especialidades mais próximas e pede para o cliente escolher por número.
5. Buscar prestadores da especialidade:

```sql
SELECT u.idUsuario,
       COALESCE(u.nome, u.razaoSocial) AS nome,
       p.valor,
       p.periodo_cobranca,
       u.media_reputacao
FROM Prestador p
JOIN Usuario u ON u.idUsuario = p.idUsuario
JOIN PrestadorEspecialidade pe ON pe.idUsuario = p.idUsuario
WHERE pe.idEspecialidade = ?
  AND p.idUsuario <> ? -- não sugerir o próprio cliente
ORDER BY COALESCE(u.media_reputacao, 0) DESC, p.valor ASC
LIMIT 3;
```

6. Se não houver prestador: informar que ainda não há profissionais para aquela especialidade e oferecer as categorias disponíveis.
7. Excluir prestadores bloqueados pelo cliente e vice-versa.

**Limitações do MVP:**

- Não há geolocalização no schema; a ordenação é por reputação e preço, não por distância.
- Não há IA generativa; o bot entende palavras-chave, não linguagem livre complexa.
- O bot não negocia preço, não fecha contrato e não compartilha contato externo.

**Exemplo de diálogo:**

> **Bot:** Olá! Sou o assistente da Roppin. O que você precisa hoje?  
> **Cliente:** preciso de um eletricista para trocar uma tomada  
> **Bot:** Entendi: serviço de eletricista. Encontrei estes profissionais:  
> 1. João Silva — R$ 80/h — ★ 4.8  
> 2. Maria Souza — R$ 100/h — ★ 4.9  
> 3. Carlos Lima — R$ 70/h — sem avaliações  
> **Cliente:** 2  
> **Bot:** Pronto! Você está falando com Maria Souza. Ela recebeu seu pedido e vai responder por aqui.

**Mensagens do bot:**

- Persistidas em `Mensagem` com `tipoRemetente = 'bot'` e `idRemetente = NULL`.
- Cartões de prestador em `metadados`:
  ```json
  {
    "tipo": "sugestoes",
    "idEspecialidade": 4,
    "prestadores": [
      {"idUsuario": 12, "nome": "Maria Souza", "valor": 100, "periodo": "hora", "reputacao": 4.9}
    ]
  }
  ```
- O front renderiza os cartões; se o JS não estiver disponível, o texto da mensagem já contém a lista.

**Transferência:**

- Ação `chat_escolher_prestador` valida:
  - conversa pertence ao cliente;
  - estado é `bot`;
  - prestador existe, oferece a especialidade e não é o próprio cliente;
  - prestador está na lista sugerida (ou pelo menos oferece a especialidade).
- Transação:
  1. `UPDATE Conversa SET idPrestador = ?, idEspecialidade = ?, estado = 'ativa', ...`;
  2. insere mensagem de sistema (`tipoRemetente = 'sistema'`);
  3. insere mensagem do bot de handoff;
  4. cria/atualiza `ConversaParticipante` do prestador;
  5. enfileira e-mail de “novo cliente” para o prestador;
  6. atualiza o preview da conversa.
- Idempotência: se a conversa já foi transferida para o mesmo prestador, devolve sucesso; para outro, `409`.

### 6.3 Conversa ativa

- **Enviar:** cliente ou prestador participante, conversa `ativa`, ninguém bloqueado, limite de taxa ok.
- **Receber:** polling busca mensagens com `idMensagem > desde_id`.
- **Prestador não pode iniciar conversa:** ele só aparece como participante após a transferência.
- **Bloqueio:** se qualquer lado bloqueou o outro, o envio é recusado (`403`) e o compositor fica desabilitado com aviso. A conversa continua legível.
- **Encerramento:** não há botão no MVP; o estado `encerrada` fica reservado.

### 6.4 Não lidos, leitura e digitação

- **Não lidos:** mensagens com `idMensagem > ultimaMensagemLida` do participante, excluindo as próprias e as de sistema.
- **Marcar como lida:** ao abrir a conversa, ao receber mensagem com a aba visível e ao voltar o foco.
- **Confirmação de leitura:** o remetente vê “lida” quando a `ultimaMensagemLida` do outro participante for maior ou igual ao ID da mensagem.
- **Digitando:** o cliente envia `chat_digitando` no máximo a cada 3 s enquanto digita; o servidor grava `digitandoEm = agora`; o polling do outro lado mostra “digitando...” se `digitandoEm > agora - 6 s`.
- **Presença:** `ultimoVistoEm` é atualizado em cada polling; usado para decidir e-mail.

### 6.5 Anexos

- Um arquivo por mensagem no MVP.
- Tipos: `image/jpeg`, `image/png`, `image/webp`, `application/pdf`, `application/msword` (DOC) e `application/vnd.openxmlformats-officedocument.wordprocessingml.document` (DOCX).
- Tamanho máximo: 5 MB (ajustável em `.env`).
- Validação por `finfo_file`, não pela extensão.
- Diretório: `storage/chat` na raiz do projeto, adicionado ao `.gitignore`.
- Nome em disco: `YYYY/MM/<32 hex>.bin`; sem extensão executável e sem relação com o nome original.
- Conteúdo em disco criptografado com AES-256-GCM (`nonce + tag + ciphertext`), inclusive para imagens.
- Proteção do diretório:
  - `storage/.htaccess` com `Require all denied` (Apache 2.4) e `Deny from all` (Apache 2.2);
  - snippet de Nginx documentado no README (`location ^~ /storage/ { deny all; }`);
  - no servidor embutido do PHP o `.htaccess` é ignorado, mas os arquivos são `.bin` e só são servidos com segurança por `anexo.php`.
- Download por `anexo.php?id=<idAnexo>`:
  - exige sessão;
  - verifica se o usuário participa da conversa da mensagem;
  - envia `Content-Type` armazenado, `X-Content-Type-Options: nosniff` e `Content-Disposition: attachment`;
  - imagens podem ser exibidas inline com `Content-Security-Policy: default-src 'none'`;
  - PDF, DOC e DOCX sempre como anexo.
- Limpeza de órfãos: script opcional `src/scripts/limpar_anexos_orfaos.php` (fase 1.5).

### 6.6 E-mail

- **Regra principal:** uma notificação por conversa/cliente na **primeira mensagem não lida**.
  - Ao chegar mensagem, o sistema verifica se o destinatário já tem mensagens não lidas naquela conversa.
  - Se já tem, não envia outro e-mail; o lote de não lidas já foi avisado.
  - Quando o destinatário lê a conversa, a próxima mensagem não lida gera um novo e-mail.
  - Cada conversa gera um e-mail separado (um cliente por conversa).
- Também disparado na transferência para avisar o prestador do novo cliente.
- **Sem cron:** a requisição do chat só insere em `NotificacaoEmail`; o envio é feito por um **dispatcher oportunista**:
  - após a resposta de `chat_poll`/ações de chat, se `fastcgi_finish_request()` existir;
  - em carregamentos de `index.php`/`conta.php`, com lock de 60 s e no máximo 1 e-mail por execução;
  - fallback manual: `php src/scripts/processar_notificacoes.php`.
- **Gatilho externo opcional:** `notificacoes_cron.php?token=<CHAT_CRON_TOKEN>` para serviços gratuitos de cron HTTP (ex.: cron-job.org) quando o servidor não permite cron.
- **Retry:** até 3 tentativas; erro fica em `ultimoErro`; status `pendente`, `enviada` ou `erro`.
- **Conteúdo:** nome do remetente, prévia de até 120 caracteres (escapada) e link para `mensagens.php` sem expor o id da conversa. A conversa aberta fica na sessão (`chat_conversa_token`) e é identificada por `tokenPublico` opaco; o link do e-mail pode apontar apenas para a página de mensagens. Se a criptografia E2EE for adotada, a prévia deixa de existir e o e-mail só avisa “você tem uma nova mensagem”.
- Reutilizar a configuração SMTP/PHPMailer já usada na recuperação de senha.
- Como não há cron, o envio pode atrasar alguns minutos; documentar isso no README.

### 6.7 Bloquear e denunciar

- **Bloquear:** cria linha em `UsuarioBloqueio`; impede novas mensagens nos dois sentidos; não apaga histórico.
- **Desbloquear:** remove a linha; a conversa volta a permitir envio.
- **Denunciar:** grava `Denuncia` com motivo, descrição e, opcionalmente, a mensagem. Retorna confirmação genérica.
- **Sem painel admin no MVP:** consulta manual via SQL; a denúncia não bloqueia automaticamente o usuário.

---

## 7. Backend

### 7.1 Arquivos novos

| Caminho | Responsabilidade |
|---|---|
| `src/models/Conversation.php` | Model de `Conversa` |
| `src/models/ConversationParticipant.php` | Model de `ConversaParticipante` |
| `src/models/MessageAttachment.php` | Model de `MensagemAnexo` |
| `src/models/UserBlock.php` | Model de `UsuarioBloqueio` |
| `src/models/Report.php` | Model de `Denuncia` |
| `src/models/EmailNotification.php` | Model de `NotificacaoEmail` |
| `src/repositories/ConversationRepository.php` | Listagem, criação, transferência, estado |
| `src/repositories/MessageRepository.php` | Mensagens, paginação, não lidos, leitura |
| `src/repositories/ChatAttachmentRepository.php` | Metadados de anexos |
| `src/repositories/ChatSafetyRepository.php` | Bloqueios e denúncias |
| `src/repositories/ChatNotificationRepository.php` | Outbox de e-mail |
| `src/services/ChatService.php` | Regras de conversa, envio, transferência |
| `src/services/ChatbotService.php` | Triagem por regras (interface para LLM) |
| `src/services/ChatCryptoService.php` | AES-256-GCM em repouso para mensagens e anexos |
| `src/services/ChatAttachmentService.php` | Validação, gravação e leitura de arquivos |
| `src/services/ChatNotificationService.php` | Enfileiramento de e-mail e regra da primeira não lida |
| `src/services/ChatNotificationDispatcher.php` | Processa a outbox de forma oportunista ou por gatilho externo |
| `src/services/ChatRateLimiter.php` | Limites por usuário/conversa |
| `src/controllers/ChatController.php` | Ações do chat |
| `src/pages/mensagens.php` | Página dedicada |
| `src/pages/partials/chat_inbox.php` | Markup compartilhado entre página e aba |
| `src/scripts/chat.js` | Estado, polling, renderização e eventos |
| `src/stylesheets/chat.css` | Ajustes finos (se necessário; o grosso usa Tailwind) |
| `anexo.php` | Download autenticado de anexo |
| `src/scripts/processar_notificacoes.php` | Processa a outbox via CLI/manual (sem depender de cron) |
| `notificacoes_cron.php` | Gatilho HTTP opcional protegido por `CHAT_CRON_TOKEN` |
| `src/scripts/chatbot_keywords.php` | Dicionário de sinônimos da triagem |
| `storage/.htaccess` | Nega acesso direto ao diretório de anexos |
| `storage/chat/.gitkeep` | Mantém a pasta no repositório sem versionar arquivos |
| `src/services/Channels/ChannelAdapter.php` | Interface para a fase 2 |
| `src/services/Channels/ChannelRegistry.php` | Resolve adapter por canal (fase 2) |

### 7.2 Arquivos alterados

| Caminho | Alteração |
|---|---|
| `processar.php` | Novas rotas de chat + instanciação do `ChatController` |
| `src/models/Message.php` | Novos campos (`idConversa`, `tipoRemetente`, `canal`, `idExterno`, `criptografia`, `metadados`, `criadoEm`) |
| `src/repositories/CrudRepository.php` | Continua funcionando; `Message` passa a ter os novos campos |
| `src/repositories/ProviderRepository.php` | Método `searchBySpecialty` para o bot |
| `src/pages/conta.php` | Nova aba “Mensagens” + ativação por `?aba=mensagens` |
| `index.php` | Item “Mensagens” no menu (desktop/mobile) + badge de não lidas + busca da home |
| `src/scripts/config.php` | Timezone padrão e constantes do chat |
| `.env.example` | Novas variáveis |
| `.gitignore` | Ignora os arquivos de `storage/chat`, mantendo `.htaccess` e `.gitkeep` |
| `README.md` | Documentação do chat, storage e dispatcher de e-mail sem cron |

### 7.3 Endpoints

Todos os endpoints abaixo são `POST` em `processar.php` com `acao=<nome>` e `csrf_token`, exceto o download. **Nenhuma ação de conversa recebe o id numérico:** use sempre `token` (`Conversa.tokenPublico`, 32 hex), resolvido no servidor com `ChatService::resolveConversationToken()`. O mesmo vale para as ações introduzidas nos Sprints 2 e 3.

| Ação | Quem pode | Parâmetros principais | Retorno |
|---|---|---|---|
| `chat_listar_conversas` | Autenticado | `filtro` (opcional) | Lista de conversas com não lidas |
| `chat_iniciar_bot` | Cliente autenticado | `mensagem_inicial` (opcional) | Conversa e mensagens iniciais |
| `chat_escolher_prestador` | Cliente da conversa | `token`, `idPrestador` | Mensagens de transferência |
| `chat_listar_mensagens` | Participante | `token`, `antes_de_id` (opcional) | Mensagens + `tem_mais` |
| `chat_enviar_mensagem` | Participante | `token`, `texto`, `anexo` (opcional) | Mensagem criada + respostas do bot, se for o caso |
| `chat_marcar_lidas` | Participante | `token`, `ultima_mensagem_id` | OK |
| `chat_avaliar` | Participante da conversa encerrada | `token`, `nota` (1–5) | Conversa atualizada + média recalculada |
| `chat_digitando` | Participante | `token` | OK |
| `chat_poll` | Autenticado | `token` (opcional), `desde_id` (opcional) | Novas mensagens, leituras, digitação, totais |
| `chat_bloquear` | Participante | `token`, `motivo` (opcional) | OK |
| `chat_desbloquear` | Participante | `token` | OK |
| `chat_denunciar` | Participante | `token`, `motivo`, `descricao`, `idMensagem` (opcional) | OK |
| `chat_focar_conversa` / `chat_limpar_foco` | Participante | `token` / — | Grava ou limpa `chat_conversa_token` na sessão |
| `chat_nao_lidas` | Autenticado | — | Total de não lidas para o badge |
| `GET anexo.php?token=` | Participante | `token` opaco do anexo | Arquivo |

Para o download, não usar `idAnexo` sequencial na URL: adicionar `tokenPublico` em `MensagemAnexo` (migration própria) e validar participação na conversa antes de servir o arquivo.

**Formato de resposta:** manter `{ "sucesso": bool, "mensagem": string, ... }` como no restante do projeto. Códigos HTTP: `200`, `400` validação, `401` sem login, `403` CSRF/bloqueio/sem permissão, `404` não encontrado, `409` conflito de estado, `429` limite de taxa, `500` erro genérico.

### 7.4 Contrato do polling

```json
{
  "sucesso": true,
  "servidorEm": "2026-11-19T14:32:10-03:00",
  "conversa": {
    "id": 42,
    "estado": "ativa",
    "idPrestador": 12,
    "ultimaMensagemEm": "2026-11-19 14:32:08"
  },
  "mensagens": [
    {
      "id": 340,
      "tipoRemetente": "usuario",
      "idRemetente": 12,
      "texto": "Consigo sim, às 14h.",
      "criadoEm": "2026-11-19 14:32:08",
      "anexo": null,
      "metadados": null
    }
  ],
  "leituras": [
    { "idUsuario": 12, "ultimaMensagemLida": 340 }
  ],
  "digitando": [12],
  "naoLidas": { "total": 3, "porConversa": { "42": 2, "39": 1 } }
}
```

- Sem `conversa_id`, retorna apenas o resumo da inbox e o total de não lidas.
- Com `conversa_id`, retorna também as mensagens novas a partir de `desde_id`.
- O cliente deve enviar `desde_id` igual ao maior ID já recebido.
- Intervalo: 4 s na conversa aberta, 10 s na inbox, 30 s para o badge do menu.
- Pausar quando `document.hidden`; retomar no `visibilitychange`.
- Backoff em erro: 4 → 8 → 16 → 30 s.
- Respostas com `Cache-Control: no-store`.

### 7.5 Autorização

| Recurso | Regra |
|---|---|
| Listar conversas | Somente conversas em que o usuário é `idCliente` ou `idPrestador` |
| Ler mensagens | Participante da conversa |
| Enviar mensagem | Participante + conversa `ativa` (ou `bot` para o cliente) + sem bloqueio |
| Escolher prestador | Somente `idCliente`, conversa em `bot` |
| Marcar lida/digitando | Participante |
| Bloquear/denunciar | Participante; não pode bloquear a si mesmo |
| Baixar anexo | Participante da conversa da mensagem |

Toda consulta deve filtrar pelo usuário da sessão; nunca confiar em `idUsuario` vindo do cliente.

### 7.6 Limites de taxa

| Ação | Limite sugerido |
|---|---|
| Mensagens enviadas | 30 por minuto por usuário |
| Novas conversas | 10 por dia por cliente |
| Anexos | 10 por hora por usuário |
| Interações com o bot | 30 mensagens de bot por conversa por hora |
| Denúncias | 5 por dia por usuário |

Retornar `429` com `retry_after` em segundos. Os limites são configuráveis por `.env`.

### 7.7 Validação

- Texto: `trim`, 1 a 2000 caracteres; rejeitar vazio se não houver anexo.
- IDs: inteiros positivos; verificar existência e participação.
- Anexo: validar MIME real, tamanho e erro de upload.
- Bloqueio: motivo opcional até 255 caracteres.
- Denúncia: motivo em allowlist; descrição até 1000 caracteres.
- Nunca inserir HTML do usuário no banco como markup; armazenar texto puro e escapar na saída.

---

## 8. Frontend

### 8.1 Páginas e navegação

- **`src/pages/mensagens.php`**
  - Header no padrão de `conta.php` (logo + “Minhas mensagens” + voltar).
  - Layout de duas colunas no desktop; lista ↔ conversa no mobile.
  - Inclui `partials/chat_inbox.php`.
  - Gera `csrf_token` na sessão e expõe em `<meta name="csrf-token">`.
  - Carrega `chat.js`.
- **`src/pages/conta.php`**
  - Nova aba “Mensagens” ao lado de “Dados da conta” e “Cadastro de prestador”.
  - Reutiliza o mesmo partial e o mesmo `chat.js` em modo embutido.
  - `?aba=mensagens` ativa a aba; link do menu e do e-mail apontam para a página dedicada.
- **`index.php`**
  - Item “Mensagens” na sidebar e no menu mobile, com badge de não lidas.
  - Badge carregado no login e atualizado a cada 30 s (pausado quando a aba está oculta).
  - Botão “Buscar com IA” redireciona para o chat com o texto da busca.
- **`anexo.php`**
  - Endpoint de download; não é página visual.

### 8.2 Layout da inbox

**Desktop:**

```
┌─────────────────────────────────────────────────────────────┐
│ Header Roppin                                               │
├───────────────────┬─────────────────────────────────────────┤
│ Conversas         │  Maria Souza            [bloquear] [⋮]  │
│ [buscar...]       │  ─────────────────────────────────────  │
│                   │  (mensagens)                            │
│ • Assistente      │                                         │
│   O que você...   │                                         │
│ • Maria Souza  2  │  [digite sua mensagem...] [📎] [enviar]│
└───────────────────┴─────────────────────────────────────────┘
```

**Mobile:** lista em tela cheia; ao abrir uma conversa, a thread ocupa a tela com botão “voltar”.

**Item da lista:** avatar com inicial, nome, prévia da última mensagem, horário relativo, badge de não lidas, selo “Bot” quando aplicável, selo “Cliente”/“Prestador” quando o usuário tem os dois papéis.

**Thread:**

- Mensagens do usuário à direita; do outro participante à esquerda; do bot à esquerda com ícone de robô; sistema centralizado.
- Separadores de data (Hoje, Ontem, dd/mm/aaaa).
- Anexos: imagem com preview; PDF, DOC e DOCX com ícone + nome + tamanho.
- Cartões de sugestão do bot com nome, valor/período, reputação e botão “Conversar”.
- Indicador “digitando...” no rodapé do header.
- Confirmação de leitura nas mensagens enviadas (✓ / ✓✓).
- Botão “novas mensagens” quando o usuário rolou para cima.

### 8.3 Estados da UI

- Carregando lista / conversa.
- Lista vazia (“Nenhuma conversa ainda. Fale com o assistente para começar.”).
- Conversa vazia.
- Enviando / erro de envio (manter rascunho e permitir tentar de novo).
- Bloqueado (compositor desabilitado + aviso).
- Offline (navegador sem conexão; retomar polling).
- Bot transferido (mostrar aviso “Você está falando com X”).
- Prestador sem resposta (sem estado especial no MVP).

### 8.4 Acessibilidade

- `aria-live="polite"` para novas mensagens.
- Labels em todos os campos e botões.
- Navegação por teclado: `Enter` envia, `Shift+Enter` quebra linha; `Esc` fecha menus.
- Foco visível e gerenciado ao trocar de conversa no mobile.
- Contraste compatível com o tema vermelho/branco existente.

### 8.5 Integração com a home

- O texto digitado na busca é enviado como `mensagem_inicial` ao bot.
- Se o usuário não estiver logado, guardar a busca e abrir o login; após sucesso, redirecionar para o chat.
- O alerta atual de “especialidades disponíveis” deixa de existir; o bot passa a ser o caminho.

---

## 9. Segurança e privacidade

- **Autenticação:** todas as ações exigem `$_SESSION['usuario_logado']`.
- **CSRF:** `csrf_token` em todas as mutações, validado no controller com `hash_equals`.
- **IDOR:** toda consulta filtra pelo usuário da sessão; testar acesso a conversa/mensagem/anexo de terceiros.
- **SQL injection:** prepared statements em todas as consultas; allowlist para filtros/ordenação.
- **XSS:** armazenar texto puro; renderizar com `textContent`; nunca usar `innerHTML` com conteúdo do usuário. Em e-mails, escapar com `htmlspecialchars`.
- **Upload:** validar MIME real, limitar tamanho, nome aleatório `.bin`, `storage/chat` com acesso negado por `.htaccess`/Nginx, `nosniff`, `Content-Disposition`.
- **Rate limiting:** mensagens, conversas, anexos, denúncias e bot.
- **Spam:** cliente é o único que inicia; limite diário de novas conversas; bloqueio/denúncia.
- **Logs:** não registrar conteúdo de mensagens; apenas IDs, ação e erro.
- **LGPD:** exclusão de conta cascateia conversas e mensagens. Se for necessário preservar o histórico do outro lado, migrar para anonimização/soft delete.
- **E-mail:** não incluir conteúdo sensível além da prévia; link autenticado; não revelar existência de conta.
- **Timezone:** padronizar `America/Sao_Paulo` em `config.php` e gerar timestamps no PHP.
- **Sessão:** manter `HttpOnly`, `SameSite=Lax` e regeneração de ID já existentes.

### 9.1 Criptografia — análise e recomendação

O pedido de **E2EE** conflita com partes centrais do desenho atual. É importante separar três camadas:

| Camada | O que protege | Complexidade | Compatível com bot/e-mail/moderação? |
|---|---|---|---|
| **TLS (HTTPS)** | Dados em trânsito | Baixa (infra) | Sim |
| **AES-256-GCM em repouso** | Banco, backup, dump e acesso direto ao `storage` | Média | Sim (servidor descriptografa) |
| **E2EE real** | Conteúdo nem para o servidor | Alta | Não, sem mudar o bot para o navegador |

**E2EE real exige:**

- chaves privadas geradas e guardadas só no navegador (Web Crypto/IndexedDB);
- troca de chaves públicas por conversa (ECDH) e criptografia simétrica (AES-GCM);
- criptografia de anexos no navegador antes do upload;
- recuperação de chave (sem ela, o histórico é perdido);
- suporte a múltiplos dispositivos (backup de chave, o que enfraquece o modelo);
- bot **client-side** ou fora do E2EE, porque o servidor não consegue ler a mensagem;
- e-mail sem prévia de conteúdo;
- moderação/denúncia limitada (o usuário teria que compartilhar a mensagem descriptografada);
- busca de conteúdo apenas no cliente;
- proteção rigorosa contra XSS, porque um XSS rouba a chave e quebra o E2EE.

**Decisão do MVP:** TLS + **AES-256-GCM em repouso** para `textoMensagem` e anexos, com chave em `CHAT_ENCRYPTION_KEY` no `.env`. Isso protege backup/dump e o diretório `storage/chat`, sem inviabilizar o bot, o e-mail e a moderação. O desenho do `ChatCryptoService` fica isolado para permitir E2EE depois.

**Se E2EE for requisito de negócio:** planejar como fase própria (2.5 ou 3), decidir o que acontece com o bot (client-side ou conversa separada) e aceitar as limitações acima. Não é recomendável no MVP.

### 9.2 Chave de criptografia em repouso

- Gerar uma chave de 32 bytes: `base64_encode(random_bytes(32))`.
- Guardar em `CHAT_ENCRYPTION_KEY` no `.env` (nunca no Git).
- Formato do payload: `base64(nonce(12) + tag(16) + ciphertext)`.
- `ChatCryptoService` centraliza `encrypt`/`decrypt`; nenhum outro ponto usa `openssl_*` diretamente.
- Rotação de chave fica fora do MVP; documentar que perder a chave significa perder o conteúdo.
- Com criptografia em repouso, a prévia da inbox é descriptografada em memória; não há busca SQL por conteúdo.

---

## 10. Fase 2 — Canais externos

### 10.1 Modelo de roteamento

- Cada participante tem um canal preferido em `ConversaParticipante.canal` (`site`, `telegram`, `whatsapp`).
- `CanalVinculo` resolve `(canal, identificadorExterno) → usuário`.
- **Entrada:** webhook identifica o vínculo, encontra a conversa ativa mais recente daquele usuário naquele canal e grava a mensagem com `canal` e `idExterno`.
- **Saída:** ao gravar uma mensagem para um participante, o dispatcher verifica o canal dele; se não for `site`, chama o adapter e, em caso de falha, enfileira retry.
- Se o usuário não tem vínculo: o bot responde com um link para conectar a conta; não cria conta automaticamente.
- Se há mais de uma conversa ativa no mesmo canal: o bot pergunta sobre qual conversa é, ou usa a mais recente (decisão de UX da fase 2).

### 10.2 Telegram

- Criar bot no BotFather e configurar webhook HTTPS com secret token.
- Deep link `https://t.me/<bot>?start=<token>` para vínculo: o usuário logado gera o token; ao abrir o bot e enviar `/start <token>`, o webhook cria `CanalVinculo`.
- Inbound: texto, foto e documento; `getFile` para baixar mídia.
- Outbound: `sendMessage`, `sendPhoto`, `sendDocument`.
- Idempotência por `update_id`/`message_id` em `Mensagem.idExterno`.
- Vantagem: simples, sem aprovação de negócio; recomendado como primeiro canal.

### 10.3 WhatsApp Cloud API

- Requisitos: conta Meta Business, número dedicado, token permanente, webhook HTTPS verificado.
- Verificação do webhook (`hub.challenge`) e validação de assinatura `X-Hub-Signature-256`.
- Inbound: texto, imagem, documento; download de mídia pela Graph API.
- Outbound: mensagens livres dentro da janela de 24 h; fora dela, usar **templates aprovados**.
- Status de entrega/leitura via webhook.
- Custos por conversa e aprovação de templates; planejar com antecedência.
- Não usar soluções não oficiais (risco de banimento e violação de termos).

### 10.4 Interface de adapter

```php
interface ChannelAdapter
{
    public function enviarTexto(string $destino, string $texto): bool;
    public function enviarAnexo(string $destino, array $anexo): bool;
    public function normalizarEntrada(array $payload): array;
}
```

- `normalizarEntrada` devolve `canal`, `idExterno`, `identificador`, `texto`, `anexos`.
- `ChannelRegistry` resolve o adapter pelo nome do canal.
- O MVP já cria a interface e o registry, mesmo sem implementações.

### 10.5 OTP / vínculo

- **Telegram:** deep link com token de uso único (mais simples).
- **WhatsApp:** usuário logado informa o número; servidor gera código de 6 dígitos, guarda o hash em `CanalVerificacao` (validade 10 min), envia pelo canal; usuário responde o código; servidor valida e cria `CanalVinculo`.
- Limitar tentativas, expirar, uso único e não revelar se o número já existe.
- Tela de vínculo em `conta.php` (nova aba “Canais” na fase 2).

---

## 11. Fase 3 — IA real

- `ChatbotService` vira uma interface com duas implementações: `RulesChatbot` (MVP) e `LlmChatbot` (fase 3).
- Provedor via HTTP/cURL; chave em `.env` (ex.: `OPENAI_API_KEY`, `OPENAI_MODEL`).
- Prompt de sistema com:
  - papel de assistente de triagem da Roppin;
  - regras: não negociar preço, não pedir dados sensíveis, não compartilhar contato externo, não prometer serviço;
  - ferramenta `buscar_prestadores(especialidade)` e `listar_especialidades()`;
  - contexto das últimas N mensagens (truncado).
- Guardrails:
  - timeout e fallback para regras;
  - limite de tokens por conversa/dia;
  - moderação de entrada/saída;
  - não enviar CPF/e-mail/telefone ao LLM;
  - log de custo por conversa.
- Geolocalização: adicionar `Prestador.latitude/longitude` (ou endereço + geocoding) e ordenar por distância.
- Moderação automática: classificar denúncias e esconder mensagens com score alto (revisão humana depois).

---

## 12. Plano de execução

### Sprint 1 — Fundação (3–4 dias)

- [ ] Criar migrations de núcleo, anexos, segurança e outbox.
- [ ] Rodar migrations em banco limpo e em banco existente.
- [ ] Atualizar `Message` e criar models/repositories.
- [ ] Implementar `ChatCryptoService` (AES-256-GCM) e criptografar mensagens em repouso.
- [ ] Implementar `ChatService` (criar conversa, enviar, listar, não lidos, leitura).
- [ ] Implementar `ChatController` e rotas no `processar.php`.
- [ ] Criar `mensagens.php` com inbox textual (sem bot, sem anexos).
- [ ] Adicionar aba “Mensagens” em `conta.php` e item no menu.
- [ ] CSRF, autorização e testes de IDOR.

**Aceite:** cliente e prestador conversam em texto; não lidos e leitura funcionam; ninguém acessa conversa alheia; o texto fica cifrado no banco.

### Sprint 2 — Bot e tempo quase real (2–3 dias)

- [ ] Implementar `ChatbotService` com match por palavras-chave.
- [ ] Criar `src/scripts/chatbot_keywords.php` e testes de cenários.
- [ ] Implementar cartões de sugestão e transferência.
- [ ] Implementar polling, digitação e confirmação de leitura — todas as novas ações recebem `token`, nunca `conversa_id`.
- [ ] Integrar a busca da home ao bot: ao criar/transferir a conversa, gravar o `token` retornado em `$_SESSION['chat_conversa_token']` e abrir `mensagens.php` sem query string.
- [ ] Badge de não lidas no menu.

**Aceite:** cliente descreve necessidade, recebe sugestões, escolhe prestador, o prestador vê a conversa e o bot para de responder; nenhuma nova ação reintroduz o id numérico na URL ou no payload.

### Sprint 3 — Anexos, segurança e e-mail (2–3 dias)

- [ ] Upload/download de anexos com validação e criptografia em repouso; o download usa `tokenPublico` do anexo, não `idAnexo`, e valida participação na conversa.
- [ ] Bloquear/desbloquear e denunciar — todas as ações recebem `token` da conversa.
- [ ] Outbox de e-mail + dispatcher oportunista (sem cron). O link do e-mail aponta para `mensagens.php` sem parâmetro; se for preciso abrir uma conversa específica, usar token de acesso de uso único em rota que redireciona para `mensagens.php` com URL limpa.
- [ ] Limites de taxa — chavear por usuário/sessão, não por id de conversa exposto.
- [ ] Ajustes de responsividade, acessibilidade e estados vazios.
- [ ] Testes manuais roteirizados e revisão de segurança, incluindo a checagem de que `conversa_id`, `data-conversation-id` e `?conversa=<id>` não voltam no HTML/JSON.

**Aceite:** anexos abrem com segurança e ficam cifrados no disco; bloqueio impede envio; e-mail chega quando offline; limites funcionam; nenhum id sequencial de conversa aparece na URL.

### Fase 2 — Canais (5–8 dias)

- [ ] Interface `ChannelAdapter` e registry.
- [ ] Telegram: vínculo por deep link, webhook, texto e mídia.
- [ ] WhatsApp Cloud API: verificação, webhook, texto, mídia, templates.
- [ ] Roteamento por participante, idempotência e retry.
- [ ] Tela de canais na conta.
- [ ] Testes com números reais e ambiente de homologação.

### Fase 3 — IA real (3–5 dias)

- [ ] `LlmChatbot` com function calling.
- [ ] Guardrails, moderação e limites de custo.
- [ ] Geolocalização e ordenação por distância.
- [ ] Métricas e painel administrativo.

---

## 13. Testes e critérios de aceite

### 13.1 Migrations

- Banco limpo: `php src/scripts/migrate.php` aplica tudo sem erro.
- Banco existente: mensagens antigas viram conversas `encerrada` e continuam legíveis.
- Rodar novamente: nenhuma migration é reaplicada.
- Checksum: editar migration aplicada gera erro (comportamento esperado).

### 13.2 Fluxo funcional

- [ ] Cliente logado abre o bot pela busca da home com texto pré-preenchido.
- [ ] Bot identifica especialidade e sugere até 3 prestadores.
- [ ] Cliente escolhe prestador; conversa vira `ativa`; prestador recebe não lida + e-mail.
- [ ] Prestador responde; cliente recebe via polling.
- [ ] Não lidos, leitura e “digitando...” funcionam nos dois sentidos.
- [ ] Anexo de imagem e PDF funciona; arquivo inválido/grande é recusado.
- [ ] Bloqueio impede envio nos dois sentidos; desbloqueio restaura.
- [ ] Denúncia é gravada.
- [ ] Aba “Mensagens” da conta abre a mesma inbox.
- [ ] Badge do menu atualiza.
- [ ] Layout funciona em celular e desktop.

### 13.3 Segurança

- [ ] Acessar conversa de outro usuário retorna `403/404`.
- [ ] Baixar anexo de outra conversa retorna `403/404`.
- [ ] POST sem CSRF retorna `403`.
- [ ] Upload de `.php`/`.svg`/executável é recusado.
- [ ] Texto com `<script>` é exibido como texto, não executado.
- [ ] Prestador não consegue iniciar conversa nem enviar antes da transferência.
- [ ] Rate limit retorna `429`.
- [ ] Mensagens novas estão cifradas no banco (`SELECT` não mostra o texto legível).
- [ ] A API devolve o texto decifrado apenas para os participantes.
- [ ] Mensagens legadas com `criptografia='nenhuma'` continuam legíveis.
- [ ] O arquivo `.bin` no disco não é legível diretamente; `anexo.php` decifra e entrega.
- [ ] Chave ausente/incorreta gera erro controlado, sem quebrar a inbox.

### 13.4 E-mail e operação

- [ ] E-mail sai na primeira mensagem não lida da conversa.
- [ ] Enquanto houver não lidas, não dispara novo e-mail; após a leitura, a próxima mensagem dispara.
- [ ] Retry e status de erro ficam registrados.
- [ ] Dispatcher oportunista funciona sem cron; gatilho externo opcional responde ao token.
- [ ] `storage/chat` não é acessível por URL direta em Apache/Nginx; arquivos são `.bin` e só saem por `anexo.php`.

---

## 14. Deploy e operação

### 14.1 Novas variáveis de ambiente

```env
APP_TIMEZONE=America/Sao_Paulo

CHAT_ENABLED=true
CHAT_UPLOAD_DIR=storage/chat
CHAT_ENCRYPTION_KEY=
CHAT_MAX_UPLOAD_MB=5
CHAT_POLL_INTERVAL_MS=4000
CHAT_EMAIL_OFFLINE_MIN=2
CHAT_EMAIL_MIN_INTERVAL_MIN=5
CHAT_EMAIL_DISPATCH=opportunistic
CHAT_CRON_TOKEN=
CHAT_MAX_MESSAGES_PER_MINUTE=30
CHAT_MAX_NEW_CONVERSATIONS_PER_DAY=10
```

- `CHAT_UPLOAD_DIR=storage/chat` é relativo à raiz do projeto, conforme decidido. Proteger com `storage/.htaccess` e, em Nginx, `location ^~ /storage/ { deny all; }`.
- `CHAT_ENCRYPTION_KEY` é uma chave de 32 bytes em base64 e é obrigatória. Se estiver ausente ou inválida, o chat deve falhar de forma controlada (não gravar mensagens em texto puro); apenas um ambiente local explícito pode rodar em modo `nenhuma`.
- `CHAT_EMAIL_MIN_INTERVAL_MIN` é apenas uma proteção contra abuso; a regra principal é “primeira não lida”.
- `CHAT_CRON_TOKEN` habilita o gatilho HTTP opcional para serviços externos de cron.
- `CHAT_ENABLED=false` esconde o menu e bloqueia as rotas (útil para deploy incremental).

### 14.2 Passos de deploy

1. Backup do banco.
2. Criar `storage/chat` e `storage/.htaccess`; dar permissão de escrita ao PHP (ex.: `750`).
3. Adicionar ao `.gitignore`:
   ```gitignore
   /storage/chat/*
   !/storage/chat/.gitkeep
   ```
4. Configurar as variáveis no `.env`, incluindo `CHAT_ENCRYPTION_KEY`.
5. Rodar `php src/scripts/migrate.php`.
6. **Sem cron:** o dispatcher oportunista processa a outbox nos carregamentos de página e após o polling. Opcionalmente, cadastrar um serviço externo (ex.: cron-job.org) chamando `https://seu-dominio/notificacoes_cron.php?token=<CHAT_CRON_TOKEN>` a cada 1–2 minutos.
7. Testar login, chat, anexo e e-mail.
8. Ativar o item de menu (feature flag).

### 14.3 Monitoramento

- `error_log` para falhas de envio, upload e bot.
- Backlog da outbox (`status = 'pendente'` e `criadaEm` antiga).
- Última execução do dispatcher (para detectar outbox parada sem cron).
- Espaço em disco dos anexos.
- Tempo de resposta do polling e volume de queries.
- Denúncias abertas (consulta SQL manual no MVP).

### 14.4 Rollback

- As migrations são one-way; restaurar backup se necessário.
- Desativar `CHAT_ENABLED` esconde a funcionalidade sem remover tabelas.
- As colunas antigas de `Mensagem` são preservadas; o código anterior continua lendo `textoMensagem`, `data` e `hora`.

---

## 15. Riscos e decisões pendentes

| # | Ponto | Risco/impacto | Recomendação |
|---|---|---|---|
| 1 | Não existe página pública de prestador nem listagem | Cliente só consegue iniciar conversa pelo bot; contato direto fica limitado | MVP via cartões do bot; criar perfil público do prestador na sequência |
| 2 | Geolocalização não existe no schema | Recomendação não considera distância, apesar do discurso do site | Deixar claro no MVP; adicionar lat/lng na fase 3 |
| 3 | Exclusão de conta | Cascata confirmada: o outro lado perde o histórico | Manter CASCADE no MVP; reavaliar anonimização se surgir exigência legal |
| 4 | Cron na hospedagem | Sem cron, e-mails podem atrasar | Dispatcher oportunista + gatilho externo opcional; documentar o atraso |
| 5 | Storage na raiz | `storage/chat` fica dentro do webroot | `.htaccess`/Nginx deny, arquivos `.bin`, download só por `anexo.php` |
| 6 | Sem painel admin | Denúncias não têm tratamento dentro do sistema | Registrar e tratar via SQL no MVP; painel na fase 3 |
| 7 | Reutilização de conversa | Múltiplas conversas com o mesmo prestador confundem | Reutilizar a conversa `ativa` do par; criar nova só se a anterior estiver `encerrada` |
| 8 | Prestador não responde | Cliente fica sem retorno | MVP não reatribui; fase 3 pode sugerir outro prestador após timeout |
| 9 | E-mail | Usuário pode não querer notificação | Regra da primeira não lida; preferência de usuário na fase 3 |
| 10 | Limite de anexo | 5 MB pode ser pouco para DOCX/fotos | Ajustar via `.env`; compressão no cliente é opcional |
| 11 | WhatsApp oficial | Exige Meta Business, número, templates e janela de 24 h | Planejar homologação com antecedência; Telegram primeiro |
| 12 | Polling | Muitos usuários geram muitas requisições | Índices corretos, pausa em aba oculta, backoff; WebSocket só se necessário |
| 13 | Timezone | Datas inconsistentes entre PHP e MySQL | Padronizar `America/Sao_Paulo` e gerar timestamps no PHP |
| 14 | Bot ver histórico | Prestador vê o que o cliente falou ao bot | Transparência é desejável; avisar na política de privacidade |
| 15 | Conteúdo do bot | Regras podem errar a especialidade | Fallback para lista de categorias e opção “voltar” |
| 16 | E2EE real | Incompatível com bot server-side, prévia de e-mail e moderação | MVP com TLS + AES-256-GCM em repouso; E2EE como fase própria |
| 17 | Chave de criptografia | Perda da chave torna o conteúdo ilegível | Backup seguro do `.env`; documentar; rotação fora do MVP |
| 18 | `.htaccess` no servidor embutido | O PHP built-in server ignora `.htaccess` | Arquivos `.bin` + download por PHP; documentar limitação local |

### Decisões confirmadas

1. **Criptografia:** TLS + AES-256-GCM em repouso; E2EE real fica para fase futura.
2. **Anexos:** 5 MB para todos os tipos, incluindo DOC/DOCX.
3. **Exclusão de conta:** cascata.
4. **Storage:** `storage/chat` na raiz, protegido e fora do Git.
5. **Cron:** sem cron; dispatcher oportunista + gatilho externo opcional.
6. **E-mail:** primeira mensagem não lida por conversa.
7. **Conversa por par:** reutiliza a ativa.
8. **WhatsApp:** API oficial da Meta.

---

## 16. Próximos passos

1. Aprovar este plano e a ordem dos sprints.
2. Implementar o Sprint 1 (schema + backend + inbox textual).
3. Validar com dois navegadores (cliente e prestador) antes de seguir para o bot.
4. Implementar o Sprint 2 (bot + polling) e o Sprint 3 (anexos, segurança e e-mail).
5. Planejar a fase 2 (Telegram/WhatsApp) somente depois do MVP estável.
