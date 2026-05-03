/* AgroSecure – main.js
   Navbar, scroll, hamburger, card animations */

document.addEventListener('DOMContentLoaded', () => {

  /* === NAVBAR SCROLL === */
  const navbar = document.getElementById('navbar');

  window.addEventListener('scroll', () => {
    if (window.scrollY > 60) {
      navbar && navbar.classList.add('scrolled');
    } else {
      navbar && navbar.classList.remove('scrolled');
    }
  });

  /* === HAMBURGER MENU === */
  const hamburger = document.getElementById('hamburger');
  const navLinks = document.getElementById('navLinks');

  if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => {
      navLinks.classList.toggle('open');
      const isOpen = navLinks.classList.contains('open');
      hamburger.setAttribute('aria-expanded', isOpen);
      // Animate hamburger lines
      const spans = hamburger.querySelectorAll('span');
      if (isOpen) {
        spans[0].style.transform = 'rotate(45deg) translateY(7px)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'rotate(-45deg) translateY(-7px)';
      } else {
        spans.forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
      }
    });

    // Close nav on link click (mobile)
    navLinks.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        navLinks.classList.remove('open');
        const spans = hamburger.querySelectorAll('span');
        spans.forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
      });
    });
  }

  /* === INTERSECTION OBSERVER for card animations === */
  const animCards = document.querySelectorAll('.animate-card');
  if (animCards.length && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    animCards.forEach(card => observer.observe(card));
  } else {
    animCards.forEach(c => c.classList.add('visible'));
  }

  /* === ACTIVE NAV LINK === */
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a').forEach(link => {
    const href = link.getAttribute('href');
    if (href && href.includes(currentPage)) {
      link.classList.add('active');
    }
  });

  /* === FORM VALIDATION HELPER (reusable) === */
  window.validateField = function(input) {
    const group = input.closest('.form-group');
    if (!group) return true;
    const err = group.querySelector('.form-error');
    const val = input.value.trim();
    let valid = true;

    if (input.required && !val) {
      if (err) { err.textContent = 'This field is required.'; err.classList.add('show'); }
      input.style.borderColor = '#e63946';
      valid = false;
    } else if (input.type === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
      if (err) { err.textContent = 'Please enter a valid email address.'; err.classList.add('show'); }
      input.style.borderColor = '#e63946';
      valid = false;
    } else if (input.type === 'tel' && val && !/^\+?[\d\s\-]{7,}$/.test(val)) {
      if (err) { err.textContent = 'Please enter a valid phone number.'; err.classList.add('show'); }
      input.style.borderColor = '#e63946';
      valid = false;
    } else {
      if (err) err.classList.remove('show');
      input.style.borderColor = '';
    }
    return valid;
  };

  /* Wire up live validation on all inputs */
  document.querySelectorAll('input, textarea, select').forEach(field => {
    field.addEventListener('blur', () => window.validateField(field));
    field.addEventListener('input', () => {
      if (field.style.borderColor === 'rgb(230, 57, 70)') {
        window.validateField(field);
      }
    });
  });

  /* === SMOOTH INTERNAL ANCHOR LINKS === */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', e => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

});