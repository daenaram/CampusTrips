// Help Desk Page JavaScript

// ---------- Hamburger menu behaviour ----------
const menuToggle = document.getElementById('menuToggle');
const menuPanel = document.getElementById('menuPanel');
const menuBackdrop = document.getElementById('menuBackdrop');

function openMenu() {
    menuToggle.classList.add('open');
    menuToggle.setAttribute('aria-expanded', 'true');
    menuToggle.setAttribute('aria-label', 'Close menu');
    menuPanel.classList.add('open');
    menuPanel.setAttribute('aria-hidden', 'false');
    menuBackdrop.classList.add('visible');
}

function closeMenu() {
    menuToggle.classList.remove('open');
    menuToggle.setAttribute('aria-expanded', 'false');
    menuToggle.setAttribute('aria-label', 'Open menu');
    menuPanel.classList.remove('open');
    menuPanel.setAttribute('aria-hidden', 'true');
    menuBackdrop.classList.remove('visible');
}

menuToggle.addEventListener('click', function () {
    menuPanel.classList.contains('open') ? closeMenu() : openMenu();
});

menuBackdrop.addEventListener('click', closeMenu);

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeMenu();
    }
});
