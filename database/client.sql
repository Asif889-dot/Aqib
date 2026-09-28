CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_email (email)
);
CREATE TABLE IF NOT EXISTS `clients` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`           VARCHAR(150) NOT NULL,
    `father_name`    VARCHAR(150) NOT NULL,
    `iris_email`     VARCHAR(190) NOT NULL,
    `iris_password`  VARCHAR(255) NOT NULL,           -- store ENCRYPTED
    `cnic`           VARCHAR(15)  NOT NULL UNIQUE,    -- format: 12345-1234567-1
    `entry_year`     YEAR         NOT NULL,
    `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `whatsapp`       VARCHAR(20)  NOT NULL,
    `profile_pic`    VARCHAR(255) NULL,
    `cnic_front_pic` VARCHAR(255) NULL,
    `cnic_back_pic`  VARCHAR(255) NULL,
    `created_by`     INT UNSIGNED NULL,               -- FK to users.id
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_cnic`     (`cnic`),
    INDEX `idx_email`    (`iris_email`),
    INDEX `idx_status`   (`status`),
    INDEX `idx_year`     (`entry_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;