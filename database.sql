-- Create database (run this first if database doesn't exist)
-- CREATE DATABASE msg;

-- Use the msg database
USE msg;

-- Create table for storing passenger attachment data
CREATE TABLE IF NOT EXISTS passenger_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    passenger_name VARCHAR(255) NOT NULL,
    email_subject VARCHAR(500),
    email_from VARCHAR(255),
    email_date DATETIME,
    attachment_name VARCHAR(255),
    attachment_type VARCHAR(100),
    attachment_size INT,
    attachment_data LONGTEXT,
    base64_preview TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_passenger_name (passenger_name),
    INDEX idx_email_date (email_date)
);

-- Optional: Create a separate table for email metadata to avoid duplication
CREATE TABLE IF NOT EXISTS emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    msg_file_name VARCHAR(255),
    subject VARCHAR(500),
    email_from VARCHAR(255),
    email_to TEXT,
    email_cc TEXT,
    email_bcc TEXT,
    sent_date DATETIME,
    received_date DATETIME,
    body_text LONGTEXT,
    body_html LONGTEXT,
    total_attachments INT DEFAULT 0,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subject (subject),
    INDEX idx_from (email_from),
    INDEX idx_sent_date (sent_date)
);

-- Table to link emails with passenger attachments
CREATE TABLE IF NOT EXISTS email_passenger_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_id INT,
    passenger_attachment_id INT,
    FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE,
    FOREIGN KEY (passenger_attachment_id) REFERENCES passenger_attachments(id) ON DELETE CASCADE
);