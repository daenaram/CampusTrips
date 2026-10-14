<?php
session_start();
require_once __DIR__ . '../../../assets/api/helpers/currencyHelper.php';

// Check if the request is coming via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCurrency = $_POST['currency'] ?? 'NZD';

    // Validate that the currency is one we support
    if (isCurrencySupported($selectedCurrency)) {
        $_SESSION['currency'] = $selectedCurrency;
        
        echo json_encode(['status' => 'success', 'message' => 'Currency updated to ' . $selectedCurrency]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid currency selected.']);
    }
    exit;
}
