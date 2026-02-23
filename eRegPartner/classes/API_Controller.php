<?php
/**
 * API Controller - Matches Windows IIS API exactly
 * IIS Endpoints: /dmslcr004/execsql (with token), /dmslcr004/execsql1 (without token)
 * Reverse Proxy: https://sakatamalaybalay.com/api/lcr/dmslcr004.php
 */
class API_Controller {
    private $apiUrl;
    private $clientToken;
    private $timeout;
    private $maxRetries;
    
    public function __construct() {
        // Get from config
        $this->apiUrl = defined('API_ENDPOINT') ? API_ENDPOINT : '';
        $this->clientToken = defined('API_KEY') ? API_KEY : '';
        $this->timeout = 600; // 10 minutes (matching VB.NET)
        $this->maxRetries = 3;
        
        if (empty($this->apiUrl)) {
            throw new Exception('API endpoint not configured. Please set API_ENDPOINT in config.php');
        }
        
        if (empty($this->clientToken)) {
            throw new Exception('API key not configured. Please set API_KEY in config.php');
        }
    }
    
    /**
     * Execute SQL command via IIS API
     * Matches VB.NET ExecuteSQLAsync exactly
     */
    public function executeSQL($sql, $database, $queryType, $parameters = []) {
        // Clean parameters (matching VB.NET)
        $cleanedParams = [];
        foreach ($parameters as $key => $value) {
            if ($value === null || $value === '') {
                $cleanedParams[$key] = null;
            } elseif (is_resource($value)) {
                // Handle binary data
                $cleanedParams[$key] = base64_encode(stream_get_contents($value));
            } else {
                $cleanedParams[$key] = $value;
            }
        }
        
        // Build payload (matching VB.NET and IIS API SqlPayload)
        $payload = [
            'Sql' => $sql,
            'Database' => $database,
            'QueryType' => strtoupper($queryType),
            'Params' => $cleanedParams
        ];
        
        // Execute with retry logic
        return $this->executeWithRetry(function() use ($payload) {
            return $this->sendRequest($payload);
        });
    }
    
    /**
     * Send HTTP request to IIS API
     */
    private function sendRequest($payload) {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        
        $ch = curl_init($this->apiUrl);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Client-Token: ' . $this->clientToken  // IIS API expects this header
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("CURL Error: " . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: " . $httpCode);
        }
        
        if (empty($response)) {
            throw new Exception("Empty response from server");
        }
        
        // Parse response (matching IIS API response format)
        $result = json_decode($response, true);
        
        if ($result === null) {
            throw new Exception("Invalid JSON response: " . $response);
        }
        
        // Check for errors (IIS API returns {error: "..."})
        if (isset($result['error']) && $result['error'] !== null) {
            throw new Exception("API Error: " . $result['error']);
        }
        
        // Handle SELECT queries (IIS returns {rows: [...]})
        if (isset($result['rows'])) {
            return $result['rows'];
        }
        
        // Handle INSERT/UPDATE/DELETE (IIS returns {RowsAffected: N})
        if (isset($result['RowsAffected'])) {
            return true;
        }
        
        // Success but no specific data
        return true;
    }
    
    /**
     * Execute with retry logic (matching VB.NET)
     */
    private function executeWithRetry($operation) {
        $lastException = null;
        
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (Exception $e) {
                $lastException = $e;
                
                if ($attempt < $this->maxRetries) {
                    // Wait before retry (exponential backoff)
                    $delay = pow(2, $attempt) * 1000000; // microseconds
                    usleep(min($delay, 30000000)); // Max 30 seconds
                }
            }
        }
        
        // All retries failed
        throw new Exception("Operation failed after {$this->maxRetries} retries: " . $lastException->getMessage());
    }
    
    /**
     * Ping IIS API to check connectivity
     */
    public function ping() {
        try {
            // IIS API has /dmslcr004/ping endpoint
            $pingUrl = str_replace('execsql', 'ping', $this->apiUrl);
            
            $ch = curl_init($pingUrl);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Client-Token: ' . $this->clientToken],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
        } catch (Exception $e) {
            return false;
        }
    }
}