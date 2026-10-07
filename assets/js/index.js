// Carousel Logic
const slides = document.querySelectorAll('.hero-slide');
const nextBtn = document.getElementById('next-slide');
const prevBtn = document.getElementById('prev-slide');
const slideNum = document.getElementById('slide-number');
let index = 0;

function updateCarousel() {
    slides.forEach(slide => slide.classList.remove('active'));
    slides[index].classList.add('active');
    slideNum.innerText = `0${index + 1} / 0${slides.length}`;
}

nextBtn.addEventListener('click', () => {
    index = (index + 1) % slides.length;
    updateCarousel();
});

prevBtn.addEventListener('click', () => {
    index = (index - 1 + slides.length) % slides.length;
    updateCarousel();
});

// Auto-play carousel
setInterval(() => {
    index = (index + 1) % slides.length;
    updateCarousel();
}, 5000);

// Intersection Observer for Reveal Animations
const observerOptions = { threshold: 0.15 };
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('active');
        }
    });
}, observerOptions);

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// Initialize Lucide Icons
lucide.createIcons();
