<?php
/**
 * MySQL Database Manager
 * Handles all database connections and queries
 */

class MySQL_DatabaseManager {
    private $conn_main = null;
    private $conn_support = null;
    private $last_error = '';
    
    /**
     * Get Main Database Connection (phcris)
     */
    public function getMainConnection() {
    if ($this->conn_main === null || !$this->conn_main->ping()) {
        try {
            mysqli_report(MYSQLI_REPORT_STRICT); // Throw exceptions on error
            $this->conn_main = new mysqli(
                DB_HOST,
                DB_USER_MAIN,
                DB_PASS_MAIN,
                DB_NAME_MAIN,
                DB_PORT
            );
            $this->conn_main->set_charset("utf8");
        } catch (mysqli_sql_exception $e) {
            $this->last_error = "Main DB Connection Error: " . $e->getMessage();
            $this->conn_main = null;
            return false; // Connection failed
        }
    }

    return $this->conn_main;
}

public function getSupportConnection() {
    if ($this->conn_support === null || !$this->conn_support->ping()) {
        try {
            mysqli_report(MYSQLI_REPORT_STRICT);
            $this->conn_support = new mysqli(
                DB_HOST,
                DB_USER_SUPPORT,
                DB_PASS_SUPPORT,
                DB_NAME_SUPPORT,
                DB_PORT_SUPPORT
            );

            if (!$this->conn_support->set_charset("utf8")) {
                $this->last_error = "Error loading character set utf8: " . $this->conn_support->error;
            }
        } catch (mysqli_sql_exception $e) {
            $this->last_error = "Support DB Connection Error: " . $e->getMessage();
            $this->conn_support = null;
            return false;
        }
    }

    return $this->conn_support;
}


    
    /**
     * Execute Query
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @param string $database 'main' or 'support'
     * @return mysqli_result|bool
     */
    public function executeQuery($sql, $params = [], $database = 'main') {
        $conn = ($database === 'support') ? $this->getSupportConnection() : $this->getMainConnection();
        
        if (!$conn) {
            return false;
        }
        
        // Prepare statement
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            $this->last_error = "Prepare failed: " . $conn->error;
            return false;
        }
        
        // Bind parameters if provided
        if (!empty($params)) {
            $types = '';
            $bind_params = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b'; // blob
                }
                $bind_params[] = $param;
            }
            
            $stmt->bind_param($types, ...$bind_params);
        }
        
        // Execute
        if (!$stmt->execute()) {
            $this->last_error = "Execute failed: " . $stmt->error;
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Fetch All Rows
     */
    public function fetchAll($sql, $params = [], $database = 'main') {
        $result = $this->executeQuery($sql, $params, $database);
        
        if (!$result) {
            return [];
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        return $rows;
    }
    
    /**
     * Fetch Single Row
     */
    public function fetchOne($sql, $params = [], $database = 'main') {
        $result = $this->executeQuery($sql, $params, $database);
        
        if (!$result) {
            return null;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Execute Non-Query (INSERT, UPDATE, DELETE)
     */
    public function executeNonQuery($sql, $params = [], $database = 'main') {
        $conn = ($database === 'support') ? $this->getSupportConnection() : $this->getMainConnection();
        
        if (!$conn) {
            return false;
        }
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            $this->last_error = "Prepare failed: " . $conn->error;
            return false;
        }
        
        if (!empty($params)) {
            $types = '';
            $bind_params = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
                $bind_params[] = $param;
            }
            
            $stmt->bind_param($types, ...$bind_params);
        }
        
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $success ? $affected_rows : false;
    }
    
    /**
     * Get Statistics for Dashboard
     */
    public function getDashboardStatistics() {
        $stats = [
            'birth' => ['registered' => 0, 'unregistered' => 0, 'total' => 0],
            'death' => ['registered' => 0, 'unregistered' => 0, 'total' => 0],
            'marriage' => ['registered' => 0, 'unregistered' => 0, 'total' => 0]
        ];
        
        // Birth statistics
        $sql = "SELECT 
                    COUNT(CASE WHEN RegistryNum NOT LIKE '!%' THEN 1 END) as registered,
                    COUNT(CASE WHEN RegistryNum LIKE '!%' THEN 1 END) as unregistered,
                    COUNT(*) as total
                FROM " . TABLE_BIRTH;
        
        $result = $this->fetchOne($sql);
        if ($result) {
            $stats['birth'] = $result;
        }
        
        // Death statistics
        $sql = "SELECT 
                    COUNT(CASE WHEN RegistryNum NOT LIKE '!%' THEN 1 END) as registered,
                    COUNT(CASE WHEN RegistryNum LIKE '!%' THEN 1 END) as unregistered,
                    COUNT(*) as total
                FROM " . TABLE_DEATH;
        
        $result = $this->fetchOne($sql);
        if ($result) {
            $stats['death'] = $result;
        }
        
        // Marriage statistics
        $sql = "SELECT 
                    COUNT(CASE WHEN RegistryNum NOT LIKE '!%' THEN 1 END) as registered,
                    COUNT(CASE WHEN RegistryNum LIKE '!%' THEN 1 END) as unregistered,
                    COUNT(*) as total
                FROM " . TABLE_MARRIAGE;
        
        $result = $this->fetchOne($sql);
        if ($result) {
            $stats['marriage'] = $result;
        }
        
        return $stats;
    }
    
    /**
     * Get Unregistered Birth Records
     */
    public function getUnregisteredBirthRecords($page = 1, $search = '') {
        $offset = ($page - 1) * RECORDS_PER_PAGE;
        
        $sql = "SELECT 
                    RegistryNum,
                    CFirstName,
                    CMiddleName,
                    CLastName,
                    CBirthDate,
                    CSexID,
                    CONCAT(MFirstName, ' ', COALESCE(MMiddleName, ''), ' ', MLastName) AS MotherName,
                    CONCAT(FFirstName, ' ', COALESCE(FMiddleName, ''), ' ', FLastName) AS FatherName
                FROM " . TABLE_BIRTH . "
                WHERE RegistryNum LIKE '!%'";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (
                        RegistryNum LIKE ? OR
                        CFirstName LIKE ? OR
                        CLastName LIKE ? OR
                        CONCAT(MFirstName, ' ', MLastName) LIKE ? OR
                        CONCAT(FFirstName, ' ', FLastName) LIKE ?
                    )";
            $searchParam = "%$search%";
            $params = array_fill(0, 5, $searchParam);
        }
        
        $sql .= " ORDER BY RegistryNum DESC LIMIT ? OFFSET ?";
        $params[] = RECORDS_PER_PAGE;
        $params[] = $offset;
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Get Total Unregistered Birth Records Count
     */
    public function getUnregisteredBirthCount($search = '') {
        $sql = "SELECT COUNT(*) as total FROM " . TABLE_BIRTH . " WHERE RegistryNum LIKE '!%'";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (
                        RegistryNum LIKE ? OR
                        CFirstName LIKE ? OR
                        CLastName LIKE ? OR
                        CONCAT(MFirstName, ' ', MLastName) LIKE ? OR
                        CONCAT(FFirstName, ' ', FLastName) LIKE ?
                    )";
            $searchParam = "%$search%";
            $params = array_fill(0, 5, $searchParam);
        }
        
        $result = $this->fetchOne($sql, $params);
        return $result ? $result['total'] : 0;
    }
    
    /**
     * Get Birth Record by Registry Number
     */
    public function getBirthRecord($registryNum) {
        $sql = "SELECT * FROM " . TABLE_BIRTH . " WHERE RegistryNum = ? LIMIT 1";
        return $this->fetchOne($sql, [$registryNum]);
    }
    
    /**
     * Log Registry Action
     */
    public function logRegistryAction($registryNum, $action, $docType, $contactNumber = '', $attachmentPath = '') {
        $attachmentData = null;
        $attachmentSize = 0;
        $attachmentType = 'No';
        
        if (!empty($attachmentPath) && file_exists($attachmentPath)) {
            $attachmentData = file_get_contents($attachmentPath);
            $attachmentSize = filesize($attachmentPath);
            $attachmentType = 'YES-DB';
        }
        
        $logTable = '';
        $historyTable = '';
        
        switch ($docType) {
            case 'Birth':
                $logTable = TABLE_BIRTH_LOG;
                $historyTable = TABLE_BIRTH_HISTORY;
                break;
            case 'Death':
                $logTable = TABLE_DEATH_LOG;
                $historyTable = TABLE_DEATH_HISTORY;
                break;
            case 'Marriage':
                $logTable = TABLE_MARRIAGE_LOG;
                $historyTable = TABLE_MARRIAGE_HISTORY;
                break;
        }
        
        // Check if record exists in log
        $checkSql = "SELECT COUNT(*) as count FROM $logTable WHERE RegistryNum = ?";
        $exists = $this->fetchOne($checkSql, [$registryNum], 'support');
        
        if ($exists && $exists['count'] > 0) {
            // Update existing
            $sql = "UPDATE $logTable SET 
                        Action = ?,
                        ContactNumber = ?,
                        Attachment = ?,
                        AttachmentSize = ?,
                        AttachmentType = ?,
                        ActionDate = NOW()
                    WHERE RegistryNum = ?";
            
            $params = [$action, $contactNumber, $attachmentData, $attachmentSize, $attachmentType, $registryNum];
        } else {
            // Insert new
            $sql = "INSERT INTO $logTable 
                        (RegistryNum, Action, ContactNumber, Attachment, AttachmentSize, AttachmentType, ActionDate)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [$registryNum, $action, $contactNumber, $attachmentData, $attachmentSize, $attachmentType];
        }
        
        $result = $this->executeNonQuery($sql, $params, 'support');
        
        // Insert history
        $historySql = "INSERT INTO $historyTable 
                        (RegistryNum, Details, Modified_By, Modified_Date, MobileNo)
                       VALUES (?, ?, ?, NOW(), ?)";
        
        $details = "Record " . ($exists && $exists['count'] > 0 ? "updated" : "inserted") . " - Action: $action";
        $modifiedBy = $_SESSION['username'] ?? 'System';
        
        $this->executeNonQuery($historySql, [$registryNum, $details, $modifiedBy, $contactNumber], 'support');
        
        return $result;
    }
    
    /**
     * Get Last Error
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    /**
     * Close Connections
     */
    public function close() {
        if ($this->conn_main) {
            $this->conn_main->close();
        }
        if ($this->conn_support) {
            $this->conn_support->close();
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }


  /**
 * Cleanup Orphan Records Row-by-Row (Safe)
 * Only deletes log/history records if they do not exist in main table
 *
 * @param string $docType Birth | Death | Marriage
 * @return array ['log_deleted' => int, 'history_deleted' => int]
 */
public function cleanupOrphans($docType): array {

    // Map tables
    switch ($docType) {
        case 'Birth':
            $mainTable    = TABLE_BIRTH;
            $logTable     = TABLE_BIRTH_LOG;
            $historyTable = TABLE_BIRTH_HISTORY;
            break;
        case 'Death':
            $mainTable    = TABLE_DEATH;
            $logTable     = TABLE_DEATH_LOG;
            $historyTable = TABLE_DEATH_HISTORY;
            break;
        case 'Marriage':
            $mainTable    = TABLE_MARRIAGE;
            $logTable     = TABLE_MARRIAGE_LOG;
            $historyTable = TABLE_MARRIAGE_HISTORY;
            break;
        default:
            $this->last_error = "Invalid document type: $docType";
            return ['log_deleted' => 0, 'history_deleted' => 0];
    }

    $deletedLog = 0;
    $deletedHistory = 0;

    try {
        // --- Process Log Table ---
        $logRecords = $this->fetchAll("SELECT RegistryNum FROM $logTable", [], 'support');
        foreach ($logRecords as $row) {
            $regNum = $row['RegistryNum']; // Keep as-is

            // Check if main table has this RegistryNum
            $exists = $this->fetchOne("SELECT 1 FROM $mainTable WHERE RegistryNum = ? LIMIT 1", [$regNum], 'main');

            if (!$exists) {
                // Safe to delete
                $this->executeNonQuery("DELETE FROM $logTable WHERE RegistryNum = ?", [$regNum], 'support');
                $deletedLog++;
            }
        }

        // --- Process History Table ---
        $historyRecords = $this->fetchAll("SELECT RegistryNum FROM $historyTable", [], 'support');
        foreach ($historyRecords as $row) {
            $regNum = $row['RegistryNum']; // Keep as-is

            $exists = $this->fetchOne("SELECT 1 FROM $mainTable WHERE RegistryNum = ? LIMIT 1", [$regNum], 'main');

            if (!$exists) {
                $this->executeNonQuery("DELETE FROM $historyTable WHERE RegistryNum = ?", [$regNum], 'support');
                $deletedHistory++;
            }
        }

    } catch (\Exception $e) {
        $this->last_error = "Cleanup failed: " . $e->getMessage();
        return ['log_deleted' => 0, 'history_deleted' => 0];
    }

    return [
        'log_deleted' => $deletedLog,
        'history_deleted' => $deletedHistory
    ];
}


}
?>
