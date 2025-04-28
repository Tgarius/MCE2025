<?php

namespace WeeConnectPay\WordPress\Plugin\includes;

use WeeConnectPay\Integrations\Logger;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Controller class for handling log-related REST API endpoints
 * 
 * @since 3.12.6
 */
class WeeConnectPayLogController {

    /**
     * @var Logger
     */
    private Logger $logger;

    public function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Register the REST API routes for logs
     */
    public function register_routes() {
        register_rest_route('weeconnectpay/v1', '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('weeconnectpay/v1', '/logs/download', [
            'methods' => 'GET',
            'callback' => [$this, 'download_logs'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('weeconnectpay/v1', '/logs/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_logs'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }

    /**
     * Check if the current user has permission to access logs
     * 
     * @return bool
     */
    public function check_permissions(): bool {
        return current_user_can('manage_woocommerce');
    }

    /**
     * Get filtered logs with pagination
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_logs(WP_REST_Request $request): WP_REST_Response {
        $filters = [
            'level' => $request->get_param('level'),
            'date_from' => $request->get_param('dateFrom'),
            'date_to' => $request->get_param('dateTo'),
        ];

        $page = max(1, (int)$request->get_param('page'));
        $per_page = (int)$request->get_param('per_page') ?: 10; // Default to 10 if not specified
        
        $result = $this->logger->getFilteredLogs($filters, $page, $per_page);
        
        return new WP_REST_Response($result);
    }

    /**
     * Download logs as a text file
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function download_logs(WP_REST_Request $request): WP_REST_Response {
        $filters = [
            'level' => $request->get_param('level'),
            'date_from' => $request->get_param('dateFrom'),
            'date_to' => $request->get_param('dateTo'),
        ];

        $filepath = $this->logger->downloadLogs($filters);
        
        if (!$filepath || !file_exists($filepath)) {
            return new WP_REST_Response(['error' => 'Failed to generate log file'], 500);
        }

        $content = file_get_contents($filepath);
        unlink($filepath); // Clean up the temporary file

        $response = new WP_REST_Response($content);
        $response->header('Content-Type', 'text/plain; charset=UTF-8');
        $response->header('Content-Disposition', 'attachment; filename="weeconnectpay-logs.txt"');
        
        // Prevent WordPress from JSON encoding the response
        remove_filter('rest_pre_serve_request', 'rest_send_allow_header');
        add_filter('rest_pre_serve_request', function($served, $result) use ($content) {
            echo $content;
            return true;
        }, 10, 2);
        
        return $response;
    }

    /**
     * Clear all logs
     * 
     * @return WP_REST_Response
     */
    public function clear_logs(): WP_REST_Response {
        $success = $this->logger->clearLogs();
        
        if (!$success) {
            return new WP_REST_Response(['error' => 'Failed to clear logs'], 500);
        }

        return new WP_REST_Response(['message' => 'Logs cleared successfully']);
    }
} 