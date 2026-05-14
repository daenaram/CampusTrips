// Close modal function
function closeModal() {
    document.getElementById('error-modal').style.display = 'none';
    window.history.replaceState({}, document.title, window.location.pathname);
}

// Close modal when clicking outside the modal box
document.getElementById('error-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Error messages configuration
const errorMessages = {
    'all_fields_required': { title: 'Missing Fields', message: 'Please enter both email and password.' },
    'invalid_email': { title: 'Invalid Email', message: 'Please enter a valid email address.' },
    'invalid_credentials': { title: 'Login Failed', message: 'Incorrect email or password. Please try again.' },
    'account_locked': { title: 'Account Locked', message: 'Your account is temporarily locked due to multiple failed login attempts. Please try again later.' },
    'server_error': { title: 'Server Error', message: 'Something went wrong on our end. Please try again later.' }
};

// Display error message
function displayError(errorType) {
    const errorInfo = errorMessages[errorType];
    if (errorInfo) {
        document.getElementById('error-modal').style.display = 'flex';
        document.querySelector('.modal-box h3').textContent = errorInfo.title;
        document.querySelector('.modal-box p').textContent = errorInfo.message;
    }
}

// Check for error in URL and display
const error = new URLSearchParams(window.location.search).get('error');
if (error) displayError(error);