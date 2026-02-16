<?php
/**
 * Vendor Logout
 */
require_once __DIR__ . '/../includes/vendor-auth.php';
require_once __DIR__ . '/../../includes/functions.php';

vendorLogout();
setFlash('success', 'Du er nu logget ud.');
redirect('/subcontractor/dashboard/login.php');
