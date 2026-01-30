<?php
/**
 * Platform Admin Logout
 */

require_once __DIR__ . '/../includes/admin-platform-auth.php';

logoutPlatformAdmin();

redirect(BASE_PATH . '/admin-platform/login.php');
