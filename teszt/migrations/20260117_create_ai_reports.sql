-- Migration: Create ai_verification_reports table
-- Date: 2026-01-17

USE szivhang_db;

CREATE TABLE IF NOT EXISTS ai_verification_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    risk_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
    justification TEXT,
    warnings TEXT,
    recommendation ENUM('approve', 'manual_check', 'reject') NOT NULL DEFAULT 'manual_check',
    image_status ENUM('original', 'suspicious', 'not_original') DEFAULT 'original',
    image_observations TEXT,
    raw_response JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
