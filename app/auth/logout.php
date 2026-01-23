<?php
/**
 * Account Logout
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth-account.php';

accountLogout();
redirect('/app/auth/login.php?logged_out=1');
