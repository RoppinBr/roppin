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

CREATE TABLE IF NOT EXISTS ConversaParticipante (
    idConversa INT NOT NULL,
    idUsuario INT NOT NULL,
    papel VARCHAR(10) NOT NULL,
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

INSERT INTO Conversa (idCliente, idPrestador, estado, criadaEm, atualizadaEm)
SELECT
    LEAST(m.idRemetente, m.idDestinatario),
    GREATEST(m.idRemetente, m.idDestinatario),
    'encerrada',
    COALESCE(MIN(STR_TO_DATE(CONCAT(m.data, ' ', m.hora), '%Y-%m-%d %H:%i:%s')), NOW()),
    COALESCE(MAX(STR_TO_DATE(CONCAT(m.data, ' ', m.hora), '%Y-%m-%d %H:%i:%s')), NOW())
FROM Mensagem m
WHERE m.idConversa IS NULL
  AND m.idRemetente IS NOT NULL
  AND m.idDestinatario IS NOT NULL
GROUP BY LEAST(m.idRemetente, m.idDestinatario), GREATEST(m.idRemetente, m.idDestinatario);

UPDATE Mensagem m
JOIN (
    SELECT idCliente, idPrestador, MIN(idConversa) AS idConversa
    FROM Conversa
    WHERE estado = 'encerrada'
    GROUP BY idCliente, idPrestador
) c
    ON c.idCliente = LEAST(m.idRemetente, m.idDestinatario)
   AND c.idPrestador = GREATEST(m.idRemetente, m.idDestinatario)
SET m.idConversa = c.idConversa
WHERE m.idConversa IS NULL
  AND m.idRemetente IS NOT NULL
  AND m.idDestinatario IS NOT NULL;

UPDATE Mensagem
SET criadoEm = STR_TO_DATE(CONCAT(data, ' ', hora), '%Y-%m-%d %H:%i:%s')
WHERE data IS NOT NULL AND hora IS NOT NULL;

ALTER TABLE Mensagem
    MODIFY COLUMN idConversa INT NOT NULL,
    ADD CONSTRAINT fk_mensagem_conversa
        FOREIGN KEY (idConversa) REFERENCES Conversa(idConversa) ON DELETE CASCADE,
    ADD CONSTRAINT FK_Mensagem_Remetente
        FOREIGN KEY (idRemetente) REFERENCES Usuario(idUsuario) ON DELETE SET NULL,
    ADD CONSTRAINT FK_Mensagem_Destinatario
        FOREIGN KEY (idDestinatario) REFERENCES Usuario(idUsuario) ON DELETE SET NULL;
