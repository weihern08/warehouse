-- Warehouse Management System
-- Database: synergy1_weihern_warehouse (cPanel shows as weihern_warehouse)
-- Compatible with MySQL 8+ / MariaDB / cPanel
--
-- IMPORTANT (cPanel / shared hosting):
-- Do NOT run CREATE DATABASE here — user synergy1_shaoxi cannot create databases.
-- 1. In phpMyAdmin, select database `synergy1_weihern_warehouse` first
-- 2. Then Import this file (or paste SQL into the SQL tab)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role (role),
    KEY idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    location VARCHAR(100) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL,
    reorder_level INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_items_code (item_code),
    KEY idx_items_name (item_name),
    KEY idx_items_category (category),
    KEY idx_items_location (location),
    CONSTRAINT chk_items_quantity CHECK (quantity >= 0),
    CONSTRAINT chk_items_reorder CHECK (reorder_level >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    movement_type ENUM('IN', 'OUT') NOT NULL,
    quantity INT NOT NULL,
    movement_date DATE NOT NULL,
    reference_note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_movements_item (item_id),
    KEY idx_movements_type (movement_type),
    KEY idx_movements_date (movement_date),
    KEY idx_movements_created_by (created_by),
    CONSTRAINT chk_movements_quantity CHECK (quantity > 0),
    CONSTRAINT fk_movements_item
        FOREIGN KEY (item_id) REFERENCES items (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_movements_user
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_username_time (username, attempted_at),
    KEY idx_login_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo accounts (development / testing only)
-- Admin: admin / admin123
-- User:  demo  / user123
-- Change these passwords before production use.
INSERT INTO users (full_name, username, password_hash, role, is_active) VALUES
(
    'System Administrator',
    'admin',
    '$2y$12$gsyF8763JZt6PpO7zFnhquINZCJf0iAVySeRm1QegAJz2Xg28lwYO',
    'admin',
    1
),
(
    'Demo User',
    'demo',
    '$2y$12$Ti4Kqrnjm0G/61UbamSUTu3Q7aHL83eKTlF1X0/PIZLj/nQ9hSOa6',
    'user',
    1
);

-- Demo inventory (quantities match opening stock movements)
INSERT INTO items (item_code, item_name, category, location, quantity, unit, reorder_level) VALUES
('ITM-001', 'A4 Copy Paper', 'Office Supplies', 'Shelf A1', 120, 'ream', 30),
('ITM-002', 'Ballpoint Pen Blue', 'Office Supplies', 'Shelf A2', 45, 'box', 20),
('ITM-003', 'Safety Helmet', 'PPE', 'Rack B1', 8, 'pcs', 10),
('ITM-004', 'Work Gloves', 'PPE', 'Rack B2', 25, 'pair', 15),
('ITM-005', 'Packing Tape', 'Packaging', 'Shelf C1', 60, 'roll', 20);

INSERT INTO stock_movements (item_id, movement_type, quantity, movement_date, reference_note, created_by) VALUES
(1, 'IN', 120, CURDATE(), 'Opening balance', 1),
(2, 'IN', 45, CURDATE(), 'Opening balance', 1),
(3, 'IN', 8, CURDATE(), 'Opening balance', 1),
(4, 'IN', 25, CURDATE(), 'Opening balance', 1),
(5, 'IN', 60, CURDATE(), 'Opening balance', 1);
