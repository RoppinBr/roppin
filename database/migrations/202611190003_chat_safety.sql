CREATE TABLE IF NOT EXISTS UsuarioBloqueio (
    idBloqueador INT NOT NULL,
    idBloqueado INT NOT NULL,
    motivo VARCHAR(255) NULL,
    criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (idBloqueador, idBloqueado),
    CONSTRAINT fk_bloqueio_bloqueador
        FOREIGN KEY (idBloqueador) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    CONSTRAINT fk_bloqueio_bloqueado
        FOREIGN KEY (idBloqueado) REFERENCES Usuario(idUsuario) ON DELETE CASCADE,
    KEY idx_bloqueio_bloqueado (idBloqueado, idBloqueador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
        FOREIGN KEY (idMensagem) REFERENCES Mensagem(idMensagem) ON DELETE SET NULL,
    KEY idx_denuncia_status (status, criadaEm),
    KEY idx_denuncia_denunciado (idDenunciado, criadaEm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
