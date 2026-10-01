const slides = document.querySelectorAll('.hero-slide');
const nextBtn = document.getElementById('next-slide');
const prevBtn = document.getElementById('prev-slide');
const slideNum = document.getElementById('slide-number');
const heroTitle = document.getElementById('hero-title');

let index = 0;

// const content = [
//     { title: "Meet your next<br>sunny escape.", desc: "Mediterranean Coast, Italy" },
//     { title: "Find peace in<br>the deep blue.", desc: "Preikestolen, Norway" },
//     { title: "Walk along the<br>golden sands.", desc: "Milos, Greece" },
//     { title: "Discover the<br>misty highlands.", desc: "Isle of Skye, Scotland" }
// ];

function updateCarousel() {
    // Update Slides
    slides.forEach(slide => slide.classList.remove('active'));
    slides[index].classList.add('active');

    // Update Text
    heroTitle.innerHTML = content[index].title;
    slideNum.innerText = `0${index + 1} / 03`;
}

nextBtn.addEventListener('click', () => {
    index = (index + 1) % slides.length;
    updateCarousel();
});

prevBtn.addEventListener('click', () => {
    index = (index - 1 + slides.length) % slides.length;
    updateCarousel();
});

// Auto-play every 5 seconds
setInterval(() => {
    index = (index + 1) % slides.length;
    updateCarousel();
}, 5000);
