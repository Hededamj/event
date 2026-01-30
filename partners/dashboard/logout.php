<?php
/**
 * Partner Dashboard - Logout
 */

require_once __DIR__ . '/../../includes/partner-auth.php';

logoutPartner();

redirect(BASE_PATH . '/partners/');
