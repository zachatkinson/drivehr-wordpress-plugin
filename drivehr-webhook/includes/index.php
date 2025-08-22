<?php
/**
 * Silence is golden.
 * 
 * This file prevents directory browsing and unauthorized access
 * to the includes directory.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}