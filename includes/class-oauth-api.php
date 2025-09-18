<?php
/**
 * OAuth API handler for PKL REST API Auth (Disabled)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PKL_REST_API_Auth_OAuth_API {

    /**
     * Database handler
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct($database) {
        $this->database = $database;
    }

    /**
     * Initialize - OAuth endpoints are disabled
     */
    public function init() {
        // OAuth endpoints are disabled for security reasons
        // Users should generate API keys through their profile page instead
    }
}