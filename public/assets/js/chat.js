/* ==========================================================================
   Widget de chat d'assistance (côté public)
   - Ouverture/fermeture de la bulle
   - Identité demandée dans le widget (ou reprise du compte client connecté)
   - Envoi texte + image
   - Long polling des nouveaux messages (sans bloquer la session PHP)
   - Statut en ligne / hors ligne
   ========================================================================== */

(function () {
  'use strict';

  var toggle = document.getElementById('chat-toggle');
  var widget = document.querySelector('.chat-widget');
  var windowEl = document.getElementById('chat-window');
  var closeBtn = document.getElementById('chat-close');
  var body = document.getElementById('chat-body');
  var form = document.getElementById('chat-form');
  var input = document.getElementById('chat-input');
  var attachBtn = document.getElementById('chat-attach');
  var attachInput = document.getElementById('chat-attach-input');
  var sendBtn = document.getElementById('chat-send');
  var badge = document.getElementById('chat-badge');
  var statusEl = document.getElementById('chat-status');
  var offlineNote = document.getElementById('chat-offline-note');
  var intro = document.getElementById('chat-intro');
  var identity = document.getElementById('chat-identity');
  var nameInput = document.getElementById('chat-name');
  var emailInput = document.getElementById('chat-email');
  var identityStart = document.getElementById('chat-identity-start');
  var identityError = document.getElementById('chat-identity-error');

  var customer = window.IE_CUSTOMER || { name: '', email: '' };

  var conversationId = localStorage.getItem('ie_chat_conversation_id') || null;
  var visitorName = localStorage.getItem('ie_chat_visitor_name') || customer.name || '';
  var visitorEmail = localStorage.getItem('ie_chat_visitor_email') || customer.email || '';
  var since = '0';
  var isOpen = false;
  var isPolling = false;
  var pending = null;

  if (!toggle || !windowEl || !form) return;

  // ---------- Helpers ----------
  function escHtml(text) {
    var d = document.createElement('div');
    d.textContent = text == null ? '' : String(text);
    return d.innerHTML;
  }

  function imageUrl(path) {
    if (!path) return '';
    return IE_APP_URL + '/' + String(path).replace(/^\/+/, '');
  }

  function setBadge(visible) {
    if (!badge) return;
    badge.classList.toggle('visible', !!visible);
  }

  /* Une seule source de vérité pour l’état ouvert/fermé (bulle + panneau + scroll). */
  function setOpen(open) {
    isOpen = !!open;
    windowEl.classList.toggle('open', isOpen);
    if (widget) widget.classList.toggle('is-open', isOpen);
    document.body.classList.toggle('chat-open', isOpen);
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) setBadge(false);
  }

  function setSending(disabled) {
    if (sendBtn) sendBtn.disabled = !!disabled;
    if (input) input.disabled = !!disabled;
    if (identityStart) identityStart.disabled = !!disabled;
  }

  function appendMessage(sender, message, image, time, isNew, id) {
    var div = document.createElement('div');
    if (id) div.dataset.id = String(id);
    var isVisitor = sender === 'visitor';
    var isAdmin = sender === 'admin';
    var cls = 'chat-message ' + (isVisitor ? 'chat-message--visitor' : isAdmin ? 'chat-message--admin' : 'chat-message--system');

    var messageHtml = '<span class="notranslate" translate="no">' + escHtml(message) + '</span>';
    if (image) {
      var url = imageUrl(image);
      messageHtml += '<br><div class="chat-message--image"><a href="' + escHtml(url) + '" target="_blank" rel="noopener"><img src="' + escHtml(url) + '" alt="Image"></a></div>';
    }

    div.className = cls;
    div.innerHTML = messageHtml + '<span class="chat-time">' + escHtml(time) + '</span>';

    if (isNew) {
      body.appendChild(div);
      body.scrollTop = body.scrollHeight;
    } else {
      body.insertBefore(div, body.firstChild);
    }
  }

  function appendSystem(message) {
    var div = document.createElement('div');
    div.className = 'chat-message chat-message--system';
    div.innerHTML = '<span class="notranslate" translate="no">' + escHtml(message) + '</span>';
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
  }

  function showIdentity() {
    if (!identity) return;
    identity.style.display = 'flex';
    if (nameInput) nameInput.value = visitorName;
    if (emailInput) emailInput.value = visitorEmail;
  }

  function hideIdentity() {
    if (identity) identity.style.display = 'none';
  }

  function api(url, options) {
    return fetch(IE_APP_URL + url, Object.assign({ credentials: 'same-origin' }, options || {}))
      .then(function (r) {
        return r.json().catch(function () {
          throw new Error('HTTP ' + r.status);
        });
      });
  }

  // ---------- Statut en ligne / hors ligne ----------
  function updateStatus() {
    api('/api/messagerie.php?action=status')
      .then(function (data) {
        if (statusEl) {
          statusEl.textContent = data.online ? 'En ligne' : 'Hors ligne';
          statusEl.className = 'chat-status ' + (data.online ? 'chat-status--online' : 'chat-status--offline');
        }
        if (offlineNote) {
          offlineNote.style.display = data.online ? 'none' : 'block';
          offlineNote.textContent = data.offline_message || 'Nous sommes hors ligne. Laissez un message, nous vous répondrons dès notre retour.';
        }
        if (intro) {
          intro.innerHTML = '<strong>Assistance</strong> — ' + (data.online ? 'Nous sommes en ligne.' : 'Nous sommes actuellement hors ligne.') + ' ' + (data.hours || '');
          intro.style.display = 'block';
        }
      })
      .catch(function () {
        if (statusEl) {
          statusEl.textContent = 'Hors ligne';
          statusEl.className = 'chat-status chat-status--offline';
        }
      });
  }

  // ---------- Historique ----------
  function loadHistory() {
    if (!conversationId) return Promise.resolve(false);
    return api('/api/messagerie.php?action=history&conversation_id=' + encodeURIComponent(conversationId))
      .then(function (data) {
        if (!data.ok) return false;
        body.innerHTML = '';
        (data.messages || []).forEach(function (m) {
          appendMessage(m.sender, m.message, m.image, m.created_at, false, m.id);
        });
        if (data.messages && data.messages.length) {
          since = data.messages[data.messages.length - 1].created_at;
        }
        hideIdentity();
        if (intro) intro.style.display = 'none';
        return true;
      })
      .catch(function () { return false; });
  }

  // ---------- Créer une conversation ----------
  function createConversation(name, email, after) {
    setSending(true);
    var fd = new FormData();
    fd.append('visitor_name', name);
    fd.append('visitor_email', email || '');

    return api('/api/messagerie.php?action=create', { method: 'POST', body: fd })
      .then(function (data) {
        if (!data.ok || !data.conversation_id) {
          throw new Error(data.message || 'Impossible de démarrer la conversation.');
        }
        conversationId = data.conversation_id;
        localStorage.setItem('ie_chat_conversation_id', conversationId);
        localStorage.setItem('ie_chat_visitor_name', visitorName);
        localStorage.setItem('ie_chat_visitor_email', visitorEmail);
        hideIdentity();
        return loadHistory();
      })
      .then(function () {
        if (typeof after === 'function') return after();
      })
      .catch(function (err) {
        appendSystem('Erreur : ' + (err.message || 'conversation impossible'));
        if (identityError) identityError.textContent = err.message || '';
        showIdentity();
      })
      .finally(function () {
        setSending(false);
      });
  }

  function ensureConversation(after) {
    if (conversationId) {
      loadHistory().then(function () {
        if (typeof after === 'function') after();
      });
      return;
    }
    if (visitorName) {
      createConversation(visitorName, visitorEmail, after);
      return;
    }
    showIdentity();
  }

  // ---------- Envoyer un message ----------
  function sendMessage() {
    var text = input ? input.value.trim() : '';
    var file = attachInput && attachInput.files.length ? attachInput.files[0] : null;
    if (!text && !file) return;

    if (!conversationId) {
      pending = { text: text, file: file };
      ensureConversation(function () { sendMessage(); });
      return;
    }

    var fd = new FormData(form);
    fd.set('conversation_id', conversationId);
    if (file) {
      fd.set('image', file, file.name);
    } else {
      fd.delete('image');
      if (!text) fd.append('message', '');
    }

    setSending(true);
    api('/api/messagerie.php?action=send', { method: 'POST', body: fd })
      .then(function (data) {
        if (!data.ok) throw new Error(data.message || 'Erreur lors de l envoi.');
        if (input) input.value = '';
        if (attachInput) attachInput.value = '';
        if (input) input.placeholder = 'Écrire un message…';
        since = data.server_time || since;
        pending = null;
        loadHistory();
      })
      .catch(function (err) {
        appendSystem('Erreur : ' + (err.message || 'envoi impossible'));
      })
      .finally(function () {
        setSending(false);
        if (input && !input.disabled) input.focus();
      });
  }

  // ---------- Long polling ----------
  function poll() {
    if (!conversationId || isPolling) return;
    isPolling = true;

    api('/api/messagerie.php?action=poll&conversation_id=' + encodeURIComponent(conversationId) + '&since=' + encodeURIComponent(since))
      .then(function (data) {
        if (data.ok && data.messages && data.messages.length) {
          data.messages.forEach(function (m) {
            // Éviter les doublons avec l'historique rechargé après envoi
            if (m.id && body.querySelector('[data-id="' + m.id + '"]')) return;
            appendMessage(m.sender, m.message, m.image, m.created_at, true, m.id);
          });
          since = data.messages[data.messages.length - 1].created_at;
          if (!isOpen) setBadge(true);
        }
      })
      .catch(function () {})
      .finally(function () {
        isPolling = false;
      });
  }

  // ---------- Événements ----------
  toggle.addEventListener('click', function () {
    setOpen(!isOpen);
    if (isOpen) ensureConversation();
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      setOpen(false);
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && isOpen) {
      setOpen(false);
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    sendMessage();
  });

  if (attachBtn && attachInput) {
    attachBtn.addEventListener('click', function () { attachInput.click(); });
    attachInput.addEventListener('change', function () {
      if (attachInput.files.length) {
        input.placeholder = attachInput.files[0].name;
        if (input.value.trim() === '') input.focus();
      }
    });
  }

  if (identityStart) {
    identityStart.addEventListener('click', function () {
      var name = (nameInput ? nameInput.value : '').trim();
      var email = (emailInput ? emailInput.value : '').trim();
      if (name === '') {
        if (identityError) identityError.textContent = 'Votre nom est requis.';
        if (nameInput) nameInput.focus();
        return;
      }
      if (identityError) identityError.textContent = '';
      visitorName = name;
      visitorEmail = email;
      createConversation(visitorName, visitorEmail, function () {
        if (pending) sendMessage();
      });
    });
  }

  if (identity && identityError) {
    identity.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
        e.preventDefault();
        if (identityStart) identityStart.click();
      }
    });
  }

  // ---------- Initialisation ----------
  updateStatus();
  setInterval(updateStatus, 60000);
  // Le polling démarre toujours : il ne fait rien tant qu'il n'y a pas de conversation.
  setInterval(poll, 3000);
  if (conversationId) {
    loadHistory();
  }
})();
