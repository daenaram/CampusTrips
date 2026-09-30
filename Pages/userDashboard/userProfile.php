<?php
// Start session to access user data
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

require_once __DIR__ . '/../../assets/api/config/database.php';

$profilePictureError = '';
$profilePictureSuccess = '';
$usernameError = '';
$usernameSuccess = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['remove_profile_picture'])
) {

    try {

        // Find the current picture first.
        $pictureStmt = $pdo->prepare("
            SELECT profile_picture
            FROM users
            WHERE id = ?
        ");

        $pictureStmt->execute([
            $_SESSION['user_id']
        ]);

        $currentPicture =
            $pictureStmt->fetchColumn();


        // Remove custom uploaded file.
        // Do NOT delete preset avatars.
        if (
            $currentPicture &&
            str_starts_with(
                $currentPicture,
                'uploads/profile_pictures/'
            )
        ) {

            $filePath =
                __DIR__ .
                '/../../assets/' .
                $currentPicture;

            if (is_file($filePath)) {
                unlink($filePath);
            }
        }


        $removeStmt = $pdo->prepare("
            UPDATE users
            SET profile_picture = NULL
            WHERE id = ?
        ");

        $removeStmt->execute([
            $_SESSION['user_id']
        ]);

        header('Location: userProfile.php?picture=removed');
        exit();

    } catch (Throwable $e) {

        error_log(
            'Profile picture removal error: ' .
            $e->getMessage()
        );

        $profilePictureError =
            'Unable to remove profile picture.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_picture'])) {

    $selectedAvatar = trim($_POST['selected_avatar'] ?? '');

    $allowedAvatars = [
        'avatar1.png',
        'avatar2.png',
        'avatar3.png',
        'avatar4.png',
        'avatar5.png',
        'avatar6.png'
    ];

    try {

        /*
         * OPTION 1:
         * User uploaded their own image.
         */
        if (
            isset($_FILES['profile_upload']) &&
            $_FILES['profile_upload']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['profile_upload']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('The image could not be uploaded.');
            }

            // Maximum 5 MB
            if ($_FILES['profile_upload']['size'] > 5 * 1024 * 1024) {
                throw new Exception('Profile picture must be 5 MB or smaller.');
            }

            $tempFile = $_FILES['profile_upload']['tmp_name'];

            // Verify the actual file type.
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tempFile);

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($allowedTypes[$mimeType])) {
                throw new Exception(
                    'Only JPG, PNG and WEBP images are allowed.'
                );
            }

            $extension = $allowedTypes[$mimeType];

            // Generate our own filename.
            $newFilename =
                'user_' .
                $_SESSION['user_id'] .
                '_' .
                bin2hex(random_bytes(8)) .
                '.' .
                $extension;

            $uploadDirectory =
                __DIR__ .
                '/../../assets/uploads/profile_pictures/';

            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }

            $destination =
                $uploadDirectory . $newFilename;

            if (!move_uploaded_file($tempFile, $destination)) {
                throw new Exception('Unable to save the uploaded image.');
            }

            $profilePicture =
                'uploads/profile_pictures/' . $newFilename;


        /*
         * OPTION 2:
         * User selected a preset avatar.
         */
        } elseif (
            $selectedAvatar !== '' &&
            in_array($selectedAvatar, $allowedAvatars, true)
        ) {

            $profilePicture =
                'images/avatars/' . $selectedAvatar;

        } else {

            throw new Exception(
                'Please choose an avatar or upload a picture.'
            );
        }


        $updateStmt = $pdo->prepare("
            UPDATE users
            SET profile_picture = ?
            WHERE id = ?
        ");

        $updateStmt->execute([
            $profilePicture,
            $_SESSION['user_id']
        ]);

        header('Location: userProfile.php?picture=updated');
        exit();

    } catch (Throwable $e) {

        error_log(
            'Profile picture update error: ' .
            $e->getMessage()
        );

        $profilePictureError = $e->getMessage();
    }
}

if (isset($_GET['picture'])) {

    if ($_GET['picture'] === 'updated') {
        $profilePictureSuccess =
            'Profile picture updated successfully.';
    }

    elseif ($_GET['picture'] === 'removed') {
        $profilePictureSuccess =
            'Profile picture removed successfully.';
    }
}

if (
    isset($_GET['username']) &&
    $_GET['username'] === 'updated'
) {

    $usernameSuccess =
        'Username updated successfully.';
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['change_username'])
) {

    $newUsername =
        trim($_POST['new_username'] ?? '');


    /*
     * VALIDATION 1:
     * Empty username
     */

    if ($newUsername === '') {

        $usernameError =
            'Please enter a new username.';
    }


    /*
     * VALIDATION 2:
     * Length
     */

    elseif (
        strlen($newUsername) < 3 ||
        strlen($newUsername) > 30
    ) {

        $usernameError =
            'Username must be between 3 and 30 characters.';
    }


    /*
     * VALIDATION 3:
     * Allowed characters
     */

    elseif (
        !preg_match(
            '/^[A-Za-z0-9 _-]+$/',
            $newUsername
        )
    ) {

        $usernameError =
            'Username can only contain letters, numbers, spaces, underscores and hyphens.';
    }

    else {

        try {

            /*
             * Check whether another account
             * already uses this username.
             */

            $checkStmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE LOWER(name) = LOWER(?)
                AND id != ?
                LIMIT 1
            ");

            $checkStmt->execute([
                $newUsername,
                $_SESSION['user_id']
            ]);


            if ($checkStmt->fetch()) {

                $usernameError =
                    'That username is already in use.';
            }

            else {

                /*
                 * Update username.
                 */

                $updateUsernameStmt = $pdo->prepare("
                    UPDATE users
                    SET name = ?
                    WHERE id = ?
                ");

                $updateUsernameStmt->execute([
                    $newUsername,
                    $_SESSION['user_id']
                ]);


                /*
                 * Redirect prevents another update
                 * if the page is refreshed.
                 */

                header(
                    'Location: userProfile.php?username=updated'
                );

                exit();
            }

        }

        catch (PDOException $e) {

            error_log(
                'Username update error: ' .
                $e->getMessage()
            );

            $usernameError =
                'Unable to update username. Please try again.';
        }
    }
}

$stmt = $pdo->prepare("SELECT name, email, created_at, profile_picture FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User profile not found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="../../assets/css/loginformStyles.css">

    <style>
        .profile-topbar {
            display: flex;
            align-items: center;
            gap: 16px;
            justify-content: flex-start;
        }
        .back-to-dashboard-btn {
            background: none;
            border: 1px solid #0B2545;
            color: #0B2545;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
        }
        .back-to-dashboard-btn:hover {
            background: #0B2545;
            color: #fff;
        }
        .profile-topbar h1 {
            margin: 0;
        }
    </style>
</head>
<body>

    <div class="profile-page">
        <!-- Header row: Back to Dashboard on the left, title next to it -->
        <div class="profile-topbar">
            <button type="button" class="back-to-dashboard-btn" onclick="location.href='Dashboard.php'">
                ← Back to Dashboard
            </button>
            <h1>Campus Trip</h1>
        </div>
        <hr>

        <!-- Profile Picture -->
        <div class="profile-header">
            <div class="profile-avatar">

                <?php if (!empty($user['profile_picture'])): ?>

                    <img
                        src="../../assets/<?php
                            echo htmlspecialchars($user['profile_picture']);
                        ?>"
                        alt="Profile Picture"
                        class="profile-avatar-image"
                    >

                <?php else: ?>

                    <div class="avatar-head"></div>
                    <div class="avatar-body"></div>

                <?php endif; ?>

            </div>

            <button
                type="button"
                class="profile-picture-btn"
                id="open-profile-picture-modal">
                Update Profile Picture
            </button>
            <?php if ($profilePictureError !== ''): ?>
                <p class="profile-picture-message error">
                    <?php echo htmlspecialchars($profilePictureError); ?>
                </p>
            <?php endif; ?>

            <?php if ($profilePictureSuccess !== ''): ?>
                <p class="profile-picture-message success">
                    <?php echo htmlspecialchars($profilePictureSuccess); ?>
                </p>
            <?php endif; ?>
        </div>

        

        <div
            class="profile-picture-modal"
            id="profile-picture-modal">

            <div class="profile-picture-modal-card">

                <button
                    type="button"
                    class="profile-modal-close"
                    id="close-profile-picture-modal">
                    &times;
                </button>

                <h2>Update Profile Picture</h2>

                <form
                    method="POST"
                    enctype="multipart/form-data"
                    id="profile-picture-form">

                    <h3>Choose an Avatar</h3>

                    <div class="avatar-options">

                        <?php for ($i = 1; $i <= 6; $i++): ?>

                            <label class="avatar-option">

                                <input
                                    type="radio"
                                    name="selected_avatar"
                                    value="avatar<?php echo $i; ?>.png">

                                <img
                                    src="../../assets/images/avatars/avatar<?php echo $i; ?>.png"
                                    alt="Avatar <?php echo $i; ?>">

                            </label>

                        <?php endfor; ?>

                    </div>

                    <div class="profile-picture-divider">
                        <span>OR</span>
                    </div>

                    <h3>Upload Your Own Picture</h3>

                    <input
                        type="file"
                        name="profile_upload"
                        id="profile-upload"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                    <p class="profile-upload-help">
                        JPG, PNG or WEBP. Maximum file size: 5 MB.
                    </p>

                    <div
                        class="upload-preview-container"
                        id="upload-preview-container">

                        <img
                            id="upload-preview"
                            alt="Selected image preview">

                    </div>

                    <div class="profile-modal-actions">

                    <button
                        type="button"
                        class="profile-modal-cancel"
                        id="cancel-profile-picture">
                        Cancel
                    </button>

                    <button
                        type="submit"
                        name="update_profile_picture"
                        class="profile-modal-save">
                        Save Picture
                    </button>

                </div>

                <?php if (!empty($user['profile_picture'])): ?>
                    <button
                        type="submit"
                        name="remove_profile_picture"
                        class="remove-profile-picture-btn"
                        formnovalidate>
                        Remove Current Picture
                    </button>
                <?php endif; ?>

                </form>

            </div>

        </div>

        <div
            class="username-modal"
            id="username-modal">

            <div class="username-modal-card">

                <button
                    type="button"
                    class="username-modal-close"
                    id="close-username-modal">
                    &times;
                </button>

                <h2>Change Username</h2>

                <form
                    method="POST"
                    id="username-form">

                    <div class="username-field">

                        <label>
                            Current Username
                        </label>

                        <div class="current-username">
                            <?php echo htmlspecialchars($user['name']); ?>
                        </div>

                    </div>

                    <div class="username-field">

                        <label for="new-username">
                            New Username
                        </label>

                        <input
                            type="text"
                            id="new-username"
                            name="new_username"
                            maxlength="30"
                            placeholder="Enter new username"
                            autocomplete="off"
                            required>

                    </div>

                    <div class="username-requirements">
                        <p>Username requirements:</p>

                        <ul>
                            <li>3–30 characters</li>
                            <li>Letters and numbers are allowed</li>
                            <li>Spaces, underscores (_) and hyphens (-) are allowed</li>
                        </ul>
                    </div>

                    <div
                        class="username-form-error"
                        id="username-form-error">
                    </div>

                    <div class="username-modal-actions">

                        <button
                            type="button"
                            class="username-cancel-btn"
                            id="cancel-username-modal">
                            Cancel
                        </button>

                        <button
                            type="submit"
                            name="change_username"
                            class="username-save-btn">
                            Save Username
                        </button>

                    </div>

                </form>

            </div>

        </div>

        <?php if ($usernameError !== ''): ?>

            <p class="username-message error">

                <?php
                echo htmlspecialchars(
                    $usernameError
                );
                ?>

            </p>

        <?php endif; ?>


        <?php if ($usernameSuccess !== ''): ?>

            <p class="username-message success">

                <?php
                echo htmlspecialchars(
                    $usernameSuccess
                );
                ?>

            </p>

        <?php endif; ?>

        <!-- Profile Info -->
        <div class="profile-info">
            <h2>Profile Information</h2>

            <div class="profile-grid">
                <label>Name</label>
                <input type="text" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>

                <label>Email</label>
                <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
            </div>
            <div class="profile-buttons">
                <button type="button" id="open-username-modal">Change Username</button>
                <button type="button" onclick="location.href='../UserAuthentication/change_password.php'">Change Password</button>
                <button type="button" onclick="location.href='deleteAccount.php'">Delete Account</button>
                <button type="button" onclick="location.href='/AUT-Web-Based-Travel-Planner/assets/api/auth/signout.php'">Sign Out</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const modal =
            document.getElementById('profile-picture-modal');

        const openButton =
            document.getElementById('open-profile-picture-modal');

        const closeButton =
            document.getElementById('close-profile-picture-modal');

        const cancelButton =
            document.getElementById('cancel-profile-picture');

        const uploadInput =
            document.getElementById('profile-upload');

        const previewContainer =
            document.getElementById('upload-preview-container');

        const preview =
            document.getElementById('upload-preview');

        const avatarInputs =
            document.querySelectorAll(
                'input[name="selected_avatar"]'
            );
        
        const usernameModal =
            document.getElementById('username-modal');

        const openUsernameButton =
            document.getElementById('open-username-modal');

        const closeUsernameButton =
            document.getElementById('close-username-modal');

        const cancelUsernameButton =
            document.getElementById('cancel-username-modal');

        const newUsernameInput =
            document.getElementById('new-username');

        const usernameForm =
            document.getElementById('username-form');

        const usernameFormError =
            document.getElementById('username-form-error');


        function openModal() {
            modal.classList.add('show');
        }


        function closeModal() {
            modal.classList.remove('show');
        }

        function openUsernameModal() {

            usernameModal.classList.add('show');

            usernameFormError.textContent = '';
            usernameFormError.classList.remove('show');

            setTimeout(function () {
                newUsernameInput.focus();
            }, 100);
        }


        function closeUsernameModal() {

            usernameModal.classList.remove('show');

            usernameForm.reset();

            usernameFormError.textContent = '';
            usernameFormError.classList.remove('show');
        }


        openButton.addEventListener('click', openModal);

        closeButton.addEventListener('click', closeModal);

        cancelButton.addEventListener('click', closeModal);

        openUsernameButton.addEventListener(
            'click',
            openUsernameModal
        );

        closeUsernameButton.addEventListener(
            'click',
            closeUsernameModal
        );

        cancelUsernameButton.addEventListener(
            'click',
            closeUsernameModal
        );

        usernameModal.addEventListener('click', function (event) {

            if (event.target === usernameModal) {
                closeUsernameModal();
            }

        });

        usernameForm.addEventListener('submit', function (event) {

            const username =
                newUsernameInput.value.trim();


            usernameFormError.textContent = '';
            usernameFormError.classList.remove('show');


            /* Empty */

            if (!username) {

                event.preventDefault();

                usernameFormError.textContent =
                    'Please enter a new username.';

                usernameFormError.classList.add('show');

                return;
            }


            /* Length */

            if (
                username.length < 3 ||
                username.length > 30
            ) {

                event.preventDefault();

                usernameFormError.textContent =
                    'Username must be between 3 and 30 characters.';

                usernameFormError.classList.add('show');

                return;
            }


            /*
            * Only:
            * letters
            * numbers
            * spaces
            * underscore
            * hyphen
            */

            const usernamePattern =
                /^[A-Za-z0-9 _-]+$/;


            if (!usernamePattern.test(username)) {

                event.preventDefault();

                usernameFormError.textContent =
                    'Username can only contain letters, numbers, spaces, underscores and hyphens.';

                usernameFormError.classList.add('show');

                return;
            }

        });


        // Clicking the dark background closes the modal.
        modal.addEventListener('click', function (event) {

            if (event.target === modal) {
                closeModal();
            }

        });


        // Show a preview of an uploaded image.
        uploadInput.addEventListener('change', function () {

            const file = this.files[0];

            if (!file) {
                previewContainer.classList.remove('show');
                preview.removeAttribute('src');
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {
                preview.src = event.target.result;
                previewContainer.classList.add('show');
            };

            reader.readAsDataURL(file);


            // Uploaded image takes priority, so clear preset selection.
            avatarInputs.forEach(function (input) {
                input.checked = false;
            });

        });


        // Selecting an avatar clears the uploaded file.
        avatarInputs.forEach(function (input) {

            input.addEventListener('change', function () {

                uploadInput.value = '';

                preview.removeAttribute('src');
                previewContainer.classList.remove('show');

            });

        });

    });
    </script>

</body>
</html>