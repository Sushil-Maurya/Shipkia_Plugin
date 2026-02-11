<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipkia Logger Class
 * 
 * Handles enterprise-grade logging using native WC_Logger.
 * Supports multiple sources, masking of sensitive data, and context injection.
 */
class Shipkia_Logger
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * WC_Logger instance
     */
    private $logger;

    /**
     * Protected keys that must be masked
     */
    private $sensitive_keys = array(
        'consumer_secret',
        'webhook_secret',
        'plugin_access_token',
        'plugin_refresh_token',
        'access_token',
        'refresh_token',
        'Authorization',
        'password',
        'secret',
        'signature'
    );

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        if (function_exists('wc_get_logger')) {
            $this->logger = wc_get_logger();
        }
    }

    /**
     * Log a message
     * 
     * @param string $message Log message
     * @param string $level Log level (info, warning, error, debug, critical)
     * @param array $context Context data
     * @param string $source Log source (default: shipkia-connection)
     */
    public function log($message, $level = 'info', $context = array(), $source = 'shipkia-connection')
    {
        if (!$this->logger) {
            return;
        }

        // Check if debug mode is enabled for debug logs
        if ($level === 'debug' && !$this->is_debug_enabled()) {
            return;
        }

        // Format context
        $context_str = '';
        if (!empty($context)) {
            // Mask sensitive data
            $safe_context = $this->mask_context($context);
            $context_str = ' | Context: ' . json_encode($safe_context);
        }

        // Add standard context
        $domain = $this->get_domain();
        $full_message = "[{$domain}] {$message}{$context_str}";

        // Log to WooCommerce
        $this->logger->log($level, $full_message, array('source' => $source));
    }

    /**
     * Check if debug mode is enabled
     */
    private function is_debug_enabled()
    {
        if (defined('SHIPKIA_DEBUG') && SHIPKIA_DEBUG) {
            return true;
        }
        
        $debug_option = get_option('shipkia_debug_mode');
        return $debug_option === 'yes' || $debug_option === true;
    }

    /**
     * Mask sensitive data in context
     */
    private function mask_context($context)
    {
        if (!is_array($context)) {
            return $context;
        }

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->mask_context($value);
            } elseif (in_array($key, $this->sensitive_keys, true)) {
                $context[$key] = '***' . substr(strval($value), -4);
            }
        }

        return $context;
    }

    /**
     * Get store domain for context
     */
    private function get_domain()
    {
        return parse_url(get_site_url(), PHP_URL_HOST);
    }
    
    /**
     * Static helper for easy access
     */
    public static function add($message, $level = 'info', $context = array(), $source = 'shipkia-connection')
    {
        self::get_instance()->log($message, $level, $context, $source);
    }
}
