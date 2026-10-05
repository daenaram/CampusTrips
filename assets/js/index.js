const slides = document.querySelectorAll('.hero-slide');
const nextBtn = document.getElementById('next-slide');
const prevBtn = document.getElementById('prev-slide');
const slideNum = document.getElementById('slide-number');

let index = 0;

function updateCarousel() {
   // Update Slides 
    slides.forEach(slide => slide.classList.remove('active'));
    slides[index].classList.add('active');

    // Update Slide Number only (e.g., 01 / 03)
    slideNum.innerText = `0${index + 1} / 0${slides.length}`;
}

// Event Listeners
nextBtn.addEventListener('click', () => {
    index = (index + 1) % slides.length;
    updateCarousel();
    resetTimer();
});

prevBtn.addEventListener('click', () => {
    index = (index - 1 + slides.length) % slides.length;
    updateCarousel();
    resetTimer();
});

// Auto-play every 5 seconds
let autoPlay = setInterval(() => {
    index = (index + 1) % slides.length;
    updateCarousel();
}, 5000);

function resetTimer() {
    clearInterval(autoPlay);
    autoPlay = setInterval(() => {
        index = (index + 1) % slides.length;
        updateCarousel();
    }, 5000);
}
