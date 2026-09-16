// Mobile Menu Toggle
function toggleMenu() {
    const navList = document.getElementById('navList');
    const menuIcon = document.getElementById('menuIcon');
    const body = document.body;
    
    navList.classList.toggle('active');
    body.classList.toggle('menu-open');
    
    // Toggle icon between bars and times
    if (navList.classList.contains('active')) {
        menuIcon.classList.remove('fa-bars');
        menuIcon.classList.add('fa-times');
        menuIcon.style.color = "#fff";
        
        body.style.overflow = 'hidden';
    } else {
        menuIcon.classList.remove('fa-times');
        menuIcon.classList.add('fa-bars');
        menuIcon.style.color = "";
        body.style.overflow = '';
    }
}

// Close menu when clicking on a link
document.querySelectorAll('.nav-list a').forEach(link => {
    link.addEventListener('click', () => {
        const navList = document.getElementById('navList');
        const menuIcon = document.getElementById('menuIcon');
        const body = document.body;
        
        navList.classList.remove('active');
        body.classList.remove('menu-open');
        menuIcon.classList.remove('fa-times');
        menuIcon.classList.add('fa-bars');
        body.style.overflow = '';
    });
});

// Close menu when clicking overlay (on mobile)
document.addEventListener('click', function(e) {
    const navList = document.getElementById('navList');
    const checkBtn = document.querySelector('.checkbtn');
    const body = document.body;
    const menuIcon = document.getElementById('menuIcon');
    
    if (body.classList.contains('menu-open') && 
        !navList.contains(e.target) && 
        !checkBtn.contains(e.target)) {
        navList.classList.remove('active');
        body.classList.remove('menu-open');
        menuIcon.classList.remove('fa-times');
        menuIcon.classList.add('fa-bars');
        body.style.overflow = '';
    }
});

// Glassmorphism effect on scroll
window.addEventListener('scroll', function() {
    const header = document.querySelector('.header');
    if (window.scrollY > 50) {
        header.classList.add('scrolled');
    } else {
        header.classList.remove('scrolled');
    }
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// Initialize Glide.js Testimonials Slider
document.addEventListener('DOMContentLoaded', function() {
    new Glide('.glide', {
        type: 'carousel',
        startAt: 0,
        perView: 1,
        autoplay: 5000,
        hoverpause: true,
        animationDuration: 500
    }).mount();
});

// Contact Form Handler
async function handleFormSubmit(event) {
    event.preventDefault();
    
    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnLoader = document.getElementById('btnLoader');
    const formMessage = document.getElementById('formMessage');
    
    const formData = new FormData(form);
    
    // Disable button and show loader
    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline';
    formMessage.style.display = 'none';
    
    try {
        const response = await fetch('send-telegram-message.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        formMessage.style.display = 'block';
        
        if (result.success) {
            formMessage.style.background = 'rgba(34, 197, 94, 0.2)';
            formMessage.style.color = '#22c55e';
            formMessage.style.border = '2px solid #22c55e';
            formMessage.textContent = result.message;
            form.reset();
        } else {
            formMessage.style.background = 'rgba(239, 68, 68, 0.2)';
            formMessage.style.color = '#ef4444';
            formMessage.style.border = '2px solid #ef4444';
            formMessage.textContent = result.message || 'Failed to send message';
        }
        
    } catch (error) {
        formMessage.style.display = 'block';
        formMessage.style.background = 'rgba(239, 68, 68, 0.2)';
        formMessage.style.color = '#ef4444';
        formMessage.style.border = '2px solid #ef4444';
        formMessage.textContent = 'An error occurred. Please try again.';
    } finally {
        submitBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
    }
}

// Attach form submit handler
document.getElementById('contactForm').addEventListener('submit', handleFormSubmit);