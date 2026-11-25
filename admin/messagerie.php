<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

// Badge non lus (chargé via JS)
$unreadCount = 0;
$stmt = $db->query("SELECT COUNT(*) AS nb FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE m.sender = 'visitor' AND m.is_read = 0");
$row = $stmt->fetch();
if ($row) { $unreadCount = (int)$row['nb']; }

$pageTitle = 'Messagerie — Administration';

require_once __DIR__ . '/includes/header.php';
?>
<div class="admin-content" style="padding:26px 22px;">
    <div class="section-title"><i class="fas fa-comments"></i> Messagerie d'assistance</div>

    <div style="display:flex; gap:20px; align-items:flex-start;">
        <!-- Liste des conversations -->
        <div style="flex:1 1 420px; min-width:300px;">
            <div class="btn-toolbar" style="margin-bottom:16px; flex-wrap:wrap;">
                <input type="search" id="convSearch" placeholder="Rechercher nom ou email…" style="padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); flex:1 1 200px; min-width:140px;">
                <select id="convStatus" style="padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:#fff;">
                    <option value="">Tous les statuts</option>
                    <option value="ouverte">Ouverte</option>
                    <option value="fermée">Fermée</option>
                    <option value="bloquée">Bloquée</option>
                </select>
            </div>

            <div id="convBadge" style="margin-bottom:10px; font-size:12px; color:var(--color-gray-dark); font-weight:600;">
                <span class="notranslate" translate="no"><?= (int)$unreadCount ?></span> message<?= (int)$unreadCount > 1 ? 's' : '' ?> non lu<?= (int)$unreadCount > 1 ? 'x' : '' ?>
            </div>

            <div id="convList" style="border:1px solid var(--color-border); border-radius:var(--radius); background:#fff; max-height:70vh; overflow-y:auto;">
                <!-- Chargé par AJAX -->
                <div style="padding:20px; text-align:center; color:var(--color-gray);">Chargement…</div>
            </div>
        </div>

        <!-- Détail de la conversation -->
        <div style="flex:1 1 560px; min-width:320px; display:flex; flex-direction:column;">
            <div id="convDetail" style="display:none; flex:1; border:1px solid var(--color-border); border-radius:var(--radius); background:#fff; overflow:hidden; display:flex; flex-direction:column;">
                <div style="padding:16px 20px; border-bottom:1px solid var(--color-border); background:var(--color-lighter); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; flex-direction:column; gap:2px;">
                        <div style="font-weight:700; color:var(--color-dark);" id="detailVisitor"></div>
                        <div style="font-size:12px; color:var(--color-gray);" id="detailMeta"></div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <span id="detailStatus" class="badge" style="font-size:11px;"></span>
                        <button class="btn btn--outline btn--sm" onclick="toggleClose()"><i class="fas fa-flag"></i> <span id="closeBtnText">Fermer</span></button>
                        <button class="btn btn--outline btn--sm" onclick="toggleBlock()"><i class="fas fa-ban"></i> <span id="blockBtnText">Bloquer</span></button>
                    </div>
                </div>

                <div id="detailBody" style="flex:1; overflow-y:auto; padding:16px 20px; background:var(--color-light); background-image:
                    linear-gradient(135deg, #f0f0f0 12%, transparent 12.5%),
                    linear-gradient(225deg, #f0f0f0 12%, transparent 12.5%);
                    background-size: 20px 20px;
                    background-position: 0 0, 10px 10px; display:flex; flex-direction:column; gap:12px;">
                    <!-- Messages chargés par AJAX -->
                </div>

                <form id="detailForm" style="padding:12px 20px; border-top:1px solid var(--color-border); background:#fff; display:flex; gap:8px; flex-shrink:0; align-items:center;">
                    <?= csrfField() ?>
                    <input type="hidden" name="conversation_id" id="detailConvId">
                    <input type="text" name="message" id="detailInput" placeholder="Écrire une réponse…" style="flex:1; padding:9px 12px; border:1px solid var(--color-border); border-radius:var(--radius-pill); outline:none;">
                    <label style="cursor:pointer; display:flex; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid var(--color-border); border-radius:50%; background:#fff; color:var(--color-gray); font-size:15px;">
                        <i class="fas fa-paperclip"></i>
                        <input type="file" name="image" id="detailAttach" accept="image/*" style="display:none;">
                    </label>
                    <button type="submit" class="btn btn--primary" style="padding:0 18px;">Envoyer</button>
                    <button type="button" class="btn btn--outline btn--sm" onclick="markRead()" style="font-size:11px; padding:0 10px;">Marquer lu</button>
                </form>
            </div>

            <div id="convEmpty" style="flex:1; display:flex; align-items:center; justify-content:center; color:var(--color-gray);">
                <p>Sélectionnez une conversation pour commencer.</p>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

<!-- Audio bip pour notification nouvelle message -->
<audio id="notifySound" src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YSUAAAA=" style="display:none;"></audio>

<script>
/* ---------- Admin Messagerie ---------- */
(function(){
  'use strict';

  var listEl = document.getElementById('convList');
  var detailEl = document.getElementById('convDetail');
  var emptyEl = document.getElementById('convEmpty');
  var bodyEl = document.getElementById('detailBody');
  var formEl = document.getElementById('detailForm');
  var inputEl = document.getElementById('detailInput');
  var searchEl = document.getElementById('convSearch');
  var statusEl = document.getElementById('convStatus');
  var badgeEl = document.getElementById('convBadge');
  var notifSound = document.getElementById('notifySound');

  var currentConvId = null;
  var lastPollTime = '0';
  var polling = false;
  var soundPlayed = false;

  // ---------- Charger la liste ----------
  function loadList() {
    var params = new URLSearchParams();
    if (searchEl.value) params.set('search', searchEl.value);
    if (statusEl.value) params.set('status', statusEl.value);

    fetch(IE_APP_URL + '/api/admin/messagerie.php?action=list&' + params.toString(), { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok) return;
        listEl.innerHTML = '';
        (data.conversations || []).forEach(function(c){
          var div = document.createElement('div');
          div.style.padding = '12px 16px';
          div.style.borderBottom = '1px solid #f0f0f0';
          div.style.cursor = 'pointer';
          div.style.display = 'flex';
          div.style.justifyContent = 'space-between';
          div.style.alignItems = 'center';
          div.style.transition = 'background 0.2s';
          div.onmouseover = function(){ div.style.background = '#fafafa'; };
          div.onmouseout = function(){ div.style.background = ''; };
          div.onclick = function(){ openConversation(c.id); };

          var unread = parseInt(c.unread_by_admin) === 1 ? '<span style="background:var(--color-primary); color:#fff; font-size:10px; font-weight:800; padding:1px 6px; border-radius:10px; margin-left:8px;">Non lu</span>' : '';
          var statusClass = c.status === 'bloquée' ? 'var(--color-error)' : (c.status === 'fermée' ? 'var(--color-gray)' : 'var(--color-success)');

          div.innerHTML =
            '<div style="flex:1; min-width:0;">' +
            '<div style="font-weight:600; color:var(--color-dark); font-size:13px;">' + escHtml(c.visitor_name) + unread + '</div>' +
            '<div style="font-size:11px; color:var(--color-gray);"><span class="notranslate" translate="no">' + escHtml(c.visitor_email || '—') + '</span></div>' +
            '</div>' +
            '<div style="font-size:11px; color:' + statusClass + '; font-weight:600; white-space:nowrap;">' + escHtml(c.status) + '</div>';
          listEl.appendChild(div);
        });

        // Mettre à jour le badge non lus
        fetch(IE_APP_URL + '/api/admin/messagerie.php?action=unread_count', { credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(d){
            if (d.ok && d.count !== undefined) {
              badgeEl.textContent = d.count + ' message' + (d.count > 1 ? 's non lus' : ' non lu');
              badgeEl.style.display = d.count > 0 ? 'block' : 'none';
              // Son si nouveau message non lu et pas encore joué
              if (d.count > 0 && !soundPlayed) {
                try { notifSound.play(); } catch(e){}
                soundPlayed = true;
              }
              if (d.count === 0) soundPlayed = false;
            }
          });
      });
  }

  // ---------- Ouvrir une conversation ----------
  function openConversation(cid) {
    currentConvId = cid;
    lastPollTime = '0';
    detailEl.style.display = 'flex';
    emptyEl.style.display = 'none';
    bodyEl.innerHTML = '<div style="text-align:center; color:var(--color-gray); padding:40px 0;">Chargement…</div>';

    fetch(IE_APP_URL + '/api/admin/messagerie.php?action=detail&conversation_id=' + cid, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok) return;
        var c = data.conversation;
        document.getElementById('detailVisitor').textContent = c.visitor_name;
        document.getElementById('detailConvId').value = c.id;
        var meta = (c.visitor_email ? '<span class="notranslate" translate="no">' + escHtml(c.visitor_email) + '</span>' : '—') +
                   ' — <span class="notranslate" translate="no">' + escHtml(c.visitor_ip || '—') + '</span>';
        document.getElementById('detailMeta').innerHTML = meta;
        var statusBadge = document.getElementById('detailStatus');
        statusBadge.textContent = c.status;
        statusBadge.className = 'badge ' + (c.status === 'bloquée' ? 'badge--inactive' : (c.status === 'fermée' ? 'badge--inactive' : 'badge--active'));
        document.getElementById('closeBtnText').textContent = c.status === 'fermée' ? 'Rouvrir' : 'Fermer';
        document.getElementById('blockBtnText').textContent = c.status === 'bloquée' ? 'Débloquer' : 'Bloquer';

        renderMessages(data.messages || []);
        lastPollTime = (data.messages && data.messages.length > 0) ? data.messages[data.messages.length-1].created_at : '0';
      });
  }

  function renderMessages(messages) {
    bodyEl.innerHTML = '';
    messages.forEach(function(m){
      appendMessage(m.sender, m.message, m.image, m.created_at, m.is_read);
    });
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function appendMessage(sender, message, image, time, isRead) {
    var div = document.createElement('div');
    var isAdmin = sender === 'admin';
    var cls = 'chat-message ' + (isAdmin ? 'chat-message--admin' : 'chat-message--visitor');
    div.className = cls;
    var html = '<strong style="font-size:11px; text-transform:uppercase; color:' + (isAdmin ? 'var(--color-primary)' : 'var(--color-dark)') + ';">' + escHtml(isAdmin ? 'Vous' : 'Visiteur') + '</strong><br>' +
               '<span class="notranslate" translate="no">' + escHtml(message) + '</span>';
    if (image) {
      html += '<br><div class="chat-message--image"><a href="' + IE_APP_URL + '/' + escHtml(image) + '" target="_blank"><img src="' + IE_APP_URL + '/' + escHtml(image) + '" alt="Image"></a></div>';
    }
    html += '<span class="chat-time">' + escHtml(time) + (isRead ? ' <small style="opacity:0.6;">lu</small>' : '') + '</span>';
    div.innerHTML = html;
    bodyEl.appendChild(div);
  }

  // ---------- Répondre ----------
  formEl.addEventListener('submit', function(e){
    e.preventDefault();
    var text = inputEl.value.trim();
    if (!text && !document.getElementById('detailAttach').files.length) return;
    var fd = new FormData(formEl);
    fd.append('conversation_id', currentConvId);
    fetch(IE_APP_URL + '/api/admin/messagerie.php?action=send', { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          inputEl.value = '';
          document.getElementById('detailAttach').value = '';
          openConversation(currentConvId);
        } else {
          alert(data.message || 'Erreur.');
        }
      });
  });

  // ---------- Marquer lu ----------
  function markRead() {
    if (!currentConvId) return;
    fetch(IE_APP_URL + '/api/admin/messagerie.php?action=mark_read', {
      method:'POST',
      body: new URLSearchParams({ csrf_token: document.querySelector('input[name="csrf_token"]').value, conversation_id: currentConvId }),
      credentials:'same-origin'
    }).then(function(r){ return r.json(); }).then(function(){
      openConversation(currentConvId);
    });
  }

  // ---------- Fermer / rouvrir ----------
  function toggleClose() {
    if (!currentConvId) return;
    var btn = document.getElementById('closeBtnText');
    var newStatus = btn.textContent === 'Fermer' ? 'fermée' : 'ouverte';
    fetch(IE_APP_URL + '/api/admin/messagerie.php?action=close', {
      method:'POST',
      body: new URLSearchParams({ csrf_token: document.querySelector('input[name="csrf_token"]').value, conversation_id: currentConvId, status: newStatus }),
      credentials:'same-origin'
    }).then(function(r){ return r.json(); }).then(function(){
      openConversation(currentConvId);
      loadList();
    });
  }

  // ---------- Bloquer / débloquer ----------
  function toggleBlock() {
    if (!currentConvId) return;
    var btn = document.getElementById('blockBtnText');
    var block = btn.textContent === 'Bloquer';
    fetch(IE_APP_URL + '/api/admin/messagerie.php?action=block', {
      method:'POST',
      body: new URLSearchParams({ csrf_token: document.querySelector('input[name="csrf_token"]').value, conversation_id: currentConvId, blocked: block ? '1' : '0' }),
      credentials:'same-origin'
    }).then(function(r){ return r.json(); }).then(function(){
      openConversation(currentConvId);
      loadList();
    });
  }

  // ---------- Polling des nouveaux messages ----------
  function pollDetail() {
    if (!currentConvId || polling) return;
    polling = true;
    fetch(IE_APP_URL + '/api/admin/messagerie.php?action=detail&conversation_id=' + currentConvId, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok) return;
        var msgs = data.messages || [];
        if (msgs.length > 0) {
          var lastTime = msgs[msgs.length-1].created_at;
          if (lastTime !== lastPollTime) {
            // Nouveau message : l'afficher
            var last = msgs[msgs.length-1];
            appendMessage(last.sender, last.message, last.image, last.created_at, last.is_read);
            bodyEl.scrollTop = bodyEl.scrollHeight;
            lastPollTime = lastTime;
            // Son si message visiteur non lu
            if (last.sender === 'visitor' && last.is_read === 0) {
              try { notifSound.play(); } catch(e){}
            }
          }
        }
      })
      .catch(function(){})
      .finally(function(){ polling = false; });
  }

  // ---------- Utils ----------
  function escHtml(text){
    var d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
  }

  // ---------- Init ----------
  loadList();
  searchEl.addEventListener('input', loadList);
  statusEl.addEventListener('change', loadList);
  setInterval(pollDetail, 3000);
  setInterval(loadList, 10000);
})();
</script>
