(function () {
  'use strict';

  // ---------- Dark / Light mode ----------
  var THEME_KEY = 'absensi-theme';

  var ICONS_DARK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
  var ICONS_LIGHT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4.5"/><line x1="12" y1="19.5" x2="12" y2="22"/><line x1="2" y1="12" x2="4.5" y2="12"/><line x1="19.5" y1="12" x2="22" y2="12"/><line x1="4.93" y1="4.93" x2="6.66" y2="6.66"/><line x1="17.34" y1="17.34" x2="19.07" y2="19.07"/><line x1="4.93" y1="19.07" x2="6.66" y2="17.34"/><line x1="17.34" y1="6.66" x2="19.07" y2="4.93"/></svg>';

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    var btn = document.getElementById('theme-toggle');
    if (btn) {
      var isDark = theme === 'dark';
      btn.innerHTML = isDark ? ICONS_DARK : ICONS_LIGHT;
      btn.title = isDark ? 'Mode terang' : 'Mode gelap';
    }
  }

  var stored = null;
  try { stored = localStorage.getItem(THEME_KEY); } catch (e) { /* ignore */ }
  var initial = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  applyTheme(initial);

  window.addEventListener('DOMContentLoaded', function () {
    var modeToggle = document.getElementById('theme-toggle');
    if (modeToggle) {
      modeToggle.addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        try { localStorage.setItem(THEME_KEY, next); } catch (e) { /* ignore */ }
      });
    }

    // ---------- Sidebar mobile toggle ----------
    var menuBtn = document.getElementById('sidebar-toggle');
    var sidebar = document.getElementById('sidebar');
    if (menuBtn && sidebar) {
      menuBtn.addEventListener('click', function () { sidebar.classList.toggle('open'); });
      document.addEventListener('click', function (e) {
        if (window.innerWidth <= 760 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
          sidebar.classList.remove('open');
        }
      });
    }

    // ---------- Scroll to top ----------
    var toTop = document.getElementById('to-top');
    if (toTop) {
      window.addEventListener('scroll', function () {
        toTop.classList.toggle('show', window.scrollY > 260);
      });
      toTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }

    // ---------- Auto-hide flash ----------
    document.querySelectorAll('.alert-auto').forEach(function (el) {
      setTimeout(function () {
        el.classList.add('fade-out');
        setTimeout(function () { el.remove(); }, 400);
      }, 4200);
    });

    // ---------- Konfirmasi hapus ----------
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
          e.preventDefault();
        }
      });
    });
  });
})();