<?php
/**
 * Entry point for a shared trip link (?token=...).
 *
 * Shared trips no longer render on their own page — they show up inside
 * userDashboard/Dashboard.php itself, tagged "Shared by <owner>", so a
 * visitor sees the exact same view as everyone else's trips. This file's
 * only job now is: send an anonymous visitor to sign in first (carrying the
 * token through so they land back here after logging in), then hand off to
 * the dashboard, which does the actual token validation, access grant, and
 * "trip no longer exists / link disabled / trip made private" handling.
 *
 * This keeps old-style ".../sharedTrip.php?token=..." links people may
 * already have working, without a second copy of that logic to keep in
 * sync with Dashboard.php's.
 */

session_start();

$token = trim($_GET['token'] ?? '');
$dashboardTarget = '/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard.php?shared_token=' . urlencode($token);

if (!isset($_SESSION['user_id'])) {
    $loginUrl = '/AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html?redirect=' . urlencode($dashboardTarget);
    header('Location: ' . $loginUrl);
    exit();
}

header('Location: ' . $dashboardTarget);
exit();
