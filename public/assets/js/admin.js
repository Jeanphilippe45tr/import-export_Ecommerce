/* ==========================================================================
   Espace admin — JavaScript
   ========================================================================== */
(function () {
  'use strict';

  // Sidebar mobile
  var toggle = document.getElementById('adminSidebarToggle');
  var closeSidebarBtn = document.getElementById('adminSidebarClose');
  var sidebar = document.querySelector('.admin-sidebar');
  var overlay = document.querySelector('.admin-sidebar-overlay');

  function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    if (overlay) overlay.classList.toggle('open', open);
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.style.overflow = open ? 'hidden' : '';
  }

  function toggleSidebar() {
    setSidebar(!sidebar.classList.contains('open'));
  }

  if (toggle) {
    toggle.setAttribute('aria-expanded', 'false');
    toggle.addEventListener('click', toggleSidebar);
  }
  if (closeSidebarBtn) {
    closeSidebarBtn.addEventListener('click', function () { setSidebar(false); });
  }
  if (overlay) {
    overlay.addEventListener('click', function () { setSidebar(false); });
  }
  if (sidebar) {
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { setSidebar(false); });
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
      setSidebar(false);
    }
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) setSidebar(false);
  });

  // Badge messages non lus (mise à jour périodique simple)
  var badge = document.getElementById('admin-nav-badge');
  if (badge) {
    setInterval(function () {
      fetch(IE_APP_URL + '/api/admin/messagerie.php?action=unread_count', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.count !== undefined) {
            badge.textContent = data.count;
            badge.style.display = data.count > 0 ? 'inline-block' : 'none';
          }
        });
    }, 5000);
  }
})();
