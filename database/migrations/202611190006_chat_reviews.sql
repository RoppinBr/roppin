ALTER TABLE Avaliacao
    ADD COLUMN idConversa INT NULL AFTER idAvaliacao,
    ADD KEY idx_avaliacao_conversa (idConversa),
    ADD UNIQUE KEY uq_avaliacao_conversa_avaliador (idConversa, idAvaliador),
    ADD CONSTRAINT FK_Avaliacao_Conversa
        FOREIGN KEY (idConversa) REFERENCES Conversa(idConversa) ON DELETE CASCADE;

ALTER TABLE Avaliacao DROP FOREIGN KEY FK_Avaliacao_Avaliador;
ALTER TABLE Avaliacao DROP INDEX FK_Avaliacao_Avaliador;
ALTER TABLE Avaliacao
    ADD CONSTRAINT FK_Avaliacao_Avaliador
        FOREIGN KEY (idAvaliador) REFERENCES Usuario(idUsuario) ON DELETE CASCADE;

ALTER TABLE Avaliacao DROP FOREIGN KEY FK_Avaliacao_Avaliado;
ALTER TABLE Avaliacao DROP INDEX FK_Avaliacao_Avaliado;
ALTER TABLE Avaliacao
    ADD CONSTRAINT FK_Avaliacao_Avaliado
        FOREIGN KEY (idAvaliado) REFERENCES Usuario(idUsuario) ON DELETE CASCADE;
