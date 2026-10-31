/* ═══════════════════════════════════════════════════════════
   DEVZOOP — Script
   Progress bar, navigation, scroll reveals, hero grid, footer
   ═══════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  /* ── Progress Bar ───────────────────────────────────── */
  const progressBar = document.getElementById('myProgressBar');

  function updateProgressBar() {
    const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
    const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    if (scrollHeight > 0) {
      progressBar.style.width = (scrollTop / scrollHeight * 100) + '%';
    }
  }

  /* ── Sticky Header ──────────────────────────────────── */
  const header = document.getElementById('site-header');

  function updateHeader() {
    if (window.scrollY > 60) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  }

  /* ── Mobile Navigation ──────────────────────────────── */
  const navToggle = document.getElementById('navToggle');
  const mainNav = document.getElementById('main-nav');

  // Create overlay element
  const navOverlay = document.createElement('div');
  navOverlay.classList.add('nav-overlay');
  document.body.appendChild(navOverlay);

  function openNav() {
    navToggle.classList.add('active');
    navToggle.setAttribute('aria-expanded', 'true');
    mainNav.classList.add('active');
    navOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeNav() {
    navToggle.classList.remove('active');
    navToggle.setAttribute('aria-expanded', 'false');
    mainNav.classList.remove('active');
    navOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  navToggle.addEventListener('click', function () {
    if (mainNav.classList.contains('active')) {
      closeNav();
    } else {
      openNav();
    }
  });

  navOverlay.addEventListener('click', closeNav);

  // Close nav on link click
  mainNav.querySelectorAll('.nav-link').forEach(function (link) {
    link.addEventListener('click', closeNav);
  });

  // Close on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && mainNav.classList.contains('active')) {
      closeNav();
    }
  });

  /* ── Smooth Scroll for anchor links ─────────────────── */
  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      var targetId = this.getAttribute('href');
      if (targetId === '#') return;

      var target = document.querySelector(targetId);
      if (target) {
        e.preventDefault();
        var headerHeight = header.offsetHeight + 20;
        var targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight;
        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
      }
    });
  });

  /* ── Intersection Observer — Reveal animations ──────── */
  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (!prefersReducedMotion) {
    var revealObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('revealed');
            revealObserver.unobserve(entry.target);
          }
        });
      },
      {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
      }
    );

    document.querySelectorAll('.reveal-up').forEach(function (el) {
      revealObserver.observe(el);
    });
  } else {
    // If reduced motion, just show everything
    document.querySelectorAll('.reveal-up').forEach(function (el) {
      el.classList.add('revealed');
    });
  }

  /* ── Active nav link on scroll ──────────────────────── */
  var sections = document.querySelectorAll('section[id]');
  var navLinks = document.querySelectorAll('.nav-link');

  function updateActiveNav() {
    var scrollPos = window.scrollY + header.offsetHeight + 100;

    sections.forEach(function (section) {
      var sectionTop = section.offsetTop;
      var sectionHeight = section.offsetHeight;
      var sectionId = section.getAttribute('id');

      if (scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
        navLinks.forEach(function (link) {
          link.classList.remove('active');
          if (link.getAttribute('href') === '#' + sectionId) {
            link.classList.add('active');
          }
        });
      }
    });
  }

  /* ── Hero Grid Canvas ───────────────────────────────── */
  var heroCanvas = document.getElementById('hero-grid');

  function drawHeroGrid() {
    if (!heroCanvas) return;

    var ctx = heroCanvas.getContext('2d');
    var parent = heroCanvas.parentElement;

    // Set canvas size
    var dpr = window.devicePixelRatio || 1;
    var w = parent.offsetWidth;
    var h = parent.offsetHeight;

    heroCanvas.width = w * dpr;
    heroCanvas.height = h * dpr;
    heroCanvas.style.width = w + 'px';
    heroCanvas.style.height = h + 'px';
    ctx.scale(dpr, dpr);

    ctx.clearRect(0, 0, w, h);

    // Grid lines
    var gridSize = 80;
    ctx.strokeStyle = 'rgba(255, 92, 31, 0.04)';
    ctx.lineWidth = 0.5;

    // Vertical lines
    for (var x = 0; x < w; x += gridSize) {
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, h);
      ctx.stroke();
    }

    // Horizontal lines
    for (var y = 0; y < h; y += gridSize) {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(w, y);
      ctx.stroke();
    }

    // Coordinate markers at intersections (sparse)
    ctx.fillStyle = 'rgba(255, 92, 31, 0.08)';
    ctx.font = '9px "JetBrains Mono", monospace';

    for (var gx = gridSize * 2; gx < w; gx += gridSize * 3) {
      for (var gy = gridSize * 2; gy < h; gy += gridSize * 3) {
        // Small cross marker
        ctx.strokeStyle = 'rgba(255, 92, 31, 0.1)';
        ctx.lineWidth = 0.5;
        ctx.beginPath();
        ctx.moveTo(gx - 4, gy);
        ctx.lineTo(gx + 4, gy);
        ctx.moveTo(gx, gy - 4);
        ctx.lineTo(gx, gy + 4);
        ctx.stroke();

        // Coordinate label
        ctx.fillText(
          Math.round(gx) + ',' + Math.round(gy),
          gx + 6,
          gy - 4
        );
      }
    }

    // Reset stroke for grid lines
    ctx.strokeStyle = 'rgba(255, 92, 31, 0.04)';

    // Accent diagonal line
    ctx.strokeStyle = 'rgba(184, 255, 46, 0.03)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(w * 0.6, 0);
    ctx.lineTo(w, h * 0.7);
    ctx.stroke();

    // Another accent line
    ctx.strokeStyle = 'rgba(255, 92, 31, 0.04)';
    ctx.beginPath();
    ctx.moveTo(w * 0.3, h);
    ctx.lineTo(w * 0.8, 0);
    ctx.stroke();
  }

  // Draw on load and resize
  drawHeroGrid();
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(drawHeroGrid, 150);
  });

  /* ── Portrait Perspective Switchers ────────────────── */
  var heroCamButtons = document.querySelectorAll('.portrait-cam-switch .cam-btn');
  var heroPortraitImg = document.getElementById('heroPortraitImg');
  var heroPortraitRole = document.getElementById('heroPortraitRole');

  heroCamButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (this.classList.contains('active')) return;
      heroCamButtons.forEach(function (b) { b.classList.remove('active'); });
      this.classList.add('active');
      var newSrc = this.getAttribute('data-img');
      var newRole = this.getAttribute('data-role');
      if (heroPortraitImg && newSrc) {
        heroPortraitImg.style.opacity = '0.3';
        setTimeout(function () {
          heroPortraitImg.src = newSrc;
          heroPortraitImg.style.opacity = '1';
        }, 150);
      }
      if (heroPortraitRole && newRole) {
        heroPortraitRole.textContent = newRole;
      }
    });
  });

  var aboutSwitchButtons = document.querySelectorAll('.about-image-switch .about-switch-btn');
  var aboutPortraitImg = document.getElementById('aboutPortraitImg');
  var aboutImageCaption = document.getElementById('aboutImageCaption');

  aboutSwitchButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (this.classList.contains('active')) return;
      aboutSwitchButtons.forEach(function (b) { b.classList.remove('active'); });
      this.classList.add('active');
      var newSrc = this.getAttribute('data-img');
      var newCaption = this.getAttribute('data-caption');
      if (aboutPortraitImg && newSrc) {
        aboutPortraitImg.style.opacity = '0.3';
        setTimeout(function () {
          aboutPortraitImg.src = newSrc;
          aboutPortraitImg.style.opacity = '1';
        }, 150);
      }
      if (aboutImageCaption && newCaption) {
        aboutImageCaption.textContent = newCaption;
      }
    });
  });

  /* ── Back to Top ────────────────────────────────────── */
  var backToTop = document.getElementById('backToTop');
  if (backToTop) {
    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ── Dynamic Footer Year ────────────────────────────── */
  var footerYear = document.getElementById('footer-year');
  if (footerYear) {
    footerYear.textContent = new Date().getFullYear();
  }

  /* ── Scroll event listener (throttled) ──────────────── */
  var ticking = false;

  function onScroll() {
    if (!ticking) {
      window.requestAnimationFrame(function () {
        updateProgressBar();
        updateHeader();
        updateActiveNav();
        ticking = false;
      });
      ticking = true;
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });

  // Run once on load
  updateProgressBar();
  updateHeader();
  updateActiveNav();

})();
