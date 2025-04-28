<?php

namespace WeeConnectPay\Integrations;

/**
 * Centralized logging service for WeeConnectPay
 * Provides static access to logging functionality while maintaining testability
 * 
 * @package WeeConnectPay\Integrations
 * @since 3.13.0
 */
class LogService {
    private static ?Logger $instance = null;
    
    /**
     * Log a message with specified level
     * 
     * @param mixed $message The message to log
     * @param string $level The log level (debug, info, warning, error)
     */
    public static function log($message, string $level = 'debug'): void {
        self::getInstance()->log($message, $level);
    }
    
    /**
     * Log a debug message
     * 
     * @param mixed $message The message to log
     */
    public static function debug($message): void {
        self::getInstance()->debug($message);
    }
    
    /**
     * Log an info message
     * 
     * @param mixed $message The message to log
     */
    public static function info($message): void {
        self::getInstance()->info($message);
    }
    
    /**
     * Log a warning message
     * 
     * @param mixed $message The message to log
     */
    public static function warning($message): void {
        self::getInstance()->warning($message);
    }
    
    /**
     * Log an error message
     * 
     * @param mixed $message The message to log
     */
    public static function error($message): void {
        self::getInstance()->error($message);
    }
    
    /**
     * Get or create the Logger instance
     * 
     * @return Logger
     */
    private static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = Logger::create();
        }
        return self::$instance;
    }
    
    /**
     * Set the Logger instance - primarily for testing purposes
     * 
     * @param Logger|null $logger The logger instance to use
     */
    public static function setInstance(?Logger $logger): void {
        self::$instance = $logger;
    }
} 