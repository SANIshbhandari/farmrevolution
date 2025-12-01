-- ============================================================================
-- FARMSAATHI OPTIMIZED DATABASE SCHEMA
-- Consolidated to 8 Essential Tables
-- ============================================================================

CREATE DATABASE IF NOT EXISTS farm_management;
USE farm_management;

-- ============================================================================
-- TABLE 1: USERS
-- Purpose: User authentication and role management
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager') DEFAULT 'manager',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE 2: CROPS (Includes: crop production + crop sales)
-- Purpose: Complete crop lifecycle from planting to sales
-- ============================================================================
CREATE TABLE IF NOT EXISTS crops (
    id INT PRIMARY KEY AUTO_INCREMENT,
    created_by INT NOT NULL,
    crop_name VARCHAR(100) NOT NULL,
    crop_type VARCHAR(50) NOT NULL,
    planting_date DATE NOT NULL,
    harvest_date DATE NULL,
    area_hectares DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'harvested', 'failed') DEFAULT 'active',
    
    -- Production data
    expected_yield DECIMAL(10,2) NULL,
    actual_yield DECIMAL(10,2) NULL,
    production_cost DECIMAL(10,2) DEFAULT 0,
    
    -- Sales data (JSON for multiple sales from same crop)
    sales JSON NULL COMMENT 'Array of {date, quantity, rate, total, buyer, contact, payment_method}',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_by (created_by),
    INDEX idx_status (status),
    INDEX idx_crop_type (crop_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE 3: LIVESTOCK (Includes: livestock production + livestock sales)
-- Purpose: Complete livestock management from purchase to sales
-- ============================================================================
CREATE TABLE IF NOT EXISTS livestock (
    id INT PRIMARY KEY AUTO_INCREMENT,
    created_by INT NOT NULL,
    animal_type VARCHAR(50) NOT NULL,
    breed VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    purchase_date DATE NOT NULL,
    purchase_cost DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'sold', 'deceased') DEFAULT 'active',
    
    -- Production records (JSON for ongoing production like milk, eggs)
    production JSON NULL COMMENT 'Array of {date, type, quantity, unit}',
    
    -- Sales data (JSON for multiple sales)
    sales JSON NULL COMMENT 'Array of {date, quantity, price, buyer, contact, payment_method}',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_by (created_by),
    INDEX idx_animal_type (animal_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE 4: INVENTORY (Includes: inventory items + transactions + equipment + employees)
-- Purpose: All farm resources and inventory management
-- ============================================================================
CREATE TABLE IF NOT EXISTS inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    created_by INT NOT NULL,
    item_type ENUM('supply', 'equipment', 'employee') NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    
    -- For supplies
    quantity DECIMAL(10,2) NULL,
    unit VARCHAR(20) NULL,
    reorder_level DECIMAL(10,2) NULL COMMENT 'Minimum quantity threshold for reordering supplies',
    
    -- For equipment
    purchase_date DATE NULL,
    maintenance_date DATE NULL,
    
    -- For employees
    phone VARCHAR(20) NULL,
    salary DECIMAL(10,2) NULL,
    hire_date DATE NULL,
    
    status ENUM('active', 'inactive', 'low_stock') DEFAULT 'active',
    
    -- Transaction history (JSON)
    transactions JSON NULL COMMENT 'Array of {date, type, quantity, amount, notes}',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_by (created_by),
    INDEX idx_item_type (item_type),
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE 5: FINANCE (Includes: income + expenses)
-- Purpose: All financial transactions
-- ============================================================================
CREATE TABLE IF NOT EXISTS finance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    created_by INT NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    category VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_date DATE NOT NULL,
    description TEXT,
    payment_method ENUM('cash', 'bank', 'other') DEFAULT 'cash',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_by (created_by),
    INDEX idx_type (type),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE 6: REPORTS
-- Purpose: Generated reports storage and metadata
-- ============================================================================
CREATE TABLE IF NOT EXISTS reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    created_by INT NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    report_name VARCHAR(100) NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    report_data JSON NOT NULL COMMENT 'Complete report data in JSON format',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_by (created_by),
    INDEX idx_report_type (report_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE 7: ACTIVITY_LOG
-- Purpose: Track all user activities and system events
-- ============================================================================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    action VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE 8: SETTINGS
-- Purpose: System and user preferences
-- ============================================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_setting (user_id, setting_key),
    INDEX idx_user_id (user_id),
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- SUMMARY
-- ============================================================================
-- Total Tables: 8
-- 
-- Table Structure:
-- 1. users - User authentication (removed: email, last_login)
-- 2. crops - Crops + production + sales combined
-- 3. livestock - Livestock + production + sales combined
-- 4. inventory - Supplies + equipment + employees + transactions combined
-- 5. finance - Income + expenses combined
-- 6. reports - Report generation and storage
-- 7. activity_log - System activity tracking
-- 8. settings - System and user preferences
-- 
-- Key Features:
-- - User isolation via created_by foreign keys (compatible with existing system)
-- - JSON columns for flexible repeating data
-- - Minimal essential columns only
-- - Comprehensive indexing for performance
-- - Compatible with existing MySQLi-based PHP code
-- - Activity log includes username field for existing logActivity() function
-- ============================================================================
