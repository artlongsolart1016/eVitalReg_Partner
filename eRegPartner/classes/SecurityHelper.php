<?php
/**
 * Security Helper - CSRF, Session, Audit Logging
 */

class SecurityHelper {
    
    /**
     * Generate CSRF Token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF Token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check if user is logged in
     */
    public static function requireLogin() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header('Location: login.php');
            exit;
        }
        
        // Check session timeout (1 hour)
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > 3600) { // 1 hour
                session_unset();
                session_destroy();
                header('Location: login.php?timeout=1');
                exit;
            }
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Check if user has permission for action
     */
    public static function hasPermission($action) {
        // Add your permission logic here
        // For now, all logged-in users can send
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        // Future: Check user role/permissions from database
        // Example: $_SESSION['permissions'] contains array of allowed actions
        
        return true;
    }
    
    /**
     * Audit Log - Track all actions
     */
    public static function auditLog($action, $details, $registryNum = null) {
        try {
            $db = new MySQL_DatabaseManager();
            
            $sql = "INSERT INTO audit_log 
                    (user_id, username, action, details, registry_num, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $_SESSION['user_id'] ?? 0,
                $_SESSION['username'] ?? 'unknown',
                $action,
                $details,
                $registryNum,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            
            $db->executeNonQuery($sql, $params, 'support');
        } catch (Exception $e) {
            // Silent fail - don't break app if audit logging fails
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
    
    /**
     * Rate Limiting - Prevent abuse
     */
    public static function checkRateLimit($action, $maxAttempts = 10, $timeWindow = 60) {
        $key = $action . '_' . ($_SESSION['user_id'] ?? session_id());
        
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        $now = time();
        
        // Clean old entries
        if (isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = array_filter(
                $_SESSION['rate_limit'][$key],
                function($timestamp) use ($now, $timeWindow) {
                    return ($now - $timestamp) < $timeWindow;
                }
            );
        } else {
            $_SESSION['rate_limit'][$key] = [];
        }
        
        // Check if limit exceeded
        if (count($_SESSION['rate_limit'][$key]) >= $maxAttempts) {
            return false;
        }
        
        // Add current attempt
        $_SESSION['rate_limit'][$key][] = $now;
        
        return true;
    }
    
    /**
     * Sanitize input
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate Registry Number format
     */
    public static function validateRegistryNum($regNum) {
        // Birth registry numbers start with !
        return preg_match('/^![\d]+$/', $regNum);
    }
}