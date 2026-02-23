<?php
/**
 * Config.php
 * Database Configuration
 * Partner Transmission Project
 */

// Session configuration
ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params(3600);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// MySQL Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_PORT_SUPPORT', 3307);

// Main Database (phcris)
define('DB_NAME_MAIN', 'phcris');
define('DB_USER_MAIN', 'philcris_lad123');
define('DB_PASS_MAIN', 'PhilCRIS_ladPass123');

// Support Database (phcris_support)
define('DB_NAME_SUPPORT', 'phcris_support');
define('DB_USER_SUPPORT', 'philcris_lad123');
define('DB_PASS_SUPPORT', 'PhilCRIS_ladPass123');

// Database name aliases for query compatibility
define('DB_SUPPORT_NAME', DB_NAME_SUPPORT);
define('DB_MAIN_NAME', DB_NAME_MAIN);

// ════════════════════════════════════════════════════════════════
// SQL SERVER API CONFIGURATION (Windows IIS via Reverse Proxy)
// ════════════════════════════════════════════════════════════════
// Your Windows IIS API: /dmslcr004/execsql (with token)
// Reverse Proxy translates this to: https://sakatamalaybalay.com/api/lcr/dmslcr004.php/execsql
define('API_ENDPOINT', 'https://192.168.108.89/dmslcr004/execsql');
define('API_KEY', 'A01-TEST12345'); // Your Client-Token from IIS Table_ClientRegistry
define('API_SQL_SERVER_DATABASE', 'LCRDbase_Online');

// Application Settings
define('APP_NAME', 'Transmission System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/partner_project/');

// File Upload Settings
define('MAX_FILE_SIZE', 128 * 1024 * 1024); // 128MB
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_EXTENSIONS', ['pdf']);

// Table Names - Main Database (phcris)
define('TABLE_BIRTH', 'birthdocument');
define('TABLE_DEATH', 'deathdocument');
define('TABLE_MARRIAGE', 'marriagedocument');

// Table Names - Support Database (phcris_support)
define('TABLE_LOGIN', 'table_login_f1');
define('TABLE_BIRTH_LOG', 'registry_birth_log');
define('TABLE_BIRTH_HISTORY', 'registry_birth_history');
define('TABLE_DEATH_LOG', 'registry_death_log');
define('TABLE_DEATH_HISTORY', 'registry_death_history');
define('TABLE_MARRIAGE_LOG', 'registry_marriage_log');
define('TABLE_MARRIAGE_HISTORY', 'registry_marriage_history');

// Pagination
define('RECORDS_PER_PAGE', 50);

// Create upload directory if not exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>