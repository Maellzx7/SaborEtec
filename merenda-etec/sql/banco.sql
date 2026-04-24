

CREATE DATABASE IF NOT EXISTS merenda_etec CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE merenda_etec;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('aluno','supervisor','sub_supervisor') NOT NULL DEFAULT 'aluno',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    ingredientes TEXT,
    modo_preparo TEXT,
    calorias INT,
    proteinas DECIMAL(5,2),
    carboidratos DECIMAL(5,2),
    gorduras DECIMAL(5,2),
    foto VARCHAR(255),
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cardapio_semana (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia_semana ENUM('segunda','terca','quarta','quinta','sexta') NOT NULL,
    prato_id INT NOT NULL,
    data_referencia DATE NOT NULL,
    FOREIGN KEY (prato_id) REFERENCES pratos(id) ON DELETE CASCADE,
    UNIQUE KEY unico_dia_data (dia_semana, data_referencia)
);

CREATE TABLE IF NOT EXISTS novidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,
    tipo ENUM('mudanca','aviso','info') NOT NULL DEFAULT 'info',
    usuario_id INT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

INSERT INTO usuarios (nome, email, senha, perfil) VALUES
('Supervisor ETEC', 'supervisor@etec.sp.gov.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supervisor');

INSERT INTO pratos (nome, descricao, ingredientes, modo_preparo, calorias, proteinas, carboidratos, gorduras, foto) VALUES
('Arroz com Feijão e Frango Grelhado', 'Prato clássico brasileiro nutritivo e saboroso', 'Arroz branco, feijão carioca, peito de frango, alho, cebola, sal, óleo', 'Cozinhe o arroz e o feijão separadamente. Tempere o frango com alho e sal e grelhe até dourar. Sirva junto.', 520, 35.5, 68.0, 8.2, NULL),
('Macarrão ao Molho de Tomate com Carne Moída', 'Macarrão nutritivo com molho caseiro', 'Macarrão espaguete, carne moída, tomates, alho, cebola, sal, azeite, manjericão', 'Cozinhe o macarrão al dente. Refogue a carne moída com alho e cebola. Adicione os tomates e deixe apurar. Misture tudo.', 480, 28.0, 72.0, 9.5, NULL),
('Sopa de Legumes com Frango', 'Sopa quente e nutritiva com legumes frescos', 'Frango, cenoura, batata, abobrinha, chuchu, tempero verde, sal', 'Cozinhe o frango e desfie. Reserve o caldo. Adicione os legumes cortados e cozinhe até ficarem macios.', 320, 22.0, 38.0, 5.0, NULL);

INSERT INTO novidades (titulo, mensagem, tipo, usuario_id) VALUES
('Bem-vindos ao sistema de merenda!', 'O cardápio desta semana está disponível. Qualquer dúvida, fale com o supervisor.', 'info', 1);


CREATE TABLE IF NOT EXISTS relatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('reclamacao','restricao','sugestao','elogio','outro') NOT NULL DEFAULT 'sugestao',
    titulo VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,
    anonimo TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pendente','lido','respondido') NOT NULL DEFAULT 'pendente',
    resposta TEXT,
    respondido_por INT,
    respondido_em TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (respondido_por) REFERENCES usuarios(id)
);
CREATE TABLE IF NOT EXISTS relatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('reclamacao','restricao','sugestao','elogio','outro') NOT NULL DEFAULT 'sugestao',
    titulo VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,
    anonimo TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pendente','lido','respondido') NOT NULL DEFAULT 'pendente',
    resposta TEXT,
    respondido_por INT,
    respondido_em TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (respondido_por) REFERENCES usuarios(id)
);