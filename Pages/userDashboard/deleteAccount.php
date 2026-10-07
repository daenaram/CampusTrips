<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}   
?>

<!DOCTYPE html>

<html>
    <head>
        <script src="/AUT-Web-Based-Travel-Planner/assets/js/pwa.js"></script>
        <meta charset="UTF-8">
        <title>Delete Account</title>
        <link rel="stylesheet" href="../../assets/css/deleteAccount.css">
    </head>

    <body>
        <div class="warning">!</div>
        <h1>Delete Account</h1>
        <!-- Display confirmation message and options -->
        <p>Are you sure you want to delete your account?</p><p> This action cannot be undone.</p>

        <div id="alertBox" class="alert"></div>
        <!-- Form to confirm account deletion -->
        <form method="POST" action="deleteAccountConfirm.php" onsubmit="return validateConfirm()">
            <label for="confirmInput">
                Type <span class="keyword">DELETE</span> to confirm
            </label>
            <input type="text" id="confirmInput" name="confirmInput" required placeholder="DELETE" autocomplete="off" spellcheck="false">
            <p class="hint">This field is case-sensitive.</p>
            <div class="button-group">
    <button type="submit" name="confirm_delete" id="deleteBtn" value="yes" class="deleteBtn" disabled>
        Delete My Account
    </button>

    <a href="Dashboard.php" class="cancelBtn">
        Cancel
    </a>
</div>
        </form>

        <script>
            // Enable the delete button only when the user types "DELETE"
            const confirmInput = document.getElementById('confirmInput');
            const deleteBtn = document.getElementById('deleteBtn');

            confirmInput.addEventListener('input', function () {
                deleteBtn.disabled = confirmInput.value !== 'DELETE';
            });

            function validateConfirm() {
                if (confirmInput.value !== 'DELETE') {
                    alert('Please type "DELETE" to confirm account deletion.');
                    return false;
                }
                return true;
            }

            //show error message
            const errorMap = {
                'empty_input': 'Please type "DELETE" to confirm account deletion.',
                'invalid_input': 'Incorrect confirmation input. Please type "DELETE" to confirm.',
                'deletion_failed': 'An error occurred while deleting your account. Please try again later.'
            };

            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            if (error && errorMap[error]) {
                const alertBox = document.getElementById('alertBox');
                alertBox.textContent = errorMap[error];
                alertBox.classList.add ('error', 'show');
            }
        </script>
    </body>         



</html>