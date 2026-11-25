/* ==========================================================================
   Catalogue & produit — JavaScript vanilla
   - Suggestions de recherche en direct (AJAX)
   - Ajout au panier via AJAX (panier session PHP)
   ========================================================================== */

(function () {
  'use strict';

  // ---------- Suggestions de recherche ----------
  var searchInput = document.getElementById('search-input');
  var suggestionsBox = null;

  if (searchInput) {
    suggestionsBox = document.createElement('div');
    suggestionsBox.className = 'search-suggestions';
    suggestionsBox.style.position = 'absolute';
    suggestionsBox.style.background = '#fff';
    suggestionsBox.style.border = '1px solid var(--color-border)';
    suggestionsBox.style.borderTop = 'none';
    suggestionsBox.style.borderRadius = '0 0 var(--radius) var(--radius)';
    suggestionsBox.style.boxShadow = 'var(--shadow-lg)';
    suggestionsBox.style.zIndex = '500';
    suggestionsBox.style.width = '100%';
    suggestionsBox.style.maxWidth = '340px';
    suggestionsBox.style.display = 'none';
    suggestionsBox.style.fontSize = '13px';

    var searchForm = searchInput.closest('.search-bar');
    if (searchForm) {
      searchForm.style.position = 'relative';
      searchForm.appendChild(suggestionsBox);
    }

    var timer = null;

    function renderSuggestions(results) {
      suggestionsBox.innerHTML = '';
      if (!results || results.length === 0) {
        suggestionsBox.style.display = 'none';
        return;
      }
      results.forEach(function (item) {
        var div = document.createElement('div');
        div.style.padding = '8px 10px';
        div.style.cursor = 'pointer';
        div.style.borderBottom = '1px solid #f0f0f0';
        div.innerHTML =
          '<div style="font-weight:600; color:var(--color-dark);">' + escapeHtml(item.name) + '</div>' +
          '<div style="font-size:12px; color:var(--color-gray-light);">' + escapeHtml(item.reference) + ' — ' + escapeHtml(item.price) + '</div>';
        div.addEventListener('click', function () {
          window.location.href = item.url;
        });
        suggestionsBox.appendChild(div);
      });
      suggestionsBox.style.display = 'block';
    }

    function escapeHtml(text) {
      var d = document.createElement('div');
      d.textContent = text;
      return d.innerHTML;
    }

    searchInput.addEventListener('input', function () {
      var q = this.value.trim();
      clearTimeout(timer);
      if (q.length < 2) {
        suggestionsBox.style.display = 'none';
        return;
      }
      timer = setTimeout(function () {
        fetch(IE_APP_URL + '/api/search.php?q=' + encodeURIComponent(q) + '&limit=8')
          .then(function (r) { return r.json(); })
          .then(function (data) {
            renderSuggestions(data.results || []);
          })
          .catch(function () {
            suggestionsBox.style.display = 'none';
          });
      }, 300);
    });

    document.addEventListener('click', function (e) {
      if (suggestionsBox && !searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.style.display = 'none';
      }
    });
  }

  // ---------- Ajout au panier (AJAX) ----------
  var addToCartForms = document.querySelectorAll('form#add-to-cart-form');
  addToCartForms.forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      var originalText = btn ? btn.innerHTML : '';
      if (btn) { btn.innerHTML = 'Ajout…'; btn.disabled = true; }

      var formData = new FormData(form);
      // Attention : le champ caché name="action" écrase form.action en JS.
      // On lit donc l'attribut HTML et on le résout contre <base href>.
      var actionUrl = new URL(form.getAttribute('action'), document.baseURI).href;

      fetch(actionUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
        .then(function (r) {
          if (!r.ok) {
            return r.json().catch(function () {
              throw new Error('HTTP ' + r.status);
            });
          }
          return r.json();
        })
        .then(function (data) {
          if (data.ok) {
            // Mise à jour du compteur panier dans le header
            var counters = document.querySelectorAll('.cart-count');
            counters.forEach(function (c) { c.textContent = data.cart_count ?? 0; });
            // Rechargement léger de la page pour refléter le panier (ou affichage d'un toast)
            if (data.redirect) {
              window.location.href = data.redirect;
            } else {
              // Petit message de confirmation
              var toast = document.createElement('div');
              toast.style.position = 'fixed';
              toast.style.top = '20px';
              toast.style.left = '50%';
              toast.style.transform = 'translateX(-50%)';
              toast.style.background = 'var(--color-success)';
              toast.style.color = '#fff';
              toast.style.padding = '10px 18px';
              toast.style.borderRadius = 'var(--radius-sm)';
              toast.style.zIndex = '1000';
              toast.style.fontSize = '13px';
              toast.textContent = 'Article ajouté au panier.';
              document.body.appendChild(toast);
              setTimeout(function () { toast.remove(); }, 2500);
            }
          } else {
            alert(data.message || 'Erreur lors de l ajout au panier.');
          }
        })
        .catch(function (err) {
          alert((err && err.message) ? err.message : 'Erreur de connexion.');
        })
        .finally(function () {
          if (btn) { btn.innerHTML = originalText; btn.disabled = false; }
        });
    });
  });

  // ---------- Sélecteur de devise : rechargement immédiat ----------
  var currencySelect = document.getElementById('currency-select');
  if (currencySelect) {
    currencySelect.addEventListener('change', function () {
      document.cookie = 'currency=' + this.value + '; path=/; SameSite=Lax; max-age=31536000';
      location.reload();
    });
  }
})();
