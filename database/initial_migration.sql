-- ==========================================
-- USUÁRIO
-- ==========================================
CREATE TABLE IF NOT EXISTS Usuario (
    idUsuario INT AUTO_INCREMENT PRIMARY KEY,
    idPessoa INT NOT NULL,
    tipoPessoa CHAR(2) NOT NULL, -- Valores esperados: 'PF' ou 'PJ'

    cpf CHAR(11) UNIQUE,
    nome VARCHAR(100),

    cnpj CHAR(14) UNIQUE,
    razaoSocial VARCHAR(150),

    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    media_reputacao DECIMAL(3,2)
);

-- ==========================================
-- ESPECIALIDADE
-- ==========================================
CREATE TABLE IF NOT EXISTS Especialidade (
    idEspecialidade INT AUTO_INCREMENT PRIMARY KEY,
    nomeEspecialidade VARCHAR(100) NOT NULL
);

-- ==========================================
-- TOMADOR
-- ==========================================
CREATE TABLE IF NOT EXISTS Tomador (
    idUsuario INT PRIMARY KEY,
    endereco VARCHAR(200),
    telefone VARCHAR(20),

    FOREIGN KEY (idUsuario)
        REFERENCES Usuario(idUsuario)
);

-- ==========================================
-- PRESTADOR
-- ==========================================
CREATE TABLE IF NOT EXISTS Prestador (
    idUsuario INT PRIMARY KEY,
    idEspecialidade INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,

    FOREIGN KEY (idUsuario)
        REFERENCES Usuario(idUsuario),

    FOREIGN KEY (idEspecialidade)
        REFERENCES Especialidade(idEspecialidade)
);

-- ==========================================
-- CHAMADO
-- ==========================================
CREATE TABLE IF NOT EXISTS Chamado (
    idChamado INT AUTO_INCREMENT PRIMARY KEY,
    horario DATETIME NOT NULL,
    idEspecialidade INT NOT NULL,

    idTomador INT NOT NULL,
    idPrestador INT NOT NULL,

    FOREIGN KEY (idEspecialidade)
        REFERENCES Especialidade(idEspecialidade),

    FOREIGN KEY (idTomador)
        REFERENCES Tomador(idUsuario),

    FOREIGN KEY (idPrestador)
        REFERENCES Prestador(idUsuario)
);

-- ==========================================
-- PAGAMENTO
-- ==========================================
CREATE TABLE IF NOT EXISTS Pagamento (
    idPagamento INT AUTO_INCREMENT PRIMARY KEY,
    idChamado INT NOT NULL,
    meioPagamento VARCHAR(50) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data DATE NOT NULL,
    hora TIME NOT NULL,
    banco VARCHAR(100),

    FOREIGN KEY (idChamado)
        REFERENCES Chamado(idChamado)
);

-- ==========================================
-- AVALIACAO
-- ==========================================
CREATE TABLE IF NOT EXISTS Avaliacao (
    idAvaliacao INT AUTO_INCREMENT PRIMARY KEY,
    nota DECIMAL(3,1) NOT NULL,
    data DATE NOT NULL,
    hora TIME NOT NULL,

    idAvaliador INT NOT NULL,
    idAvaliado INT NOT NULL,

    CONSTRAINT FK_Avaliacao_Avaliador
        FOREIGN KEY (idAvaliador)
        REFERENCES Usuario(idUsuario),

    CONSTRAINT FK_Avaliacao_Avaliado
        FOREIGN KEY (idAvaliado)
        REFERENCES Usuario(idUsuario)
);

-- ==========================================
-- MENSAGEM
-- ==========================================
CREATE TABLE IF NOT EXISTS Mensagem (
    idMensagem INT AUTO_INCREMENT PRIMARY KEY,
    textoMensagem TEXT NOT NULL,
    data DATE NOT NULL,
    hora TIME NOT NULL,

    idRemetente INT NOT NULL,
    idDestinatario INT NOT NULL,

    CONSTRAINT FK_Mensagem_Remetente
        FOREIGN KEY (idRemetente)
        REFERENCES Usuario(idUsuario),

    CONSTRAINT FK_Mensagem_Destinatario
        FOREIGN KEY (idDestinatario)
        REFERENCES Usuario(idUsuario)
);