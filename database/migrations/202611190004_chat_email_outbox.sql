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
    KEY idx_notificacao_status (status, criadaEm),
    KEY idx_notificacao_usuario_conversa (idUsuario, idConversa, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
