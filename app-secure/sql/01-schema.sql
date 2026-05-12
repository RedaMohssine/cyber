-- =====================================================================
--  CaptusBank — Schéma version SÉCURISÉE (jumelle, table dédiée)
-- =====================================================================
CREATE DATABASE IF NOT EXISTS bank_secure CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bank_secure;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS transfers;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    full_name     VARCHAR(150) NOT NULL,
    role          ENUM('user','admin','honeypot') DEFAULT 'user',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE accounts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    iban       VARCHAR(34) NOT NULL UNIQUE,
    balance    DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency   VARCHAR(3) DEFAULT 'EUR',
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE sessions (
    sid           VARCHAR(128) PRIMARY KEY,
    user_id       INT NULL,
    ip_address    VARCHAR(45),
    ua_hash       CHAR(64),
    payload       TEXT,
    fingerprint   CHAR(64),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_rotated  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    content    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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

CREATE TABLE audit_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    event       VARCHAR(64) NOT NULL,
    severity    ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    user_id     INT NULL,
    sid         VARCHAR(128) NULL,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(500),
    detail      JSON,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (event, severity), INDEX (created_at)
) ENGINE=InnoDB;

-- Mots de passe hashés avec password_hash (PASSWORD_BCRYPT, cost 12) :
-- alice  : Password123!
-- bob    : BobPass456!
-- mallory: EvilPass1!
INSERT INTO users (username, password_hash, email, full_name, role) VALUES
    ('alice',          '$2y$12$0bnv5OXOJxJ7HJ95uTaXVOZ5p8m8DjL5BApDU0OkmYU9NPGYU8XEy', 'alice@captus.example',  'Alice Martin',       'user'),
    ('bob',            '$2y$12$Jpf8.6kCNFTNb0PXZeRsmO/HwqVCQ4tDdNCFqj9JRSGUZN2hVxRXi', 'bob@captus.example',    'Bob Durand',         'user'),
    ('mallory',        '$2y$12$3qY9SXp76rOZHsZJaJ9DseQqlz5JxX4n6gWqHQzg0vP1NgQlT3.AS', 'mallory@evil.lab',      'Mallory (attacker)', 'user'),
    ('admin_honeypot', '$2y$12$E1G7iGzKZQrnD7v4dQ0F.OmYU8X.JEy4Mq.QEN.cYQYIz2tHKKv9q', 'noreply@captus.example','HONEYPOT - DO NOT USE','honeypot');

INSERT INTO accounts (user_id, iban, balance) VALUES
    (1, 'FR7630001007941234567890185', 12450.00),
    (2, 'FR7630001007940987654321099', 3200.50),
    (3, 'FR7630001007940000000000042', 5.00),
    (4, 'FR7600000000000000000000000', 999999.99);

-- Grant bankuser access to bank_secure (docker-compose's MYSQL_USER only auto-grants on MYSQL_DATABASE = bank_vuln)
GRANT ALL PRIVILEGES ON bank_secure.* TO 'bankuser'@'%';
FLUSH PRIVILEGES;
