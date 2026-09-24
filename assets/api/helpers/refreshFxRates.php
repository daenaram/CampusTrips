<?php
/**
 * This is to run every 12 hours
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/currencyHelper.php';

$success = refreshAllFxRates($pdo);
echo $success ? "FX rates refreshed successfully." : "Failed to refresh FX rates.";

?>
