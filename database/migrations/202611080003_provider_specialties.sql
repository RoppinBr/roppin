CREATE TABLE IF NOT EXISTS PrestadorEspecialidade (
    idUsuario INT NOT NULL,
    idEspecialidade INT NOT NULL,
    PRIMARY KEY (idUsuario, idEspecialidade),
    KEY idx_prestador_especialidade_especialidade (idEspecialidade),
    CONSTRAINT fk_prestador_especialidade_prestador
        FOREIGN KEY (idUsuario)
        REFERENCES Prestador(idUsuario)
        ON DELETE CASCADE,
    CONSTRAINT fk_prestador_especialidade_especialidade
        FOREIGN KEY (idEspecialidade)
        REFERENCES Especialidade(idEspecialidade)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO PrestadorEspecialidade (idUsuario, idEspecialidade)
SELECT idUsuario, idEspecialidade
FROM Prestador;

ALTER TABLE Prestador
    DROP FOREIGN KEY Prestador_ibfk_2,
    DROP INDEX idEspecialidade,
    DROP COLUMN idEspecialidade;