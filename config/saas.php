<?php
/**
 * SaaS Platform Configuration
 * Used by admin-platform and partners
 *
 * Delegates to config/database.php for DB connection and env loading.
 */

require_once __DIR__ . '/database.php';

// Referral/Agent Program - Global defaults
if (!defined('DEFAULT_COMMISSION_RATE')) define('DEFAULT_COMMISSION_RATE', 0.15);
if (!defined('DEFAULT_AGENT_PROVISION_RATE')) define('DEFAULT_AGENT_PROVISION_RATE', 0.01);
if (!defined('AGENT_PROVISION_MONTHS')) define('AGENT_PROVISION_MONTHS', 12);
