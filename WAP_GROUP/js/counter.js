/* ============================================
   AgroSecure – counter.js
   Animated number counters on scroll
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {

  function animateCounter(el, target, duration = 1800, suffix = '') {
    let start = 0;
    const step = target / (duration / 16);
    const timer = setInterval(() => {
      start += step;
      if (start >= target) {
        start = target;
        clearInterval(timer);
      }
      el.textContent = Math.floor(start).toLocaleString();
    }, 16);
  }


  /* Stats bar big numbers — trigger on scroll */
  const bigNums = document.querySelectorAll('.big-num[data-target]');
  if (!bigNums.length) return;

  const triggered = new Set();

  function checkCounters() {
    bigNums.forEach(el => {
      if (triggered.has(el)) return;
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight - 60) {
        triggered.add(el);
        const target = parseInt(el.dataset.target, 10);
        animateCounter(el, target, 1600);
      }
    });
    if (triggered.size === bigNums.length) {
      window.removeEventListener('scroll', checkCounters);
    }
  }

  window.addEventListener('scroll', checkCounters, { passive: true });
  checkCounters(); // run once on load in case already visible
});