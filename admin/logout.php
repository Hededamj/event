<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

logout();
redirect(BASE_PATH . '/index.php');
