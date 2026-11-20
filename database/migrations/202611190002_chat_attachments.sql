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
        FOREIGN KEY (idMensagem) REFERENCES Mensagem(idMensagem) ON DELETE CASCADE,
    KEY idx_anexo_mensagem (idMensagem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
