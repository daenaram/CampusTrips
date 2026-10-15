<?php

date_default_timezone_set('Pacific/Auckland');

/**Checking if the account is locked out before allowing login attempts**/
function isAccountLocked(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare("
        SELECT locked_out
        FROM users
        WHERE email = ?
    ");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['locked_out'])) {
        return false;
    }

    $lockTime = strtotime($user['locked_out']);
    $currentTime = time();

    if ($currentTime >= $lockTime) {
        $clear = $pdo->prepare("
            UPDATE users
            SET locked_out = NULL,
                failed_attempts = 0
            WHERE email = ?
        ");

        $clear->execute([$email]);

        //when user is no longer locked out
        return false;
    }

    //user is still locked out
    return true;
}

/**Records failed login attempts
 * account locks after 3 failed attempts for 15 minutes
 * failed attempts reset to 0
 * email is sent to the user when their account is locked out
 * **/

function recordFailedLogin(PDO $pdo, string $email): void
{


    //get current failed attempts
    $stmt = $pdo->prepare("
        SELECT name, failed_attempts
        FROM users
        WHERE email = ?
    ");
    $stmt->execute([$email]);

    //fetching user information
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    //if user doesn't exist, just return without doing anything
    if (!$user) {
        return;
    }

    //Increasing the failed attempts count
    $attempts = (int) $user['failed_attempts'] + 1;

    //lock account after 3 attempts
    if ($attempts >= 3) {

        $lockUntil = date("Y-m-d H:i:s");
        $lockUntil = date("Y-m-d H:i:s", strtotime($lockUntil . " +15 minutes"));

        //update database to lock the account
        $stmt = $pdo->prepare("
            UPDATE users
            SET failed_attempts = 0,
                locked_out = ?
            WHERE email = ?
        ");

        $result = $stmt->execute([$lockUntil, $email]);

        // DEBUG: Check if the query actually worked
        if (!$result) {
            error_log("Failed to update lockout for: " . $email);
        }

        sendLockOutEmail($email, $user['name'] ?? 'user', $lockUntil);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE email = ?");
        $stmt->execute([$attempts, $email]);
    }
}

//resetting failed login attempts after successful login
function resetFailedLogin(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare("
        UPDATE users
        SET failed_attempts = 0,
            locked_out = NULL
        WHERE email = ?
    ");
    $stmt->execute([$email]);
}

//sending email to user when their account is locked out
function sendLockOutEmail(string $email, string $name, string $lockUntil): void
{
    $displayName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $lockTime = date("g:i A, j F Y", strtotime($lockUntil));
    $subject = "Security Alert: CampusTrip Account Locked Due to Multiple Failed Login Attempts";
    $message = "
    <!DOCTYPE html>
    <html lang='en'>
    <head><meta charset='UTF-8'></head>
    <body style='font-family:sans-serif;color:#333;max-width:560px;margin:auto;padding:24px'>
        <h2 style='color:#c0392b'> Account Temporarily Locked</h2>
        <p>Hi {$displayName},</p>
        <p>
            We noticed <strong>3 consecutive failed login attempts</strong> on your
            CampusTrip account. To protect your account, we have
            temporarily locked it until <strong>{$lockTime}</strong>.
        </p>
        <p>
            <strong>If this was you</strong>, please wait until the lock expires and
            try again, or <a href='/AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/forgot_password.html'>
            reset your password</a> immediately.
        </p>
        <p>
            <strong>If this was NOT you</strong>, your account may be under attack.
            We strongly recommend resetting your password straight away using the
            link below.
        </p>
        <p style='margin:24px 0'>
            <a href='/AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/forgot_password.html'
               style='background:#c0392b;color:#fff;padding:12px 24px;border-radius:6px;
                      text-decoration:none;font-weight:bold;display:inline-block'>
                Reset My Password
            </a>
        </p>
        <p style='font-size:.85rem;color:#888'>
            This is an automated security notification from CampusTrip.
            Please do not reply to this email.
        </p>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: CampusTrip <noreply@campustrip.com>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    @mail($email, $subject, $message, $headers);
    error_log("Lockout email sent to {$email} for user {$displayName} until {$lockTime}");
}

?>