ALTER TABLE Prestador
    ADD COLUMN periodo_cobranca VARCHAR(12) NOT NULL DEFAULT 'hora' AFTER valor;