CREATE TABLE IF NOT EXISTS RememberMeTokens (
    selector CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    usuario_id INT NOT NULL,
    validator_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    criado_em DATETIME NOT NULL,
    expira_em DATETIME NOT NULL,
    PRIMARY KEY (selector),
    KEY idx_remember_me_user (usuario_id),
    CONSTRAINT fk_remember_me_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES Usuario(idUsuario)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;