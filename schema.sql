-- Rode no banco criado na Hostinger (no deploy, isso foi feito por PHPs temporários)

-- Contas do painel. A senha é salva com password_hash() do PHP, então crie as contas por um script PHP, não por aqui
CREATE TABLE nutricionistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(40) NOT NULL UNIQUE,
    nome VARCHAR(80) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- nutricionista_id NULL = gestante que chegou pela calculadora do site, visível para todas as nutricionistas
CREATE TABLE pacientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    email VARCHAR(120) NULL UNIQUE,          -- conta da gestante (opcional)
    senha_hash VARCHAR(255) NULL,
    conta_token CHAR(32) NULL,               -- prova de que foi ela quem fez o cálculo; some ao criar a conta
    altura DECIMAL(5,1) NOT NULL,
    peso_pre DECIMAL(5,1) NOT NULL,
    peso_atual DECIMAL(5,1) NOT NULL,
    semana TINYINT UNSIGNED NOT NULL,
    nutricionista_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nutricionista_id) REFERENCES nutricionistas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE planos (
    paciente_id INT PRIMARY KEY,
    refeicoes TEXT NOT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pesagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    semana TINYINT UNSIGNED NOT NULL,
    peso DECIMAL(5,1) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    nutricionista_id INT NOT NULL,
    status ENUM('pedida', 'confirmada', 'recusada') NOT NULL DEFAULT 'pedida',
    data_hora DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    FOREIGN KEY (nutricionista_id) REFERENCES nutricionistas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clientes de exemplo para a apresentação (fictícias), 3 por nutricionista, depois de criar as contas 1 a 4
INSERT INTO pacientes (nome, altura, peso_pre, peso_atual, semana, nutricionista_id, criado_em) VALUES
('Fernanda L.', 165, 58, 64, 24, 1, NOW() - INTERVAL 6 DAY),
('Patrícia M.', 158, 74, 84, 26, 1, NOW() - INTERVAL 4 DAY),
('Camila S.', 160, 50, 51, 20, 1, NOW() - INTERVAL 2 DAY),
('Juliana R.', 168, 62, 70, 28, 2, NOW() - INTERVAL 7 DAY),
('Bruna A.', 155, 82, 86, 30, 2, NOW() - INTERVAL 5 DAY),
('Renata C.', 162, 55, 55.5, 18, 2, NOW() - INTERVAL 1 DAY),
('Aline P.', 170, 68, 72, 22, 3, NOW() - INTERVAL 8 DAY),
('Gabriela M.', 158, 60, 58.5, 10, 3, NOW() - INTERVAL 3 DAY),
('Larissa F.', 165, 77, 90, 32, 3, NOW() - INTERVAL 2 DAY),
('Priscila N.', 163, 48, 58, 30, 4, NOW() - INTERVAL 9 DAY),
('Daniela V.', 160, 66, 71, 25, 4, NOW() - INTERVAL 4 DAY),
('Tatiane O.', 157, 90, 103, 27, 4, NOW() - INTERVAL 1 DAY);
