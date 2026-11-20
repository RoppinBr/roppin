ALTER TABLE Conversa
    ADD COLUMN tokenPublico CHAR(32) NULL AFTER idConversa,
    ADD UNIQUE KEY uq_conversa_token_publico (tokenPublico);

UPDATE Conversa
SET tokenPublico = LOWER(HEX(RANDOM_BYTES(16)))
WHERE tokenPublico IS NULL OR tokenPublico = '';

ALTER TABLE Conversa MODIFY COLUMN tokenPublico CHAR(32) NOT NULL;
