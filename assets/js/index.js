
document.addEventListener('DOMContentLoaded', () => {

   
    const slides = document.querySelectorAll('.hero-slide');
    const nextBtn = document.getElementById('next-slide');
    const prevBtn = document.getElementById('prev-slide');
    const slideNum = document.getElementById('slide-number');
    let index = 0;

    function updateCarousel() {
        if (slides.length === 0) return;

        slides.forEach(slide => slide.classList.remove('active'));
        slides[index].classList.add('active');

        if (slideNum) {
            slideNum.innerText = `0${index + 1} / 0${slides.length}`;
        }
    }

  
    if (nextBtn && prevBtn) {
        nextBtn.addEventListener('click', () => {
            index = (index + 1) % slides.length;
            updateCarousel();
        });

        prevBtn.addEventListener('click', () => {
            index = (index - 1 + slides.length) % slides.length;
            updateCarousel();
        });
    }

   
    if (slides.length > 0) {
        setInterval(() => {
            index = (index + 1) % slides.length;
            updateCarousel();
        }, 5000);
    }


 
    const logoLink = document.querySelector('.logo-group');
    if (logoLink) {
        logoLink.addEventListener('click', (e) => {
      
            const isHomePage = window.location.pathname.endsWith('index.html') ||
                window.location.pathname.endsWith('/') ||
                window.location.pathname === '';

            if (isHomePage) {
      
                index = 0;
                updateCarousel();


                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        });
    }


    const observerOptions = {
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');

            }
        });
    }, observerOptions);

    const revealElements = document.querySelectorAll('.reveal');
    revealElements.forEach(el => observer.observe(el));



    if (window.lucide) {
        window.lucide.createIcons();
    }

});
