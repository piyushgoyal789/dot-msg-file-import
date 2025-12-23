<?php
// Create database and tables script
require_once 'config.php';

try {
    // Connect to MySQL server without specifying database first
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '" . DB_NAME . "' created successfully or already exists.\n";
    
    // Now connect to the specific database
    $pdo->exec("USE " . DB_NAME);
    
    // Create passenger_attachments table
    $sql = "CREATE TABLE IF NOT EXISTS passenger_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        passenger_name VARCHAR(255) NOT NULL,
        email_subject VARCHAR(500),
        email_from VARCHAR(255),
        email_date DATETIME,
        attachment_name VARCHAR(255),
        attachment_type VARCHAR(100),
        attachment_size INT DEFAULT 0,
        attachment_data LONGTEXT,
        base64_preview TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_passenger_name (passenger_name),
        INDEX idx_email_date (email_date)
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "Table 'passenger_attachments' created successfully.\n";
    
    // Create emails table
    $sql = "CREATE TABLE IF NOT EXISTS emails (
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
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "Table 'emails' created successfully.\n";
    
    // Create linking table
    $sql = "CREATE TABLE IF NOT EXISTS email_passenger_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email_id INT,
        passenger_attachment_id INT,
        FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE,
        FOREIGN KEY (passenger_attachment_id) REFERENCES passenger_attachments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "Table 'email_passenger_attachments' created successfully.\n";
    
    echo "\nDatabase setup completed successfully!\n";
    echo "You can now run the MSG parser to save data to the database.\n";
    
} catch (PDOException $e) {
    echo "Database setup failed: " . $e->getMessage() . "\n";
    echo "Make sure XAMPP MySQL is running and the connection settings are correct.\n";
}
?>