<?php
/**
 * Silence is golden.
 * 
 * This file prevents directory browsing and unauthorized access
 * to the plugin directory structure.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}