<?php
session_start();
require_once __DIR__ . '/../../assets/api/config/database.php';

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
$action = $_POST['action'] ?? 'create';

// ---------- Delete a reminder ----------
if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    // Only delete a reminder that belongs to this user.
    $stmt = $pdo->prepare("DELETE FROM user_reminders WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Reminder not found.']);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'Reminder deleted.']);
    exit;
}

// ---------- Create a reminder ----------
$category = strtolower(trim($_POST['category'] ?? 'custom'));
$allowedCategories = ['flight', 'accommodation', 'activity', 'visa', 'payment', 'insurance', 'packing', 'transport', 'custom'];
if (!in_array($category, $allowedCategories, true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please select a valid reminder category.'
    ]);
    exit;
}

// Preserve compatibility with the existing reminder system.
$type = $category === 'visa' ? 'visa' : 'custom';
$title = trim($_POST['title'] ?? '');
$body = trim($_POST['body'] ?? '');
$date = trim($_POST['remind_on'] ?? '');
$tripId = (int) ($_POST['trip_id'] ?? 0);

// The title is required.
if ($title === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a title.']);
    exit;
}

// Keep the text within the column sizes.
if (mb_strlen($title) > 150 || mb_strlen($body) > 500) {
    echo json_encode(['status' => 'error', 'message' => 'Title or notes are too long.']);
    exit;
}

// The date must be a real date (YYYY-MM-DD).
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    echo json_encode(['status' => 'error', 'message' => 'Please choose a valid date.']);
    exit;
}

// The date must be today or later.
if ($date < date('Y-m-d')) {
    echo json_encode(['status' => 'error', 'message' => 'Please choose today or a future date.']);
    exit;
}

// If a trip was picked, make sure it belongs to this user.
if ($tripId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM trips WHERE id = ? AND user_id = ?");
    $stmt->execute([$tripId, $userId]);
    if (!$stmt->fetch()) {
        $tripId = 0; // Not theirs, so ignore it.
    }
}
$tripId = $tripId > 0 ? $tripId : null;

// Save the reminder.
$stmt = $pdo->prepare("
    INSERT INTO user_reminders (user_id, trip_id, reminder_type, category, title, body, remind_on)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([$userId, $tripId, $type, $category, $title, ($body === '' ? null : $body), $date]);

echo json_encode(['status' => 'success', 'message' => 'Reminder added.']);
