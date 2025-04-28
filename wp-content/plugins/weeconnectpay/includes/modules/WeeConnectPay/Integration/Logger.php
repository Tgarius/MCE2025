<?php

namespace WeeConnectPay\Integrations;

/**
 * Class Logger
 * Handles all logging functionality for WeeConnectPay
 * 
 * @package WeeConnectPay\Integrations
 */
class Logger {
    /**
     * @var string
     */
    private string $logPrefix = '[WeeConnectPay] ';

    /**
     * Maximum log file size in bytes (50MB)
     * @var int
     */
    private const MAX_LOG_SIZE = 52428800;

    /**
     * Number of logs per page
     * @var int
     */
    private const LOGS_PER_PAGE = 100;

    /**
     * Valid log levels
     * @var array
     */
    private const VALID_LOG_LEVELS = ['debug', 'info', 'warning', 'error'];

    /**
     * Custom log file name
     * @var string
     */
    private const LOG_FILENAME = 'weeconnectpay-debug.log';

    /**
     * @var string|null Path to the log file
     */
    private ?string $logFilePath = null;

    /**
     * Logger constructor.
     */
    public function __construct() {
        $this->initializeLogFile();
    }

    /**
     * Create a new Logger instance
     * 
     * @return Logger
     */
    public static function create(): Logger {
        return new self();
    }

    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    private function isDebugEnabled(): bool {
        $integrationSettings = new IntegrationSettings();
        return $integrationSettings->getDebugModeOrDefault();
    }

    /**
     * Sanitize and validate log message
     * 
     * @param mixed $message The message to sanitize
     * @return string The sanitized message
     */
    private function sanitizeLogMessage($message): string {
        if ($message === null) {
            return '[Empty: null message]';
        }
        
        if (is_array($message) || is_object($message)) {
            if (empty($message)) {
                return '[Empty: ' . (is_array($message) ? 'array' : 'object') . ']';
            }
            
            // Protect against deeply nested structures
            $depth = 0;
            $message = $this->sanitizeStructure($message, $depth);
            
            // For arrays/objects, encode to JSON with proper formatting
            $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($message === false) {
                return '[Error: Unable to encode data - ' . json_last_error_msg() . ']';
            }
        } else {
            // Convert to string and escape our control sequences
            $message = (string)$message;
            if (trim($message) === '') {
                return '[Empty: blank message]';
            }
            $message = str_replace(['[WeeConnectPay]', '[DEBUG]', '[INFO]', '[WARNING]', '[ERROR]'], 
                                 ['[escaped-WeeConnectPay]', '[escaped-DEBUG]', '[escaped-INFO]', '[escaped-WARNING]', '[escaped-ERROR]'], 
                                 $message);
        }
        
        // Remove only dangerous control characters, preserve normal whitespace
        $originalLength = mb_strlen($message);
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]|\x7F/u', '', $message);
        $newLength = mb_strlen($message);
        
        if ($newLength === 0 && $originalLength > 0) {
            return '[Empty: message contained only control characters]';
        }
        
        // Limit message length to prevent DOS
        if (mb_strlen($message) > 10000) {
            return substr($message, 0, 10000) . ' [Truncated: message exceeded maximum length]';
        }
        
        return $message;
    }

    /**
     * Sanitize nested structures with depth limit and size checks
     * 
     * @param mixed $data The data to sanitize
     * @param int &$depth Current depth
     * @return mixed Sanitized data
     */
    private function sanitizeStructure($data, int &$depth, int $maxDepth = 5): mixed {
        $depth++;
        
        // Prevent deeply nested structures
        if ($depth > $maxDepth) {
            return '[Truncated: Maximum nesting depth exceeded]';
        }

        if (is_array($data)) {
            if (empty($data)) {
                return '[Empty: array]';
            }
            
            $result = [];
            // Limit number of elements
            $count = 0;
            foreach ($data as $key => $value) {
                if ($count++ > 100) { // Limit array size
                    $result['[truncated]'] = '[Truncated: Array exceeded 100 elements]';
                    break;
                }
                // Sanitize keys to prevent injection
                $safeKey = is_string($key) ? 
                    substr(preg_replace('/[^\w\-\.]/', '_', $key), 0, 64) : // Limit key length and characters
                    $key;
                $result[$safeKey] = $this->sanitizeStructure($value, $depth, $maxDepth);
            }
            return $result;
        } elseif (is_object($data)) {
            if (empty((array)$data)) {
                return '[Empty: object]';
            }
            // Convert object to array to apply same sanitization
            return $this->sanitizeStructure((array)$data, $depth, $maxDepth);
        } elseif (is_string($data)) {
            // Limit string length and escape control sequences
            if (mb_strlen($data) > 1000) {
                return substr($data, 0, 1000) . ' [Truncated: string exceeded 1000 characters]';
            }
            return $data === '' ? '[Empty: string]' : $data;
        }
        
        // Return other types as-is (numbers, booleans, null)
        return $data ?? '[Empty: null]';
    }

    /**
     * Validate log level
     * 
     * @param string $level The log level to validate
     * @return string The validated log level
     */
    private function validateLogLevel(string $level): string {
        $validLevels = ['debug', 'info', 'warning', 'error'];
        $level = strtolower(trim($level));
        return in_array($level, $validLevels, true) ? $level : 'debug';
    }

    /**
     * Log a message if debug mode is enabled
     * 
     * @param mixed $message The message to log
     * @param string $level The log level (debug, info, warning, error)
     * @return void
     */
    public function log($message, string $level = 'debug'): void {
        try {
            // If we have settings and debug mode is disabled, don't log
            if (!$this->isDebugEnabled()) {
                return;
            }

            // Sanitize and validate inputs
            $message = $this->sanitizeLogMessage($message);
            $level = $this->validateLogLevel($level);

            $logMessage = $this->logPrefix . '[' . strtoupper($level) . '] ' . $message;
            
            // Try to write to our custom log file first
            $written = $this->writeToLogFile($logMessage);
            
            // If writing to our file fails, or if it's an error level message,
            // also write to WordPress error_log as a fallback/additional logging
            if (!$written || $level === 'error') {
                error_log($logMessage);
            }
        } catch (\Throwable $e) {
            // If logging fails, use basic error_log as fallback
            error_log($this->logPrefix . 'Logging failed: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Debug level log
     * 
     * @param mixed $message
     * @return void
     */
    public function debug($message): void {
        $this->log($message, 'debug');
    }

    /**
     * Info level log
     * 
     * @param mixed $message
     * @return void
     */
    public function info($message): void {
        $this->log($message, 'info');
    }

    /**
     * Warning level log
     * 
     * @param mixed $message
     * @return void
     */
    public function warning($message): void {
        $this->log($message, 'warning');
    }

    /**
     * Error level log
     * 
     * @param mixed $message
     * @return void
     */
    public function error($message): void {
        $this->log($message, 'error');
    }

    /**
     * Get all logs from our custom log file
     * Only returns logs with our prefix
     * 
     * @param int $limit Maximum number of lines to return
     * @return array
     */
    public function getLogs(int $limit = 1000): array {
        try {
            if (!$this->isDebugEnabled()) {
                return [];
            }

            // Validate limit
            $limit = max(1, min((int)$limit, 1000));

            if (!$this->logFilePath || !file_exists($this->logFilePath) || !is_readable($this->logFilePath)) {
                return [];
            }

            // Check file size to prevent memory issues
            $fileSize = filesize($this->logFilePath);
            if ($fileSize === false || $fileSize > self::MAX_LOG_SIZE) {
                LogService::warning('Log file too large or unreadable');
                return [];
            }

            $logs = [];
            $handle = fopen($this->logFilePath, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    // Only process lines with our prefix
                    if (strpos($line, $this->logPrefix) !== false) {
                        // Sanitize the log line before adding it
                        $sanitizedLine = $this->sanitizeLogLine($line);
                        if ($sanitizedLine !== '') {
                            array_unshift($logs, $sanitizedLine);
                            if (count($logs) >= $limit) {
                                break;
                            }
                        }
                    }
                }
                fclose($handle);
            }

            return $logs;
        } catch (\Throwable $e) {
            // Keep using error_log here as fallback since this is in the logger itself
            error_log($this->logPrefix . 'Failed to get logs: ' . esc_html($e->getMessage()));
            return [];
        }
    }

    /**
     * Sanitize a log line for display
     * 
     * @param string $line The log line to sanitize
     * @return string The sanitized log line
     */
    private function sanitizeLogLine(string $line): string {
        // Only process our actual log lines
        if (strpos($line, $this->logPrefix) === false) {
            return '[Filtered: not a WeeConnectPay log entry]';
        }
        
        // Remove only dangerous control characters, preserve normal whitespace
        $originalLength = mb_strlen($line);
        $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]|\x7F/u', '', $line);
        $newLength = mb_strlen($line);
        
        if ($newLength === 0 && $originalLength > 0) {
            return '[Filtered: line contained only control characters]';
        }
        
        // Convert to UTF-8 if not already
        if (!mb_check_encoding($line, 'UTF-8')) {
            $line = mb_convert_encoding($line, 'UTF-8', 'auto');
            if ($line === false) {
                return '[Error: Invalid UTF-8 encoding]';
            }
        }
        
        // Extract the actual log message after our prefix
        $prefixPos = strpos($line, $this->logPrefix);
        if ($prefixPos !== false) {
            $line = substr($line, $prefixPos);
        }
        
        // Unescape our legitimate log markers
        $line = str_replace(['[escaped-WeeConnectPay]', '[escaped-DEBUG]', '[escaped-INFO]', '[escaped-WARNING]', '[escaped-ERROR]'],
                          ['[WeeConnectPay]', '[DEBUG]', '[INFO]', '[WARNING]', '[ERROR]'],
                          $line);
        
        $trimmed = trim($line);
        if ($trimmed === '') {
            return '[Empty: blank line]';
        }
        
        return $trimmed;
    }

    /**
     * Check if a string is valid JSON
     * 
     * @param string $string The string to check
     * @return bool Whether the string is valid JSON
     */
    private function isJson(string $string): bool {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get logs with filtering and pagination
     * 
     * @param array $filters Array of filters (level, date_from, date_to)
     * @param int $page Page number (1-based)
     * @param int $per_page Number of logs per page
     * @return array Array containing logs and pagination info
     */
    public function getFilteredLogs(array $filters = [], int $page = 1, int $per_page = 10): array {
        try {
            if (!$this->isDebugEnabled()) {
                return $this->emptyLogsResponse($per_page);
            }

            if (!$this->logFilePath || !file_exists($this->logFilePath) || !is_readable($this->logFilePath)) {
                return $this->emptyLogsResponse($per_page);
            }

            // Check file size
            $fileSize = filesize($this->logFilePath);
            if ($fileSize === false || $fileSize > self::MAX_LOG_SIZE) {
                $this->rotateLogFile($this->logFilePath);
            }

            // Initialize variables
            $logs = [];
            $totalLogs = 0;
            $filteredLogs = [];

            // Read and filter logs
            $handle = fopen($this->logFilePath, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    if (strpos($line, $this->logPrefix) !== false) {
                        $log = $this->parseLine($line);
                        if ($this->matchesFilters($log, $filters)) {
                            array_unshift($filteredLogs, $log);
                            $totalLogs++;
                        }
                    }
                }
                fclose($handle);
            }

            // No need to sort since we're already adding newest first
            // Calculate pagination
            $totalPages = ceil($totalLogs / $per_page);
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $per_page;
            
            // Get logs for current page
            $logs = array_slice($filteredLogs, $offset, $per_page);

            return [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_logs' => $totalLogs,
                    'per_page' => $per_page
                ]
            ];
        } catch (\Throwable $e) {
            // Keep using error_log here as fallback since this is in the logger itself
            error_log($this->logPrefix . 'Failed to get filtered logs: ' . esc_html($e->getMessage()));
            return $this->emptyLogsResponse($per_page);
        }
    }

    /**
     * Parse a log line into structured data
     * 
     * @param string $line Log line to parse
     * @return array Structured log data
     */
    private function parseLine(string $line): array {
        $timestamp = strtotime(substr($line, 0, 19));
        preg_match('/\[(DEBUG|INFO|WARNING|ERROR)\]/', $line, $levelMatches);
        $level = isset($levelMatches[1]) ? strtolower($levelMatches[1]) : 'debug';
        
        return [
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
            'level' => $level,
            'message' => $this->sanitizeLogLine($line),
            'raw' => $line
        ];
    }

    /**
     * Check if a log entry matches the given filters
     * 
     * @param array $log Log entry
     * @param array $filters Filters to apply
     * @return bool Whether the log matches the filters
     */
    private function matchesFilters(array $log, array $filters): bool {
        // Level filter
        if (!empty($filters['level']) && $log['level'] !== $filters['level']) {
            return false;
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $fromTimestamp = strtotime($filters['date_from']);
            if ($fromTimestamp && $log['timestamp'] < $fromTimestamp) {
                return false;
            }
        }

        if (!empty($filters['date_to'])) {
            $toTimestamp = strtotime($filters['date_to']);
            if ($toTimestamp && $log['timestamp'] > $toTimestamp) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rotate the log file if it exceeds the maximum size
     * 
     * @param string $logFile Path to log file
     * @return void
     */
    private function rotateLogFile(string $logFile): void {
        try {
            $backupFile = $logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
            if (copy($logFile, $backupFile)) {
                // Clear the original file
                file_put_contents($logFile, '');
                // Keep only the last 5 backup files
                $backups = glob($logFile . '.*.bak');
                if (count($backups) > 5) {
                    usort($backups, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $oldBackups = array_slice($backups, 5);
                    foreach ($oldBackups as $old) {
                        unlink($old);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep using error_log here as fallback since this is in the logger itself
            error_log($this->logPrefix . 'Failed to rotate log file: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Get empty logs response structure
     * 
     * @param int $per_page Number of logs per page
     * @return array Empty logs response
     */
    private function emptyLogsResponse(int $per_page = 10): array {
        return [
            'logs' => [],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 0,
                'total_logs' => 0,
                'per_page' => $per_page
            ]
        ];
    }

    /**
     * Get available log levels
     * 
     * @return array Array of valid log levels
     */
    public function getLogLevels(): array {
        return self::VALID_LOG_LEVELS;
    }

    /**
     * Gather system information for logging context
     * 
     * @return string Formatted system information
     */
    private function getSystemInformation(): string {
        try {
            global $wp_version;
            
            $output = [];
            $output[] = "=== WeeConnectPay System Information ===";
            $output[] = "Generated: " . date('Y-m-d H:i:s T');
            $output[] = "";
            
            // Core Information
            $output[] = "--- Core Versions ---";
            $output[] = sprintf("WordPress Version: %s", $wp_version);
            if (class_exists('WooCommerce')) {
                $output[] = sprintf("WooCommerce Version: %s", WC()->version);
            }
            $output[] = sprintf("PHP Version: %s", PHP_VERSION);
            $output[] = "";
            
            // Site Information
            $output[] = "--- Site Information ---";
            $output[] = sprintf("Site URL: %s", get_site_url());
            $integrationSettings = new IntegrationSettings();

            // Integration UUID
            try {
                $output[] = sprintf("Integration UUID: %s", $integrationSettings->getIntegrationUuid());
            } catch (\Throwable $e) {
                $output[] = "Integration UUID: Not available";
            }

            // Merchant Information
            try {
                $merchant = $integrationSettings->getCloverMerchant();
                $output[] = sprintf("Merchant Name: %s", $merchant->getName());
                $output[] = sprintf("Merchant ID: %s", $merchant->getUuid());
            } catch (\Throwable $e) {
                $output[] = "Merchant Name: Not available";
                $output[] = "Merchant ID: Not available";
            }

            // Employee Information
            try {
                $employee = $integrationSettings->getCloverEmployee();
                $output[] = sprintf("Employee Name: %s", $employee->getName());
                $output[] = sprintf("Employee ID: %s", $employee->getUuid());
            } catch (\Throwable $e) {
                $output[] = "Employee Name: Not available";
                $output[] = "Employee ID: Not available";
            }

            $output[] = "";
            
            // Dependencies
            $output[] = "--- Dependencies ---";
            $dependencies = [
                new \WeeConnectPay\Dependency('WooCommerce', [3, 0, 0]),
                new \WeeConnectPay\Dependency('WordPress', [5, 0, 0]),
                new \WeeConnectPay\Dependency('PHP', [7, 4, 0]),
                new \WeeConnectPay\Dependency('WPDB_PREFIX', []),
                new \WeeConnectPay\Dependency('PERMALINK', []),
            ];
            
            foreach ($dependencies as $dependency) {
                $validator = new \WeeConnectPay\Validators\DependencyValidator($dependency);
                try {
                    $version = [];
                    switch ($dependency->name) {
                        case 'WooCommerce':
                            if (class_exists('WooCommerce')) {
                                $version = array_map('intval', explode('.', WC()->version));
                            }
                            break;
                        case 'WordPress':
                            $version = array_map('intval', explode('.', $wp_version));
                            break;
                        case 'PHP':
                            $version = array_map('intval', explode('.', PHP_VERSION));
                            break;
                        case 'WPDB_PREFIX':
                            global $wpdb;
                            $version = [$wpdb->prefix ? 1 : 0];
                            $output[] = sprintf("%s: %s (Required: non-empty, Current: '%s')",
                                $dependency->name,
                                $wpdb->prefix ? "OK" : "FAIL",
                                $wpdb->prefix
                            );
                            continue 2; // Skip the default output for this dependency
                        case 'PERMALINK':
                            $permalink_structure = get_option('permalink_structure');
                            $version = [$permalink_structure !== '' ? 1 : 0];
                            $output[] = sprintf("%s: %s (Required: non-plain, Current: %s)",
                                $dependency->name,
                                $permalink_structure !== '' ? "OK" : "FAIL",
                                $permalink_structure === '' ? "plain" : $permalink_structure
                            );
                            continue 2; // Skip the default output for this dependency
                    }
                    $validator->validate($version);
                    $output[] = sprintf("%s: OK (Required: %s, Current: %s)",
                        $dependency->name,
                        implode('.', $dependency->minVer),
                        implode('.', $version)
                    );
                } catch (\Throwable $e) {
                    $output[] = sprintf("%s: FAIL - %s",
                        $dependency->name,
                        $e->getMessage()
                    );
                }
            }
            $output[] = "";
            
            // Active Plugins
            $output[] = "--- Active Plugins ---";
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $active_plugins = get_option('active_plugins');
            $all_plugins = get_plugins();
            
            foreach ($active_plugins as $plugin) {
                if (isset($all_plugins[$plugin])) {
                    $plugin_data = $all_plugins[$plugin];
                    $output[] = sprintf("%s: %s",
                        $plugin_data['Name'],
                        $plugin_data['Version']
                    );
                }
            }
            $output[] = "";
            $output[] = str_repeat("=", 50);
            $output[] = "";
            
            return implode("\n", $output);
        } catch (\Throwable $e) {
            return "Error gathering system information: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Download logs as a text file
     * 
     * @param array $filters Filters to apply
     * @return string Path to the generated file
     */
    public function downloadLogs(array $filters = []): string {
        try {
            if (!$this->logFilePath || !file_exists($this->logFilePath)) {
                return '';
            }

            // Get all logs without pagination by setting a very high per_page value
            $allLogs = $this->getFilteredLogs($filters, 1, PHP_INT_MAX)['logs'];
            $filename = 'weeconnectpay-logs-' . date('Y-m-d-H-i-s') . '.txt';
            $filepath = wp_upload_dir()['path'] . '/' . $filename;

            // Write system information and logs to the file
            $handle = fopen($filepath, 'w');
            if ($handle) {
                // Write system information first
                fwrite($handle, $this->getSystemInformation());
                
                // Write log entries
                fwrite($handle, "=== Log Entries ===\n\n");
                foreach ($allLogs as $log) {
                    $line = rtrim($log['raw']) . PHP_EOL;
                    fwrite($handle, $line);
                }
                fclose($handle);
            }

            return $filepath;
        } catch (\Throwable $e) {
            LogService::error(sprintf(
                'Failed to generate log download: %s',
                json_encode($e->getMessage())
            ));
            return '';
        }
    }

    /**
     * Clear the log file
     * 
     * @return bool True if successful, false otherwise
     */
    public function clearLogs(): bool {
        try {
            if (!$this->isDebugEnabled() || !$this->logFilePath) {
                return false;
            }

            if (!file_exists($this->logFilePath)) {
                return true;
            }

            // Create a backup before clearing
            $backupFile = $this->logFilePath . '.' . date('Y-m-d-H-i-s') . '.bak';
            if (!copy($this->logFilePath, $backupFile)) {
                return false;
            }

            // Clear the file
            if (file_put_contents($this->logFilePath, '') === false) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            // Keep using error_log here as fallback since this is in the logger itself
            error_log($this->logPrefix . 'Failed to clear logs: ' . esc_html($e->getMessage()));
            return false;
        }
    }

    /**
     * Initialize the log file
     * 
     * @return void
     */
    private function initializeLogFile(): void {
        $this->logFilePath = WEECONNECTPAY_PLUGIN_PATH . 'logs/' . self::LOG_FILENAME;
    }

    /**
     * Write to the custom log file
     * 
     * @param string $message The message to write
     * @return bool Whether the write was successful
     */
    private function writeToLogFile(string $message): bool {
        try {
            if (!$this->logFilePath) {
                return false;
            }

            // Check file size and rotate if necessary
            if (file_exists($this->logFilePath)) {
                $fileSize = filesize($this->logFilePath);
                if ($fileSize && $fileSize > self::MAX_LOG_SIZE) {
                    $this->rotateLogFile($this->logFilePath);
                }
            }

            // Ensure directory exists
            $dir = dirname($this->logFilePath);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            // Add timestamp to the message
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] $message" . PHP_EOL;

            // Write to file with proper locking
            $result = file_put_contents(
                $this->logFilePath,
                $message,
                FILE_APPEND | LOCK_EX
            );

            return $result !== false;
        } catch (\Throwable $e) {
            error_log($this->logPrefix . 'Failed to write to log file: ' . esc_html($e->getMessage()));
            return false;
        }
    }

    /**
     * Get the path to the custom log file
     * 
     * @return string|null
     */
    public function getLogFilePath(): ?string {
        return $this->logFilePath;
    }
} 