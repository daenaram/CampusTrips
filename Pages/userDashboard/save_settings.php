<?php
session_start();
require_once __DIR__ . '/../../assets/api/config/database.php';
require_once __DIR__ . '/../../assets/api/helpers/currencyHelper.php';
require_once __DIR__ . '/../../assets/api/helpers/notificationPreferenceHelper.php';

// We always reply with JSON.
header('Content-Type: application/json');

// Must be signed in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not signed in.']);
    exit;
}

// Must be a POST request.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// --- Currency ---
if (array_key_exists('currency', $_POST)) {
    $selectedCurrency = $_POST['currency'];

    if (isCurrencySupported($selectedCurrency)) {
        $_SESSION['currency'] = $selectedCurrency;

        // Also remember it on the user's account.
        $stmt = $pdo->prepare("UPDATE users SET currency_preference = ? WHERE id = ?");
        $stmt->execute([$selectedCurrency, $userId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid currency selected.']);
        exit;
    }
}

// --- Notification preferences ---
if (array_key_exists('notifications', $_POST)) {
    // The browser sends this as a JSON string, so turn it back into an array.
    $input = json_decode($_POST['notifications'], true);

    if (!is_array($input)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid notification preferences.']);
        exit;
    }

    // Make sure every value is a real true/false.
    $clean = [];
    foreach ($input as $type => $enabled) {
        $clean[$type] = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    }

    saveNotificationPreferences($pdo, $userId, $clean);
}

echo json_encode(['status' => 'success', 'message' => 'Settings saved.']);
