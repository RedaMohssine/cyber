-- =====================================================================
--  CaptusBank — Schéma version VULNÉRABLE
-- =====================================================================
CREATE DATABASE IF NOT EXISTS bank_vuln CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bank_vuln;

DROP TABLE IF EXISTS transfers;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(60)  NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    email        VARCHAR(150) NOT NULL,
    full_name    VARCHAR(150) NOT NULL,
    role         ENUM('user','admin','honeypot') DEFAULT 'user',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE accounts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    iban         VARCHAR(34) NOT NULL UNIQUE,
    balance      DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency     VARCHAR(3) DEFAULT 'EUR',
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- IMPORTANT : table sessions persistée pour la démonstration BDD
-- (utilisée par session_set_save_handler côté PHP)
CREATE TABLE sessions (
    sid          VARCHAR(128) PRIMARY KEY,
    user_id      INT NULL,
    ip_address   VARCHAR(45),
    user_agent   VARCHAR(500),
    payload      TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- XSS stocké : commentaires NON échappés dans la version vulnérable
CREATE TABLE comments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    content      TEXT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE transfers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    from_account INT NOT NULL,
    to_iban      VARCHAR(34) NOT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    note         VARCHAR(255),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_account) REFERENCES accounts(id)
) ENGINE=InnoDB;

-- Comptes (mots de passe en clair pour la démo - simule un autre défaut classique)
INSERT INTO users (username, password, email, full_name, role) VALUES
    ('alice',          'Password123!', 'alice@captus.example',  'Alice Martin',      'user'),
    ('bob',            'BobPass456!',  'bob@captus.example',    'Bob Durand',        'user'),
    ('mallory',        'EvilPass1!',   'mallory@evil.lab',      'Mallory (attacker)','user'),
    ('admin_honeypot', 'D0_n0t_use!',  'noreply@captus.example','HONEYPOT - DO NOT USE','honeypot');

INSERT INTO accounts (user_id, iban, balance) VALUES
    (1, 'FR7630001007941234567890185', 12450.00),
    (2, 'FR7630001007940987654321099', 3200.50),
    (3, 'FR7630001007940000000000042', 5.00),
    (4, 'FR7600000000000000000000000', 999999.99);

INSERT INTO comments (user_id, content) VALUES
    (1, 'Bienvenue sur CaptusBank !'),
    (2, 'Service très efficace, je recommande.');
