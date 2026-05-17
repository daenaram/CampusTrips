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
    'password_mismatch': { title: 'Password Mismatch', message: 'The passwords you entered do not match. Please try again.' },
    'email_taken': { title: 'Email Already Registered', message: 'This email is already associated with an account. Please use a different email or log in instead.' },
    'username_taken': { title: 'Username Already Taken', message: 'This username is already in use. Please choose a different one.' },
    'password_requirements': { title: 'Password Requirements Not Met', message: 'Your password must meet all password requirements.' },
    'all_fields_required': { title: 'Missing Fields', message: 'Please fill in all required fields before submitting.' },
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


const passwordInput = document.querySelector('input[name="password"]');

const lengthReq = document.getElementById("length");
const uppercaseReq = document.getElementById("uppercase");
const numberReq = document.getElementById("number");
const symbolReq = document.getElementById("symbol");

if (passwordInput) {
passwordInput.addEventListener("input", function () {
    const value = passwordInput.value;

    if (value.length >= 8) {
        lengthReq.classList.add("valid");
    } else {
        lengthReq.classList.remove("valid");
    }

    if (/[A-Z]/.test(value)) {
        uppercaseReq.classList.add("valid");
    } else {
        uppercaseReq.classList.remove("valid");
    }

    if (/\d/.test(value)) {
        numberReq.classList.add("valid");
    } else {
        numberReq.classList.remove("valid");
    }

    if (/[\W_]/.test(value)) {
        symbolReq.classList.add("valid");
    } else {
        symbolReq.classList.remove("valid");
    }   
})
}