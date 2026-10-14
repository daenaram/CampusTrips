//Firebase used to connect Google log in
import { initializeApp } from "https://www.gstatic.com/firebasejs/12.12.1/firebase-app.js";
import {
    getAuth,
    GoogleAuthProvider,
    signInWithPopup
} from "https://www.gstatic.com/firebasejs/12.12.1/firebase-auth.js";

const firebaseConfig = {
    apiKey: "AIzaSyDuHGWuk28wzN3BagOE4Et8xOXaedU8x5o",
    authDomain: "campus-trips.firebaseapp.com",
    projectId: "campus-trips",
    storageBucket: "campus-trips.firebasestorage.app",
    messagingSenderId: "684478580677",
    appId: "1:684478580677:web:019fe5dafe14baba066dac",
    measurementId: "G-W998Z5HQKM"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);



const dashboardPath = "/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard.php";
const sessionPath = "/AUT-Web-Based-Travel-Planner/assets/api/auth/firebaseSession.php";

// Show message
function showMessage(message) {
    const el = document.getElementById("loginMessage");
    if (el) el.textContent = message;
}

// Create PHP session (used by Apple & AUT)
function createPhpSession(name, email) {
    fetch(sessionPath, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            name: name,
            email: email
        })
    })
        .then(response => response.text())
        .then(data => {
            console.log("Session response:", data);

            if (data.trim() === "success") {
                window.location.href = dashboardPath;
            } else {
                showMessage("Session could not be created: " + data);
            }
        })
        .catch(error => {
            console.log(error);
            showMessage("Login session failed.");
        });
}

//Logging with Google using Firebase
window.loginWithGoogle = function () {
    const provider = new GoogleAuthProvider();

    signInWithPopup(auth, provider)
        .then((result) => {
            showMessage("Authenticating with server...");
            
            // Get the ID token from Firebase
            return result.user.getIdToken();
        })
        .then((idToken) => {
            // Send token to backend to create PHP session
            return fetch('/AUT-Web-Based-Travel-Planner/assets/api/auth/google-login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idToken: idToken,
                    // Same "come back here after login" target the password form posts
                    redirect: document.getElementById('redirectField')?.value || null
                })
            });
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Server authentication failed');
            }
            return response.json();
        })
        .then((data) => {
            if (data.success) {
                showMessage("Google login successful!");
                window.location.href = data.redirect;
            } else {
                showMessage("Google login failed: " + (data.error || "Unknown error"));
            }
        })
        .catch((error) => {
            console.log(error.code || error.message);

            if (
                error.code === "auth/popup-closed-by-user" ||
                error.code === "auth/cancelled-popup-request"
            ) {
                showMessage("Google login was cancelled. Please try again or use email and password.");
            }
            else if (error.code === "auth/network-request-failed") {
                showMessage("Google login is currently unavailable. Please try again later or use email and password.");
            }
            else {
                showMessage("Google login failed. Please try again later or use email and password.");
            }
        });
};

//Logging with Apple (demo)
window.loginWithApple = function () {
    const confirmLogin = confirm("Do you want to continue with Apple login demo?");

    if (!confirmLogin) {
        showMessage("Apple login was cancelled.");
        return;
    }

    showMessage("Apple login successful.");

    // IMPORTANT: create session first
    createPhpSession("Apple User", "apple_user@icloud.com");
};

//Logging with AUT (demo)
window.loginWithAUT = function () {
    const confirmLogin = confirm("Do you want to continue with AUT login demo?");

    if (!confirmLogin) {
        showMessage("AUT login was cancelled.");
        return;
    }

    showMessage("AUT login successful.");

    // IMPORTANT: create session first
    createPhpSession("AUT Student", "student@aut.ac.nz");
};