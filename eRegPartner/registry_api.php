<?php
/**
 * Registry API - WITH PROGRESS TRACKING
 * Ultra-clean - no output before JSON
 * Progress updates for LGU, Log, History
 */
require_once __DIR__ . '/classes/MySQL_DatabaseManager.php';
require_once __DIR__ . '/classes/SecurityHelper.php';
// Absolutely NO output before this
error_reporting(0);
ini_set('display_errors', 0);
@ini_set('output_buffering', 'Off');
@ini_set('zlib.output_compression', 0);

// Capture everything
ob_start();

require_once 'config/config.php';
require_once 'classes/MySQL_DatabaseManager.php';
require_once 'classes/API_Controller.php';
require_once 'classes/SecurityHelper.php';

// Discard any output from includes
ob_end_clean();

// For progress tracking (SSE - Server-Sent Events)
if (isset($_GET['action']) && $_GET['action'] === 'send_record_progress') {
    handleProgressSend();
    exit;
}

// Start fresh buffer for JSON
ob_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

SecurityHelper::requireLogin();

$db = new MySQL_DatabaseManager();
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if ($method === 'POST') {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::validateCSRFToken($csrfToken)) {
            throw new Exception('Invalid CSRF token');
        }
    }
    
    switch ($action) {
        case 'list_records':
            $response = listRecords($db);
            break;
            
        case 'get_record':
            $response = getRecord($db);
            break;
            
        case 'send_record':
            $response = sendRecord($db);
            break;
            
        case 'get_stats':
            $response = getStats($db);
            break;
        case 'list_registered_records':   // ✅ new action
            $response = listRegisteredRecords($db);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    if (class_exists('SecurityHelper')) {
        @SecurityHelper::auditLog('API_ERROR', $e->getMessage());
    }
}

// Clean and output ONLY JSON
ob_end_clean();
echo json_encode($response);
exit;

function handleProgressSend() {
    // SSE Headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    
    // Disable buffering
    @ob_end_flush();
    @flush();
    
    // This will be implemented in the viewer JavaScript
    // For now, just acknowledge
    echo "data: " . json_encode(['status' => 'ready']) . "\n\n";
    flush();
}

function sendProgress($step, $progress, $message = '') {
    // Helper to send progress updates
    $data = [
        'step' => $step,
        'progress' => $progress,
        'message' => $message
    ];
    
    // We'll use this in the future for real-time progress
    // For now, it's a placeholder
}

function listRecords($db) {
    $type = $_GET['type'] ?? 'birth';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 50);
    $search = $_GET['search'] ?? '';
    
    if (!in_array($type, ['birth', 'death', 'marriage'])) {
        throw new Exception('Invalid record type');
    }
    
    $tables = getTableNames($type);
    
$db->cleanupOrphans(ucfirst($type)); // <--- add this


    $whereClauses = ["RegistryNum LIKE '!%'"];
    $params = [];
    
    if (!empty($search)) {
        switch ($type) {
            case 'birth':
            case 'death':
                $whereClauses[] = "(RegistryNum LIKE ? OR CFirstName LIKE ? OR CLastName LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                break;
                
            case 'marriage':
                $whereClauses[] = "(RegistryNum LIKE ? OR HFirstName LIKE ? OR HLastName LIKE ? OR WFirstName LIKE ? OR WLastName LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                break;
        }
    }
    
    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
    
    $countSQL = "SELECT COUNT(*) as total FROM {$tables['main']} $whereSQL";
    $countResult = $db->fetchOne($countSQL, $params);
    $total = (int)($countResult['total'] ?? 0);
    
    $selectColumns = getSelectColumns($type);
    
    $sql = "SELECT $selectColumns FROM {$tables['main']} $whereSQL ORDER BY RegistryNum DESC";
    $records = $db->fetchAll($sql, $params);
    
    if ($records === false || $records === null) {
        @SecurityHelper::auditLog('LIST_QUERY_FAILED', "Type: $type, Error: " . $db->getLastError());
        $records = [];
    }
    
    if (empty($records)) {
        return [
            'success' => true,
            'data' => [
                'records' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]
        ];
    }
    
    $registryNums = array_map(function($r) { return trim($r['RegistryNum']); }, $records);
    $placeholders = implode(',', array_map(function($r) { return "'" . addslashes($r) . "'"; }, $registryNums));
    
    $logData = [];
    if (!empty($placeholders)) {
        try {
            $logSQL = "SELECT TRIM(RegistryNum) as RegistryNum, ActionDate, AttachmentType 
                       FROM {$tables['log']} 
                       WHERE TRIM(RegistryNum) IN ($placeholders)";
            $logResults = $db->fetchAll($logSQL, [], 'support');
            
            if ($logResults) {
                foreach ($logResults as $log) {
                    $logData[trim($log['RegistryNum'])] = [
                        'ActionDate' => $log['ActionDate'],
                        'AttachmentType' => $log['AttachmentType']
                    ];
                }
            }
        } catch (Exception $e) {
            // Ignore
        }
    }
    
    $historyData = [];
    if (!empty($placeholders)) {
        try {
            $historySQL = "SELECT TRIM(RegistryNum) as RegistryNum, COUNT(*) as count 
                           FROM {$tables['history']} 
                           WHERE TRIM(RegistryNum) IN ($placeholders)
                           GROUP BY TRIM(RegistryNum)";
            $historyResults = $db->fetchAll($historySQL, [], 'support');
            
            if ($historyResults) {
                foreach ($historyResults as $hist) {
                    $historyData[trim($hist['RegistryNum'])] = (int)$hist['count'];
                }
            }
        } catch (Exception $e) {
            // Ignore
        }
    }
    
    $merged = [];
    foreach ($records as $record) {
        $regNum = trim($record['RegistryNum']);
        $log = $logData[$regNum] ?? null;
        $sentCount = $historyData[$regNum] ?? 0;
        
        $merged[] = [
            'record' => $record,
            'log' => $log,
            'sent_count' => $sentCount,
            'date_sent' => $log['ActionDate'] ?? null,
            'has_attachment' => !empty($log['AttachmentType']) && $log['AttachmentType'] !== 'NO',
            'sort_date' => $log['ActionDate'] ?? '0000-00-00 00:00:00',
            'sort_registry' => $regNum
        ];
    }
    
    usort($merged, function($a, $b) {
        $aHasDate = ($a['sort_date'] !== '0000-00-00 00:00:00');
        $bHasDate = ($b['sort_date'] !== '0000-00-00 00:00:00');
        
        if ($aHasDate && !$bHasDate) return -1;
        if (!$aHasDate && $bHasDate) return 1;
        
        if ($aHasDate && $bHasDate) {
            $dateCompare = strcmp($b['sort_date'], $a['sort_date']);
            if ($dateCompare !== 0) return $dateCompare;
        }
        
        return strcmp($b['sort_registry'], $a['sort_registry']);
    });
    
    $offset = ($page - 1) * $perPage;
    $paginatedMerged = array_slice($merged, $offset, $perPage);
    
    $formatted = [];
    foreach ($paginatedMerged as $item) {
        $formatted[] = formatRecord($item['record'], $item['log'], $item['sent_count'], $type);
    }
    
    @SecurityHelper::auditLog('LIST_RECORDS', "Type: $type, Page: $page, Results: " . count($formatted));
    
    return [
        'success' => true,
        'data' => [
            'records' => $formatted,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ]
    ];
}

function getSelectColumns($type) {
    switch ($type) {
        case 'birth':
            return 'RegistryNum, CFirstName, CMiddleName, CLastName, CSexId, CBirthDate, ' .
                   'MFirstName, MMiddleName, MLastName, ' .
                   'FFirstName, FMiddleName, FLastName';
            
        case 'death':
            return 'RegistryNum, CFirstName, CMiddleName, CLastName, CSexId, CBirthDate, CDeathDate, ' .
                   'MFirstName, MMiddleName, MLastName, ' .
                   'FFirstName, FMiddleName, FLastName';
            
        case 'marriage':
            return 'RegistryNum, ' .
                   'HFirstName, HMiddleName, HLastName, HBirthDate, HAge, ' .
                   'WFirstName, WMiddleName, WLastName, WBirthDate, WAge, ' .
                   'MarriageDate, MarriagePlaceMunicipality';
            
        default:
            return 'RegistryNum';
    }
}

function formatRecord($record, $log, $sentCount, $type) {
    $formatted = [
        'registry_num' => $record['RegistryNum'],
        'sent_count' => $sentCount,
        'date_sent' => $log['ActionDate'] ?? null,
        'has_attachment' => !empty($log['AttachmentType']) && $log['AttachmentType'] !== 'NO'
    ];
    
    switch ($type) {
        case 'birth':
            $formatted['first_name'] = $record['CFirstName'] ?? '';
            $formatted['middle_name'] = $record['CMiddleName'] ?? '';
            $formatted['last_name'] = $record['CLastName'] ?? '';
            $formatted['sex'] = $record['CSexId'] ?? '';
            $formatted['birth_date'] = $record['CBirthDate'] ?? '';
            $formatted['mother_name'] = formatName(
                $record['MFirstName'] ?? '', 
                $record['MMiddleName'] ?? '', 
                $record['MLastName'] ?? ''
            );
            $formatted['father_name'] = formatName(
                $record['FFirstName'] ?? '', 
                $record['FMiddleName'] ?? '', 
                $record['FLastName'] ?? ''
            );
            break;
            
        case 'death':
            $formatted['first_name'] = $record['CFirstName'] ?? '';
            $formatted['middle_name'] = $record['CMiddleName'] ?? '';
            $formatted['last_name'] = $record['CLastName'] ?? '';
            $formatted['sex'] = $record['CSexId'] ?? '';
            $formatted['birth_date'] = $record['CBirthDate'] ?? '';
            $formatted['date_of_death'] = $record['CDeathDate'] ?? '';
            $formatted['mother_name'] = formatName(
                $record['MFirstName'] ?? '', 
                $record['MMiddleName'] ?? '', 
                $record['MLastName'] ?? ''
            );
            $formatted['father_name'] = formatName(
                $record['FFirstName'] ?? '', 
                $record['FMiddleName'] ?? '', 
                $record['FLastName'] ?? ''
            );
            break;
            
        case 'marriage':
            $formatted['first_name'] = $record['HFirstName'] ?? '';
            $formatted['middle_name'] = $record['HMiddleName'] ?? '';
            $formatted['last_name'] = $record['HLastName'] ?? '';
            $formatted['birth_date'] = $record['HBirthDate'] ?? '';
            $formatted['h_age'] = $record['HAge'] ?? '';
            
            $formatted['wfirst_name'] = $record['WFirstName'] ?? '';
            $formatted['wmiddle_name'] = $record['WMiddleName'] ?? '';
            $formatted['wlast_name'] = $record['WLastName'] ?? '';
            $formatted['wbirth_date'] = $record['WBirthDate'] ?? '';
            $formatted['w_age'] = $record['WAge'] ?? '';
            $formatted['date_of_marriage'] = $record['MarriageDate'] ?? '';
            $formatted['place_of_marriage'] = $record['MarriagePlaceMunicipality'] ?? '';

            $formatted['Hmother_name'] = formatName(
                $record['HMFirstName'] ?? '', 
                $record['HMMiddleName'] ?? '', 
                $record['HMLastName'] ?? ''
            );
            $formatted['Hfather_name'] = formatName(
                $record['HFFirstName'] ?? '', 
                $record['HFMiddleName'] ?? '', 
                $record['HFLastName'] ?? ''
            );

            $formatted['Wmother_name'] = formatName(
                $record['WMFirstName'] ?? '', 
                $record['WMMiddleName'] ?? '', 
                $record['WMLastName'] ?? ''
            );
            $formatted['Wfather_name'] = formatName(
                $record['WFFirstName'] ?? '', 
                $record['WFMiddleName'] ?? '', 
                $record['WFLastName'] ?? ''
            );


            break;
    }
    
    return $formatted;
}

function getRecord($db) {
    $registryNum = $_GET['registry_num'] ?? '';
    $type = $_GET['type'] ?? 'birth';
    
    if (!SecurityHelper::validateRegistryNum($registryNum)) {
        throw new Exception('Invalid registry number');
    }
    
    $tables = getTableNames($type);
        $db->cleanupOrphans(ucfirst($type));

    // Get only the columns we need
    $selectCols = getSelectColumns($type);
    $sql = "SELECT $selectCols FROM {$tables['main']} WHERE RegistryNum = ? LIMIT 1";
    $record = $db->fetchOne($sql, [$registryNum]);
    
    if (!$record) {
        throw new Exception('Record not found');
    }
    
    // Add additional columns if they exist
    try {
        $allSql = "SELECT * FROM {$tables['main']} WHERE RegistryNum = ? LIMIT 1";
        $allData = $db->fetchOne($allSql, [$registryNum]);
        if ($allData) {
            $record = array_merge($record, $allData);
        }
    } catch (Exception $e) {
        // Ignore - use basic record
    }
    
    // Set defaults FIRST to ensure they always exist
    $record['sent_count'] = 0;
    $record['log'] = null;
    
    // Try to get log data (EXCLUDE Attachment BLOB to prevent JSON errors)
    try {
        $logSQL = "SELECT RegistryNum, Action, Performed_By, ActionDate, ContactNumber, 
                          AttachmentSize, AttachmentType 
                   FROM {$tables['log']} 
                   WHERE TRIM(RegistryNum) = ? LIMIT 1";
        $log = @$db->fetchOne($logSQL, [trim($registryNum)], 'support');
        
        // Only set if we got valid data
        if ($log !== false && $log !== null) {
            $record['log'] = $log;
        }
    } catch (Exception $e) {
        // Silently ignore - use default null
    }
    
    // Try to get history count
    try {
        $historySQL = "SELECT COUNT(*) as count FROM {$tables['history']} WHERE TRIM(RegistryNum) = ?";
        $historyResult = @$db->fetchOne($historySQL, [trim($registryNum)], 'support');
        
        // Only set if we got valid data
        if ($historyResult !== false && $historyResult !== null) {
            $record['sent_count'] = (int)($historyResult['count'] ?? 0);
        }
    } catch (Exception $e) {
        // Silently ignore - use default 0
    }
    
    @SecurityHelper::auditLog('VIEW_RECORD', "Type: $type", $registryNum);
    
    return [
        'success' => true,
        'data' => $record
    ];
}

function sendRecord($db) {
    if (!SecurityHelper::checkRateLimit('SEND_RECORD', 10, 60)) {
        throw new Exception('Too many send attempts. Please wait a moment.');
    }
    
    $registryNum = $_POST['registry_num'] ?? '';
    $type = $_POST['type'] ?? 'birth';
    $action = $_POST['send_action'] ?? 'SEND';
    $mobileNumber = $_POST['mobile_number'] ?? '';
    $sendDate = $_POST['send_date'] ?? date('Y-m-d');
    $remarks = $_POST['remarks'] ?? '';
    
    if (!SecurityHelper::validateRegistryNum($registryNum)) {
        throw new Exception('Invalid registry number');
    }
    
    if (empty($mobileNumber)) {
        throw new Exception('Mobile number is required');
    }
    
    $tables = getTableNames($type);
    
    $sql = "SELECT * FROM {$tables['main']} WHERE RegistryNum = ? LIMIT 1";
    $record = $db->fetchOne($sql, [$registryNum]);
    
    if (!$record) {
        throw new Exception('Record not found');
    }
    
    $pdfData = null;
    $pdfSize = 0;
    $attachmentType = 'NO';
    
    // Handle file upload VERY carefully to avoid any output
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $pdfSize = $_FILES['pdf_file']['size'];
        $pdfData = @file_get_contents($_FILES['pdf_file']['tmp_name']);
        
        if ($pdfData && strlen($pdfData) >= 4 && 
            ord($pdfData[0]) == 0x25 && ord($pdfData[1]) == 0x50 && 
            ord($pdfData[2]) == 0x44 && ord($pdfData[3]) == 0x46) {
            $attachmentType = $pdfSize < 50 * 1024 * 1024 ? 'YES-DB' : 'YES-File';
        } else {
            throw new Exception('Invalid PDF file');
        }
    }
    
    @SecurityHelper::auditLog($action . '_START', "Type: $type, Mobile: $mobileNumber", $registryNum);
    
    $api = new API_Controller();
    $sqlServerSuccess = false;
    
    try {
        $parameters = $record;
        $parameters['MobileNumber'] = $mobileNumber;
        $parameters['DateSend'] = $sendDate;
        $parameters['RemarksSend'] = $remarks;
        
        $sqlServerTable = getSQLServerTable($type);
        
        if ($pdfData && $pdfSize > 512 * 1024) {
            $sqlServerSuccess = @uploadToSQLServerChunked($api, $sqlServerTable, $parameters, $pdfData, $pdfSize);
        } else {
            $parameters['AttachFile'] = $pdfData ? base64_encode($pdfData) : null;
            $sqlServerSuccess = @uploadToSQLServer($api, $sqlServerTable, $parameters);
        }
        
    } catch (Exception $e) {
        @SecurityHelper::auditLog($action . '_FAILED', "SQL Server error: " . $e->getMessage(), $registryNum);
        throw new Exception('Failed to upload to SQL Server: ' . $e->getMessage());
    }
    
    if (!$sqlServerSuccess) {
        throw new Exception('SQL Server upload failed');
    }
    
    $checkLogSql = "SELECT COUNT(*) as count FROM {$tables['log']} WHERE TRIM(RegistryNum) = ?";
    $logCheck = $db->fetchOne($checkLogSql, [trim($registryNum)], 'support');
    $logExists = (int)($logCheck['count'] ?? 0) > 0;
    
    if ($logExists) {
        $updateLogSql = "UPDATE {$tables['log']} 
                         SET Action = ?, ContactNumber = ?, AttachmentSize = ?, 
                             AttachmentType = ?, ActionDate = NOW()
                         WHERE TRIM(RegistryNum) = ?";
        @$db->executeNonQuery($updateLogSql, [
            $action, $mobileNumber, $pdfSize, $attachmentType, trim($registryNum)
        ], 'support');
    } else {
        $insertLogSql = "INSERT INTO {$tables['log']} 
                         (RegistryNum, Action, Performed_By, ActionDate, ContactNumber, 
                          AttachmentSize, AttachmentType)
                         VALUES (?, ?, ?, NOW(), ?, ?, ?)";
        @$db->executeNonQuery($insertLogSql, [
            $registryNum, $action, $_SESSION['username'], $mobileNumber, $pdfSize, $attachmentType
        ], 'support');
    }
    
    if ($attachmentType === 'YES-DB' && $pdfData) {
        @uploadBLOBToMySQL($db, $tables['log'], $registryNum, $pdfData);
    }
    
    $historyDetails = "Action: $action | Mobile: $mobileNumber | User: " . $_SESSION['username'];
    if (!empty($remarks)) {
        $historyDetails .= " | Remarks: $remarks";
    }
    
    $historySql = "INSERT INTO {$tables['history']} 
                   (RegistryNum, Details, Modified_By, MobileNo)
                   VALUES (?, ?, ?, ?)";
    
    @$db->executeNonQuery($historySql, [
        $registryNum, 
        $historyDetails, 
        $_SESSION['username'], 
        $mobileNumber
    ], 'support');
    
    @SecurityHelper::auditLog($action . '_SUCCESS', "Type: $type, Mobile: $mobileNumber", $registryNum);
    
    return [
        'success' => true,
        'message' => ucfirst(strtolower($action)) . ' completed successfully!',
        'progress' => [
            'lgu' => 100,
            'log' => 100,
            'history' => 100
        ]
    ];
}

function uploadBLOBToMySQL($db, $tableName, $registryNum, $blobData) {
    $chunkSize = 256 * 1024;
    $totalSize = strlen($blobData);
    $offset = 0;
    $chunkNumber = 0;
    
    while ($offset < $totalSize) {
        $chunk = substr($blobData, $offset, $chunkSize);
        $hexChunk = bin2hex($chunk);
        
        if ($chunkNumber === 0) {
            $sql = "UPDATE $tableName SET Attachment = 0x{$hexChunk} WHERE TRIM(RegistryNum) = ?";
        } else {
            $sql = "UPDATE $tableName SET Attachment = CONCAT(COALESCE(Attachment, ''), 0x{$hexChunk}) WHERE TRIM(RegistryNum) = ?";
        }
        
        @$db->executeNonQuery($sql, [trim($registryNum)], 'support');
        
        $offset += $chunkSize;
        $chunkNumber++;
        unset($chunk, $hexChunk);
    }
}

function getStats($db) {
    $type = $_GET['type'] ?? 'birth';
    $tables = getTableNames($type);
    
    $totalSQL = "SELECT COUNT(*) as total FROM {$tables['main']} WHERE RegistryNum LIKE '!%'";
    $totalResult = $db->fetchOne($totalSQL);
    $total = (int)($totalResult['total'] ?? 0);
    
    $sentSQL = "SELECT COUNT(DISTINCT TRIM(RegistryNum)) as sent FROM {$tables['history']} WHERE RegistryNum LIKE '!%'";
    $sentResult = $db->fetchOne($sentSQL, [], 'support');
    $sent = (int)($sentResult['sent'] ?? 0);
    
    return [
        'success' => true,
        'data' => [
            'total' => $total,
            'sent' => $sent,
            'unsent' => $total - $sent
        ]
    ];
}

function getTableNames($type) {
    $tables = [
        'birth' => [
            'main' => 'birthdocument',
            'log' => 'registry_birth_log',
            'history' => 'registry_birth_history'
        ],
        'death' => [
            'main' => 'deathdocument',
            'log' => 'registry_death_log',
            'history' => 'registry_death_history'
        ],
        'marriage' => [
            'main' => 'marriagedocument',
            'log' => 'registry_marriage_log',
            'history' => 'registry_marriage_history'
        ]
    ];
    
    return $tables[$type] ?? $tables['birth'];
}

function getSQLServerTable($type) {
    return [
        'birth' => 'online_birthdocument',
        'death' => 'online_deathdocument',
        'marriage' => 'online_marriagedocument'
    ][$type] ?? 'online_birthdocument';
}

function formatName($first, $middle, $last) {
    $parts = array_filter([$first, $middle, $last]);
    return implode(' ', $parts);
}

function uploadToSQLServer($api, $table, $parameters) {
    $checkSql = "SELECT COUNT(*) AS Cnt FROM $table WHERE RegistryNum = @RegistryNum";
    $checkParams = ['RegistryNum' => $parameters['RegistryNum']];
    $checkResult = @$api->executeSQL($checkSql, 'LCRDbase_Online', 'SELECT', $checkParams);
    
    $exists = false;
    if ($checkResult && count($checkResult) > 0) {
        $exists = (int)$checkResult[0]['Cnt'] > 0;
    }
    
    if ($exists) {
        $sql = buildUpdateSql($table, $parameters);
    } else {
        $sql = buildInsertSql($table, $parameters);
    }
    
    $result = @$api->executeSQL($sql, 'LCRDbase_Online', $exists ? 'UPDATE' : 'INSERT', $parameters);
    return $result !== false;
}

function uploadToSQLServerChunked($api, $table, $parameters, $pdfData, $pdfSize) {
    $chunkSize = 512 * 1024;
    $totalChunks = ceil($pdfSize / $chunkSize);
    
    $initParams = $parameters;
    $initParams['AttachFile'] = null;
    if (!@uploadToSQLServer($api, $table, $initParams)) {
        return false;
    }
    
    $firstChunk = substr($pdfData, 0, min($chunkSize, $pdfSize));
    $sqlFirst = "UPDATE $table SET AttachFile = @ChunkData WHERE RegistryNum = @RegistryNum";
    $firstParams = [
        'RegistryNum' => $parameters['RegistryNum'],
        'ChunkData' => base64_encode($firstChunk)
    ];
    if (@$api->executeSQL($sqlFirst, 'LCRDbase_Online', 'UPDATE', $firstParams) === false) {
        return false;
    }
    
    for ($i = 1; $i < $totalChunks; $i++) {
        $offset = $i * $chunkSize;
        $chunk = substr($pdfData, $offset, min($chunkSize, $pdfSize - $offset));
        
        $sqlAppend = "UPDATE $table SET AttachFile = ISNULL(AttachFile, 0x) + @ChunkData WHERE RegistryNum = @RegistryNum";
        $chunkParams = [
            'RegistryNum' => $parameters['RegistryNum'],
            'ChunkData' => base64_encode($chunk)
        ];
        if (@$api->executeSQL($sqlAppend, 'LCRDbase_Online', 'UPDATE', $chunkParams) === false) {
            return false;
        }
    }
    
    return true;
}

function buildUpdateSql($table, $params) {
    $sets = [];
    foreach ($params as $key => $value) {
        if ($key !== 'RegistryNum') {
            $sets[] = "$key = " . ($value === null ? 'NULL' : "@$key");
        }
    }
    return "UPDATE $table SET " . implode(', ', $sets) . " WHERE RegistryNum = @RegistryNum";
}

function buildInsertSql($table, $params) {
    $keys = array_keys($params);
    $values = array_map(function($k) use ($params) {
        return $params[$k] === null ? 'NULL' : "@$k";
    }, $keys);
    return "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
}



// REGISTERED RECORDS
function listRegisteredRecords($db) {
    $type = $_GET['type'] ?? 'birth';
    $page = (int)($_GET['page'] ?? 1); // page = 1 means latest year
    $search = $_GET['search'] ?? '';

    if (!in_array($type, ['birth', 'death', 'marriage'])) {
        throw new Exception('Invalid record type');
    }

    $tables = getTableNames($type);
    $db->cleanupOrphans(ucfirst($type));

    // Step 1: get distinct years (from RegistryNum or date)
    $yearColumn = match($type) {
        'birth' => 'YEAR(CBirthDate)',
        'death' => 'YEAR(CDeathDate)',
        'marriage' => 'YEAR(MarriageDate)',
        default => 'YEAR(CBirthDate)'
    };

    $whereClauses = ["RegistryNum REGEXP '^[0-9]{4}-'"];
    $params = [];

    if (!empty($search)) {
        switch ($type) {
            case 'birth':
            case 'death':
                $whereClauses[] = "(RegistryNum LIKE ? OR CFirstName LIKE ? OR CLastName LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                break;
            case 'marriage':
                $whereClauses[] = "(RegistryNum LIKE ? OR HFirstName LIKE ? OR HLastName LIKE ? OR WFirstName LIKE ? OR WLastName LIKE ?)";
                for ($i=0; $i<5; $i++) $params[] = "%$search%";
                break;
        }
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);

    // Step 2: get distinct years descending
    $yearsSQL = "SELECT DISTINCT $yearColumn AS yr FROM {$tables['main']} $whereSQL ORDER BY yr DESC";
    $yearResults = $db->fetchAll($yearsSQL, $params);
    $years = array_column($yearResults, 'yr');

    $totalPages = count($years);

    if ($totalPages === 0) {
        return [
            'success' => true,
            'data' => [
                'records' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 1,
                    'year' => null
                ]
            ]
        ];
    }

    // Step 3: get year for this page
    $page = max(1, min($page, $totalPages));
    $yearForPage = $years[$page - 1];

    // Step 4: get all records for this year
    $sql = "SELECT " . getSelectColumns($type) . " FROM {$tables['main']} 
            $whereSQL AND $yearColumn = ? 
            ORDER BY $yearColumn DESC, RegistryNum DESC";
    $records = $db->fetchAll($sql, array_merge($params, [$yearForPage]));

    $formatted = [];
    foreach ($records as $record) {
        $formatted[] = formatRecord($record, null, 0, $type);
    }

    return [
        'success' => true,
        'data' => [
            'records' => $formatted,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'year' => $yearForPage
            ]
        ]
    ];
}
?>