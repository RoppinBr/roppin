ALTER TABLE Usuario
    ADD COLUMN ehConvidado TINYINT(1) NOT NULL DEFAULT 0 AFTER media_reputacao;

CREATE TABLE IF NOT EXISTS ChatConvidadoSessao (
    idSessao INT AUTO_INCREMENT PRIMARY KEY,
    tokenHash CHAR(64) NOT NULL,
    idUsuario INT NOT NULL,
    criadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expiraEm DATETIME NOT NULL,
    ultimoUsoEm DATETIME NULL,
    UNIQUE KEY uq_chat_convidado_token (tokenHash),
    KEY idx_chat_convidado_usuario (idUsuario),
    KEY idx_chat_convidado_expira (expiraEm),
    CONSTRAINT fk_chat_convidado_usuario
        FOREIGN KEY (idUsuario) REFERENCES Usuario(idUsuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
