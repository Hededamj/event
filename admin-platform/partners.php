<?php
/**
 * Redirect: partners.php → vendors.php
 * The partners table was replaced by vendors in migration 015.
 */
header('Location: vendors.php', true, 301);
exit;
