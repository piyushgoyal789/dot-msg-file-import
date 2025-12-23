<?php
require_once 'config.php';

class MsgDatabase {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Save email metadata to database
     */
    public function saveEmail($emailData) {
        $sql = "INSERT INTO emails (
            msg_file_name, subject, email_from, email_to, email_cc, email_bcc,
            sent_date, received_date, body_text, body_html, total_attachments
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $emailData['msg_file_name'] ?? '',
            $emailData['subject'] ?? '',
            $emailData['from'] ?? '',
            $emailData['to'] ?? '',
            $emailData['cc'] ?? '',
            $emailData['bcc'] ?? '',
            $emailData['sent_date'] ?? null,
            $emailData['received_date'] ?? null,
            $emailData['body_text'] ?? '',
            $emailData['body_html'] ?? '',
            $emailData['total_attachments'] ?? 0
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Save passenger attachment data
     */
    public function savePassengerAttachment($passengerData) {
        $sql = "INSERT INTO passenger_attachments (
            passenger_name, email_subject, email_from, email_date,
            attachment_name, attachment_type, attachment_size,
            attachment_data, base64_preview
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $passengerData['passenger_name'],
            $passengerData['email_subject'] ?? '',
            $passengerData['email_from'] ?? '',
            $passengerData['email_date'] ?? null,
            $passengerData['attachment_name'] ?? '',
            $passengerData['attachment_type'] ?? '',
            $passengerData['attachment_size'] ?? 0,
            $passengerData['attachment_data'] ?? '',
            $passengerData['base64_preview'] ?? ''
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Link email with passenger attachment
     */
    public function linkEmailWithAttachment($emailId, $attachmentId) {
        $sql = "INSERT INTO email_passenger_attachments (email_id, passenger_attachment_id) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$emailId, $attachmentId]);
    }
    
    /**
     * Extract passenger name from attachment filename or content
     */
    public function extractPassengerName($filename, $content = '', $emailBodyText = '') {
        // First, try to extract from email body text (most reliable)
        if (!empty($emailBodyText)) {
            $patterns = [
                // Pattern for "Passenger : Keren Pnina Mrs (ADT)" format
                '/Passenger\s*:\s*([A-Za-z\s]+(?:Mrs?|Ms|Miss|Dr)\.?)\s*\([A-Z]+\)/i',
                // Pattern for "Passenger: Name" format
                '/Passenger\s*:\s*([A-Za-z\s]+(?:Mrs?|Ms|Miss|Dr)\.?)/i',
                // Pattern for "PAX: Name" format
                '/PAX\s*:\s*([A-Za-z\s]+(?:Mrs?|Ms|Miss|Dr)\.?)/i'
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $emailBodyText, $matches)) {
                    $name = trim($matches[1]);
                    // Clean up the name
                    $name = preg_replace('/\s+/', ' ', $name); // Remove multiple spaces
                    return $name;
                }
            }
        }
        
        // Second, try to extract from decoded PDF content if it's a PDF
        if (!empty($content)) {
            $decodedContent = base64_decode($content);
            if ($decodedContent) {
                // Look for common passenger name patterns in PDF content
                $patterns = [
                    // Pattern for "ROSENTAL MEIR MR" format
                    '/\b([A-Z]{2,}\s+[A-Z]{2,}\s+(?:MR|MRS|MS|MISS)\.?)\b/i',
                    // Pattern for "Passenger: LASTNAME/FIRSTNAME MR/MRS"
                    '/Passenger[:\s]*([A-Z]+(?:\/[A-Z]+)*\s+(?:MR|MRS|MS|MISS)\.?)/i',
                    // Pattern for "PAX: LASTNAME/FIRSTNAME"  
                    '/PAX[:\s]*([A-Z]+(?:\/[A-Z]+)*)/i',
                    // Pattern for "LASTNAME FIRSTNAME MR/MRS"
                    '/([A-Z]{2,}\s+[A-Z]{2,}\s+(?:MR|MRS|MS|MISS)\.?)/i',
                    // Pattern for "LASTNAME/FIRSTNAME"
                    '/([A-Z]{2,}\/[A-Z]{2,})/i',
                    // General pattern for passenger info
                    '/passenger[:\s]*([A-Z][a-z]+\s+[A-Z][a-z]+(?:\s+(?:Mr|Mrs|Ms|Miss)\.?)?)/i',
                    // Pattern for names like "ROSENTAL MEIR" without title
                    '/\b([A-Z]{3,}\s+[A-Z]{3,})\b/i'
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $decodedContent, $matches)) {
                        $name = trim($matches[1]);
                        // Clean up the name
                        $name = str_replace('/', ' ', $name); // Replace / with space
                        $name = preg_replace('/\s+/', ' ', $name); // Remove multiple spaces
                        
                        // Convert to proper case if all caps
                        if (strtoupper($name) === $name) {
                            $nameParts = explode(' ', $name);
                            $properName = [];
                            foreach ($nameParts as $part) {
                                if (in_array(strtoupper($part), ['MR', 'MRS', 'MS', 'MISS', 'DR'])) {
                                    $properName[] = ucfirst(strtolower($part));
                                } else {
                                    $properName[] = ucfirst(strtolower($part));
                                }
                            }
                            $name = implode(' ', $properName);
                        }
                        
                        return $name;
                    }
                }
                
                // Additional search for names in different formats
                // Look for patterns like "PNINA KEREN MRS" or "KEREN PNINA MRS"
                if (preg_match_all('/\b([A-Z]{2,})\s+([A-Z]{2,})\s+(MRS?|MS|MISS)\.?\b/i', $decodedContent, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $firstName = ucfirst(strtolower($match[1]));
                        $lastName = ucfirst(strtolower($match[2]));
                        $title = ucfirst(strtolower($match[3]));
                        return "$firstName $lastName $title";
                    }
                }
            }
        }
        
        // Try to extract passenger name from filename
        $patterns = [
            '/([A-Z][a-z]+\s+[A-Z][a-z]+)/', // First Last pattern
            '/passenger[_\s-]*([A-Z][a-z]+[_\s-]*[A-Z][a-z]+)/i', // passenger_FirstLast
            '/([A-Z]{2,}\s+[A-Z]{2,})/', // FIRST LAST pattern
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filename, $matches)) {
                return trim(str_replace(['_', '-'], ' ', $matches[1]));
            }
        }
        
        // Default fallback
        return 'Unknown Passenger';
    }
    
    /**
     * Process and save MSG file data
     */
    public function processMsgFile($msgFilePath, $msgData) {
        try {
            $this->db->beginTransaction();
            
            // Convert object to array for easier handling
            $msgArray = json_decode(json_encode($msgData), true);
            
            // Prepare email data - handle both object and array structures
            $emailData = [
                'msg_file_name' => basename($msgFilePath),
                'subject' => $this->extractValue($msgData, 'headers.Subject') ?? $this->extractValue($msgData, 'subject') ?? '',
                'from' => $this->extractValue($msgData, 'headers.From') ?? $this->extractValue($msgData, 'from') ?? '',
                'to' => $this->extractValue($msgData, 'headers.To') ?? $this->extractValue($msgData, 'to') ?? '',
                'cc' => $this->extractValue($msgData, 'headers.Cc') ?? $this->extractValue($msgData, 'cc') ?? '',
                'bcc' => $this->extractValue($msgData, 'headers.Bcc') ?? $this->extractValue($msgData, 'bcc') ?? '',
                'sent_date' => $this->validateAndConvertDate($this->extractValue($msgData, 'headers.Date') ?? $this->extractValue($msgData, 'date') ?? null),
                'received_date' => $this->validateAndConvertDate($this->extractValue($msgData, 'received') ?? null),
                'body_text' => $this->extractValue($msgData, 'body') ?? '',
                'body_html' => $this->extractValue($msgData, 'bodyHTML') ?? '',
                'total_attachments' => count($this->extractValue($msgData, 'attachments') ?? [])
            ];
            
            // Convert arrays to strings
            if (is_array($emailData['to'])) $emailData['to'] = implode(', ', $emailData['to']);
            if (is_array($emailData['cc'])) $emailData['cc'] = implode(', ', $emailData['cc']);
            if (is_array($emailData['bcc'])) $emailData['bcc'] = implode(', ', $emailData['bcc']);
            
            // Save email
            $emailId = $this->saveEmail($emailData);
            if (!$emailId) {
                throw new Exception("Failed to save email data");
            }
            
            // Process attachments
            $attachments = $this->extractValue($msgData, 'attachments') ?? [];
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    // Handle both object and array structures
                    $attachmentArray = is_object($attachment) ? json_decode(json_encode($attachment), true) : $attachment;
                    
                    // Extract passenger name
                    $filename = $attachmentArray['filename'] ?? $attachmentArray['name'] ?? 'Unknown';
                    $data = $attachmentArray['data'] ?? '';
                    
                    $passengerName = $this->extractPassengerName($filename, $data, $emailData['body_text']);
                    
                    // Prepare attachment data
                    $attachmentData = [
                        'passenger_name' => $passengerName,
                        'email_subject' => $emailData['subject'],
                        'email_from' => $emailData['from'],
                        'email_date' => $emailData['sent_date'],
                        'attachment_name' => $filename,
                        'attachment_type' => $attachmentArray['mimeType'] ?? $attachmentArray['type'] ?? '',
                        'attachment_size' => isset($data) ? strlen(base64_decode($data)) : 0,
                        'attachment_data' => $data,
                        'base64_preview' => substr($data, 0, 500) // First 500 chars for preview
                    ];
                    
                    // Save passenger attachment
                    $attachmentId = $this->savePassengerAttachment($attachmentData);
                    if ($attachmentId) {
                        // Link email with attachment
                        $this->linkEmailWithAttachment($emailId, $attachmentId);
                    }
                }
            }
            
            $this->db->commit();
            return $emailId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Helper function to extract values from object/array using dot notation
     */
    private function extractValue($data, $path) {
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (is_object($current)) {
                if (isset($current->$key)) {
                    $current = $current->$key;
                } else {
                    return null;
                }
            } elseif (is_array($current)) {
                if (isset($current[$key])) {
                    $current = $current[$key];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        
        return $current;
    }
    
    /**
     * Get passenger attachments by passenger name
     */
    public function getPassengerAttachments($passengerName = null) {
        $sql = "SELECT * FROM passenger_attachments";
        $params = [];
        
        if ($passengerName) {
            $sql .= " WHERE passenger_name LIKE ?";
            $params[] = "%{$passengerName}%";
        }
        
        $sql .= " ORDER BY email_date DESC, passenger_name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all unique passenger names
     */
    public function getPassengerNames() {
        $sql = "SELECT DISTINCT passenger_name FROM passenger_attachments ORDER BY passenger_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Update passenger name for a specific attachment
     */
    public function updatePassengerName($attachmentId, $newPassengerName) {
        $sql = "UPDATE passenger_attachments SET passenger_name = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$newPassengerName, $attachmentId]);
    }
    
    /**
     * Bulk update passenger names based on attachment name patterns
     */
    public function bulkUpdatePassengerNames($updates) {
        $this->db->beginTransaction();
        try {
            foreach ($updates as $pattern => $passengerName) {
                $sql = "UPDATE passenger_attachments SET passenger_name = ?, updated_at = NOW() 
                       WHERE attachment_name LIKE ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$passengerName, $pattern]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get attachment statistics
     */
    public function getAttachmentStats() {
        $sql = "SELECT 
                    COUNT(*) as total_attachments,
                    COUNT(DISTINCT passenger_name) as unique_passengers,
                    COUNT(DISTINCT email_subject) as unique_emails,
                    SUM(attachment_size) as total_size,
                    AVG(attachment_size) as avg_size
                FROM passenger_attachments";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Get attachment by ID for downloading
     */
    public function getAttachmentById($id) {
        $sql = "SELECT id, passenger_name, attachment_name, attachment_type, 
                       attachment_size, attachment_data
                FROM passenger_attachments 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Validate and convert date to MySQL-compatible format
     */
    private function validateAndConvertDate($dateString) {
        if (empty($dateString)) {
            return null;
        }
        
        try {
            // Try to parse the date string
            $timestamp = strtotime($dateString);
            if ($timestamp === false) {
                return null;
            }
            
            // Check if the year is reasonable (between 1970 and 2050)
            $year = date('Y', $timestamp);
            if ($year < 1970 || $year > 2050) {
                // If year is invalid, use current date
                return date('Y-m-d H:i:s');
            }
            
            // Convert to MySQL datetime format
            return date('Y-m-d H:i:s', $timestamp);
            
        } catch (Exception $e) {
            // If any error occurs, return current timestamp
            return date('Y-m-d H:i:s');
        }
    }
}
?>