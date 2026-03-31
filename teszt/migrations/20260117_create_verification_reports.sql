-- Migration: Create verification_reports table
-- Date: 2026-01-17

USE szivhang_db;

-- Remove old AI table if it exists
DROP TABLE IF EXISTS ai_verification_reports;

CREATE TABLE IF NOT EXISTS verification_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    risk_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
    justification TEXT,
    warnings TEXT,
    recommendation ENUM('approve', 'manual_check', 'reject') NOT NULL DEFAULT 'manual_check',
    checks_performed JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
