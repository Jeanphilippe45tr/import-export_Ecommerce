/* ==========================================================================
   EuroImport Export — JavaScript vanilla (ES6+)
   - Sélecteur de langue personnalisé (cookie googtrans + rechargement)
   - Sélecteur de devise (cookie currency + rechargement)
   - Sélecteur de pays de livraison (cookie delivery_country)
   - Menu mobile tiroir
   ========================================================================== */

(function () {
  'use strict';

  // ---------- Helpers cookie ----------
  function setCookie(name, value, days, path) {
    path = path || '/';
    var expires = '';
    if (days) {
      var date = new Date();
      date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
      expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=' + path + '; SameSite=Lax';
  }

  function getCookie(name) {
    var nameEq = name + '=';
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) === ' ') {
        c = c.substring(1, c.length);
      }
      if (c.indexOf(nameEq) === 0) {
        return decodeURIComponent(c.substring(nameEq.length, c.length));
      }
    }
    return null;
  }

  function deleteCookie(name, path) {
    path = path || '/';
    document.cookie = name + '=; path=' + path + '; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
  }

  // ---------- Sélecteur de langue (traduction automatique Google) ----------
  var langSelect = document.getElementById('lang-select');
  if (langSelect) {
    // Initialise la valeur affichée à partir du cookie googtrans actuel (/fr/CODE)
    var current = getCookie('googtrans');
    if (current) {
      var match = current.match(/^\/fr\/([a-zA-Z0-9_-]+)$/);
      if (match) {
        var code = match[1];
        var option = Array.from(langSelect.options).find(function (o) {
          return o.value === code;
        });
        if (option) {
          langSelect.value = code;
        }
      }
    }

    langSelect.addEventListener('change', function () {
      var code = this.value;
      if (code === 'fr') {
        // Français (original) : on supprime le cookie de traduction
        deleteCookie('googtrans', '/');
      } else {
        setCookie('googtrans', '/fr/' + code, 365, '/');
      }
      location.reload();
    });
  }

  // ---------- Sélecteur de devise ----------
  var currencySelect = document.getElementById('currency-select');
  if (currencySelect) {
    currencySelect.addEventListener('change', function () {
      setCookie('currency', this.value, 365, '/');
      location.reload();
    });
  }

  // ---------- Sélecteur de pays de livraison ----------
  var countrySelect = document.getElementById('country-select');
  if (countrySelect) {
    countrySelect.addEventListener('change', function () {
      var iso = this.value;
      if (iso) {
        setCookie('delivery_country', iso, 365, '/');
      }
      location.reload();
    });
  }

  // ---------- Menu mobile tiroir ----------
  var menuToggle = document.getElementById('menu-toggle');
  var drawer = document.getElementById('mobile-drawer');
  var drawerClose = document.getElementById('drawer-close');
  var drawerOverlay = document.getElementById('drawer-overlay');

  function openDrawer() {
    if (drawer) {
      drawer.classList.add('open');
      drawer.setAttribute('aria-hidden', 'false');
    }
    if (drawerOverlay) {
      drawerOverlay.classList.add('active');
    }
    if (menuToggle) {
      menuToggle.setAttribute('aria-expanded', 'true');
    }
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    if (drawer) {
      drawer.classList.remove('open');
      drawer.setAttribute('aria-hidden', 'true');
    }
    if (drawerOverlay) {
      drawerOverlay.classList.remove('active');
    }
    if (menuToggle) {
      menuToggle.setAttribute('aria-expanded', 'false');
    }
    document.body.style.overflow = '';
  }

  if (menuToggle) {
    menuToggle.addEventListener('click', openDrawer);
  }
  if (drawerClose) {
    drawerClose.addEventListener('click', closeDrawer);
  }
  if (drawerOverlay) {
    drawerOverlay.addEventListener('click', closeDrawer);
  }
  if (drawer) {
    drawer.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeDrawer);
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeDrawer(); }
  });

  // Fermer le menu mobile au redimensionnement vers desktop
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) { closeDrawer(); }
  });

  // ---------- Préchargement des suggestions de recherche (AJAX, phase 2) ----------
  // Préparé pour la recherche avec suggestions en direct.
  var searchInput = document.getElementById('search-input');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      // Les suggestions sont injectées par le widget Google Translate après chargement.
      // Aucun élément dynamique n'est créé ici en phase 1.
    });
  }
})();
