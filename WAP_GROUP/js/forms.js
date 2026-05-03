/* ============================================
   AgroSecure – forms.js
   Form submission & JS validation (shared)
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {

  /* Generic form submit handler */
  function setupForm(formId, successMsg) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      let allValid = true;
      const fields = form.querySelectorAll('input[required], textarea[required], select[required]');
      fields.forEach(f => {
        if (!window.validateField(f)) allValid = false;
      });

      if (!allValid) {
        const firstErr = form.querySelector('[style*="rgb(230, 57, 70)"]');
        if (firstErr) firstErr.focus();
        return;
      }

      /* Show loading state */
      const submitBtn = form.querySelector('[type="submit"]');
      const originalText = submitBtn ? submitBtn.innerHTML : '';
      if (submitBtn) {
        submitBtn.innerHTML = '<span class="spinner"></span> Sending…';
        submitBtn.disabled = true;
      }

      /* Simulate server round-trip (replace with real fetch to PHP) */
      setTimeout(() => {
        if (submitBtn) {
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        }

        /* Show success */
        let successEl = form.querySelector('.form-success');
        if (!successEl) {
          successEl = document.createElement('div');
          successEl.className = 'form-success';
          form.appendChild(successEl);
        }
        successEl.textContent = successMsg || ' Submitted successfully! We will get back to you shortly.';
        successEl.classList.add('show');
        successEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        form.reset();
        setTimeout(() => successEl.classList.remove('show'), 6000);
      }, 1500);
    });
  }

  /* Wire up all forms
    -------------------------------------------------------------------------
    WE HAVE COMMENTED THESE OUT FOR OPTION B (PURE HTML).
    By disabling these here, the forms will submit directly to your PHP files
    via HTML, without JavaScript intercepting and canceling the request.
    -------------------------------------------------------------------------
  */
  // setupForm('contactForm',  'Message sent! Our team will respond within 24 hours.');
  // setupForm('reportForm',   'Crisis report submitted. Aid coordinators will be notified immediately.');
  // setupForm('registerForm', 'Registration successful! Check your email for confirmation.');
  // setupForm('loginForm',    'Login successful! Redirecting to your dashboard…');
  // setupForm('newsletterForm', ' You have been subscribed to AgroSecure updates!');

  /* === SEVERITY BUTTONS (report page) === */
  document.querySelectorAll('.severity-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.severity-btn').forEach(b => {
        b.classList.remove('active-low', 'active-medium', 'active-high');
      });
      const level = btn.dataset.level;
      btn.classList.add(`active-${level}`);
      const hidden = document.getElementById('severityValue');
      if (hidden) hidden.value = level;
    });
  });

  /* === PASSWORD TOGGLE === */
  document.querySelectorAll('.toggle-password').forEach(toggle => {
    toggle.addEventListener('click', () => {
      const inputId = toggle.dataset.target;
      const input = document.getElementById(inputId);
      if (!input) return;
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      toggle.textContent = isPassword ? 'hide' : 'unhide';
    });
  });

  /* === AUTH TABS === */
  document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const target = tab.dataset.tab;
      document.querySelectorAll('.auth-panel').forEach(panel => {
        panel.style.display = panel.id === target ? 'block' : 'none';
      });
    });
  });

  /* === RESOURCE FILTER (resources page) === */
  const filterBtns = document.querySelectorAll('.filter-btn');
  const resourceItems = document.querySelectorAll('.resource-item');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filter = btn.dataset.filter;
      resourceItems.forEach(item => {
        if (filter === 'all' || item.dataset.category === filter) {
          item.style.display = '';
          item.style.animation = 'scalePop 0.3s ease both';
        } else {
          item.style.display = 'none';
        }
      });
    });
  });

});