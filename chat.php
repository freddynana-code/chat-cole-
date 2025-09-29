<?php
// chat.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$currentUser = $_SESSION['username'];

// --- Infos du user connecté
$meStmt = $db->prepare("SELECT username, avatar, city, country FROM users WHERE username = :me LIMIT 1");
$meStmt->execute([':me' => $currentUser]);
$me = $meStmt->fetch();
$myAvatar = $me['avatar'] ?: 'uploads/avatars/default.png';

// --- User sélectionné (si présent)
$selectedUser = isset($_GET['user']) ? trim($_GET['user']) : '';
$showChatBox = false;
$peer = null;

if ($selectedUser !== '') {
    $peerStmt = $db->prepare("
        SELECT username, avatar, city, country
        FROM users
        WHERE username = :u AND username <> :me
        LIMIT 1
    ");
    $peerStmt->execute([':u' => $selectedUser, ':me' => $currentUser]);
    $peer = $peerStmt->fetch();
    if ($peer) {
        $showChatBox = true;
    } else {
        $selectedUser = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Real-time Chat</title>
  <link href="style.css" rel="stylesheet">
  <style>
    .user-list ul { list-style: disc; padding-left: 20px; }
    .user-item { display:flex; align-items:center; gap:10px; margin:6px 0; }
    .user-item img { width:28px; height:28px; border-radius:50%; object-fit:cover; }
    .user-meta { display:flex; flex-direction:column; line-height:1.1; }
    .badge-unread { display:none; font-size:12px; background:#e02424; color:#fff; padding:2px 6px; border-radius:10px; margin-top:2px; width:max-content; }
    .header { display:flex; align-items:center; gap:12px; }
    .header-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
    .header-right img { width:32px; height:32px; border-radius:50%; object-fit:cover; }
    .chat-box { border:1px solid #ddd; border-radius:8px; padding:10px; margin-top:14px; max-width:720px; }
    .chat-box-header { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
    .chat-box-header img { width:36px; height:36px; border-radius:50%; object-fit:cover; }
    .chat-box-body { height:340px; overflow-y:auto; border:1px solid #eee; border-radius:6px; padding:10px; margin-bottom:10px; background:#fafafa; }
    .chat-form { display:flex; gap:8px; }
    .chat-form input[type="text"] { flex:1; }
    .message { margin:4px 0; }
  </style>
</head>
<body>
<div class="container">

  <div class="header">
    <h1>My Account</h1>
    <div class="header-right">
      <img src="<?= htmlspecialchars($myAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="me">
      <a href="logout.php" class="logout">Logout</a>
    </div>
  </div>
<!-- Création d'un groupe : à insérer dans chat.php (ou une page dédiée) -->
<div class="card" id="create-group-card" style="max-width:560px; margin:16px 0; padding:12px; border:1px solid #ddd; border-radius:10px;">
  <h2 style="margin:6px 0 12px;">Créer un groupe</h2>
  <form id="create-group-form">
    <label for="group_name"><strong>Nom du groupe</strong></label><br>
    <input type="text" id="group_name" name="name" placeholder="Ex: 3ème B - Histoire" required style="width:100%; margin:6px 0 12px; padding:8px;">

    <details style="margin-bottom:8px;">
      <summary><strong>Ajouter des membres</strong> (soit via liste, soit en tapant des noms séparés par des virgules)</summary>
      <div style="margin-top:8px;">
        <div id="user-multi" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;"></div>
        <small id="user-hint" style="display:block; margin-bottom:8px;">Astuce : si la liste n’apparaît pas, utilisez le champ ci-dessous.</small>
        <label for="members_csv"><em>Membres (séparés par des virgules)</em></label><br>
        <input type="text" id="members_csv" placeholder="alice, bob, charles" style="width:100%; margin:6px 0 8px; padding:8px;">
      </div>
    </details>

    <button type="submit">Créer</button>
    <span id="create-group-status" style="margin-left:8px;"></span>
  </form>
</div>
<!-- Groups UI v2: with logs + periodic refresh for messages -->
<div class="groups-panel" style="margin:12px 0;">
  <h2 style="margin:0 0 8px;">Groupes</h2>
  <div id="groups-list">Chargement…</div>
  <div id="groups-error" style="color:#b00020;margin-top:6px;"></div>
</div>

<div class="chat-box" id="group-chat" style="display:none; margin:12px 0;">
  <div class="chat-box-header" style="display:flex; align-items:center; gap:8px;">
    <h3 id="group-title" style="margin:0;"></h3>
    <small id="group-id-badge" style="opacity:.7;"></small>
  </div>
  <div class="chat-box-body" id="group-chat-body" style="height:340px; overflow-y:auto; border:1px solid #ddd; border-radius:6px; padding:10px; margin:10px 0;"></div>
  <form class="chat-form" id="group-chat-form" style="display:flex; gap:8px;">
    <input type="hidden" id="group_id">
    <input type="text" id="group_message" placeholder="Votre message..." style="flex:1;">
    <button type="submit">Envoyer</button>
  </form>
  <div id="group-chat-error" style="color:#b00020;margin-top:6px;"></div>
</div>

<!-- Minimal UI snippet to list groups and open a group chat (to integrate into chat.php) -->
<div class="groups-panel">
  <h2>Groupes</h2>
  <div id="groups-list"></div>
</div>

<div class="chat-box" id="group-chat" style="display:none;">
  <div class="chat-box-header">
    <h3 id="group-title"></h3>
  </div>
  <div class="chat-box-body" id="group-chat-body" style="height:340px; overflow-y:auto; border:1px solid #ddd; border-radius:6px; padding:10px; margin-bottom:10px;"></div>
  <form class="chat-form" id="group-chat-form">
    <input type="hidden" id="group_id">
    <input type="text" id="group_message" placeholder="Votre message...">
    <button type="submit">Envoyer</button>
  </form>
</div>

  <div class="account-info">
    <div class="welcome">
      <h2>Welcome, <?= htmlspecialchars(ucfirst($currentUser), ENT_QUOTES, 'UTF-8') ?>!</h2>
    </div>

    <div class="user-list">
      <h2>Select a User to Chat With:</h2>
      <ul>
        <?php
        $listStmt = $db->prepare("
          SELECT username, avatar, city, country
          FROM users
          WHERE username <> :me
          ORDER BY username ASC
        ");
        $listStmt->execute([':me' => $currentUser]);

        while ($row = $listStmt->fetch()):
          $u       = $row['username'];
          $label   = ucfirst($u);
          $img     = $row['avatar'] ?: 'uploads/avatars/default.png';
          $city    = $row['city'] ?? '';
          $country = $row['country'] ?? '';
          $href    = 'chat.php?user=' . rawurlencode($u);
          $loc     = trim($city . (($city && $country) ? ', ' : '') . $country);
        ?>
          <li class="user-item" data-username="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>">
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="avatar">
            <div class="user-meta">
              <a href="<?= $href ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
              <?php if ($loc): ?><small style="opacity:.8;"><?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
              <span class="badge-unread">Nouveau message</span>
            </div>
          </li>
        <?php endwhile; ?>
      </ul>
    </div>
  </div>

  <?php if ($showChatBox && $peer): ?>
    <?php
      $peerName   = $peer['username'];
      $peerLabel  = ucfirst($peerName);
      $peerAvatar = $peer['avatar'] ?: 'uploads/avatars/default.png';
    ?>
    <div class="chat-box" id="chat-box">
      <div class="chat-box-header">
        <img src="<?= htmlspecialchars($peerAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="peer">
        <h2 style="margin:0;"><?= htmlspecialchars($peerLabel, ENT_QUOTES, 'UTF-8') ?></h2>
        <button class="close-btn" style="margin-left:auto;" onclick="closeChat()">✖</button>
      </div>

      <div class="chat-box-body" id="chat-box-body"><!-- messages via AJAX --></div>

      <form class="chat-form" id="chat-form" autocomplete="off">
        <input type="hidden" id="sender"   value="<?= htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" id="receiver" value="<?= htmlspecialchars($peerName, ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" id="message" placeholder="Type your message..." required>
        <button type="submit">Send</button>
      </form>
    </div>
  <?php endif; ?>

</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>

let groupMsgTimer = null;

function logErr(where, err) {
  console.error('[GroupsUI]', where, err);
  const box = document.getElementById(where);
  if (box) box.textContent = (typeof err === 'string') ? err : (err && err.message) || 'Erreur';
}

async function loadGroups(){
  try{
    const r = await fetch('list_groups.php');
    if(!r.ok){ logErr('groups-error', 'list_groups.php ' + r.status); return; }
    const data = await r.json();
    if(!data.ok){ logErr('groups-error', data.error || 'Erreur inconnue'); return; }
    const cont = document.getElementById('groups-list');
    cont.innerHTML = '';
    if(!data.groups.length){ cont.textContent = 'Aucun groupe.'; return; }
    data.groups.forEach(g=>{
      const a = document.createElement('a');
      a.href = '#';
      a.textContent = g.name + (g.unread ? ` (${g.unread})` : '');
      a.onclick = (e)=>{ e.preventDefault(); openGroup(g.id, g.name); };
      const div = document.createElement('div');
      div.appendChild(a);
      cont.appendChild(div);
    });
  }catch(e){
    logErr('groups-error', e);
  }
}

async function openGroup(id, name){
  clearInterval(groupMsgTimer);
  document.getElementById('group_id').value = id;
  document.getElementById('group-title').textContent = name;
  document.getElementById('group-id-badge').textContent = '(#' + id + ')';
  document.getElementById('group-chat').style.display = 'block';
  await fetchGroupMessages();
  groupMsgTimer = setInterval(fetchGroupMessages, 4000);
}

async function fetchGroupMessages(){
  const gid = document.getElementById('group_id').value;
  try{
    const r = await fetch('fetch_group_messages.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`group_id=${encodeURIComponent(gid)}`});
    if(!r.ok){
      const txt = await r.text();
      logErr('group-chat-error', `fetch_group_messages.php ${r.status} — ${txt}`);
      return;
    }
    const html = await r.text();
    const box = document.getElementById('group-chat-body');
    box.innerHTML = html;
    box.scrollTop = box.scrollHeight;
    document.getElementById('group-chat-error').textContent = '';
  }catch(e){
    logErr('group-chat-error', e);
  }
}

document.getElementById('group-chat-form').addEventListener('submit', async function(e){
  e.preventDefault();
  const gid = document.getElementById('group_id').value;
  const msg = document.getElementById('group_message').value.trim();
  if(!msg) return;
  try{
    const r = await fetch('submit_group_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`group_id=${encodeURIComponent(gid)}&message=${encodeURIComponent(msg)}`});
    const data = await r.json();
    if(!data.ok){
      logErr('group-chat-error', data.error || 'Erreur inconnue');
      return;
    }
    document.getElementById('group_message').value = '';
    await fetchGroupMessages();
    loadGroups();
  }catch(e){
    logErr('group-chat-error', e);
  }
});

// init
loadGroups();
setInterval(loadGroups, 6000);
async function loadUsersForMulti(){
  try{
    const res = await fetch('list_users.php');
    if(!res.ok) return;
    const data = await res.json();
    if(!data.ok) return;
    const cont = document.getElementById('user-multi');
    cont.innerHTML = '';
    data.users.forEach(u => {
      const label = document.createElement('label');
      label.style.border = '1px solid #ddd';
      label.style.borderRadius = '999px';
      label.style.padding = '6px 10px';
      label.style.cursor = 'pointer';
      label.style.userSelect = 'none';
      label.style.display = 'inline-flex';
      label.style.alignItems = 'center';
      label.style.gap = '6px';
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = u;
      label.appendChild(cb);
      label.appendChild(document.createTextNode(' '+u));
      cont.appendChild(label);
    });
  }catch(e){ /* silencieux: fallback CSV */ }
}

document.getElementById('create-group-form').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const status = document.getElementById('create-group-status');
  status.textContent = '...';

  const name = document.getElementById('group_name').value.trim();
  // collect via checkboxes
  const chosen = Array.from(document.querySelectorAll('#user-multi input[type=checkbox]:checked')).map(cb => cb.value);
  // merge with CSV fallback
  const csv = document.getElementById('members_csv').value.trim();
  if(csv){
    csv.split(',').map(s => s.trim()).filter(Boolean).forEach(v => { if(!chosen.includes(v)) chosen.push(v) });
  }

  // build x-www-form-urlencoded body
  const body = new URLSearchParams();
  body.set('name', name);
  chosen.forEach(m => body.append('members[]', m));

  try{
    const res = await fetch('create_group.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
    const data = await res.json();
    if(data.ok){
      status.textContent = 'Groupe créé ✔';
      // reset + refresh éventuelle liste des groupes si présente
      e.target.reset();
      if (typeof loadGroups === 'function') loadGroups();
    }else{
      status.textContent = 'Erreur: ' + (data.error || 'inconnue');
    }
  }catch(err){
    status.textContent = 'Erreur réseau';
  }
});

// Essaye de charger la liste des utilisateurs (si l'endpoint existe)
loadUsersForMulti();
  function loadGroups(){
  fetch('list_groups.php').then(r=>r.json()).then(data=>{
    if(!data.ok) return;
    const cont = document.getElementById('groups-list');
    cont.innerHTML = '';
    data.groups.forEach(g=>{
      const a = document.createElement('a');
      a.href = '#';
      a.textContent = g.name + (g.unread ? ` (${g.unread})` : '');
      a.onclick = (e)=>{ e.preventDefault(); openGroup(g.id, g.name); };
      const div = document.createElement('div');
      div.appendChild(a);
      cont.appendChild(div);
    });
  });
}

function openGroup(id, name){
  document.getElementById('group_id').value = id;
  document.getElementById('group-title').textContent = name;
  document.getElementById('group-chat').style.display = 'block';
  fetchGroupMessages();
}

function fetchGroupMessages(){
  const gid = document.getElementById('group_id').value;
  fetch('fetch_group_messages.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`group_id=${encodeURIComponent(gid)}`})
    .then(r=>r.text()).then(html=>{
      const box = document.getElementById('group-chat-body');
      box.innerHTML = html;
      box.scrollTop = box.scrollHeight;
    });
}

document.getElementById('group-chat-form').addEventListener('submit', function(e){
  e.preventDefault();
  const gid = document.getElementById('group_id').value;
  const msg = document.getElementById('group_message').value.trim();
  if(!msg) return;
  fetch('submit_group_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`group_id=${encodeURIComponent(gid)}&message=${encodeURIComponent(msg)}`})
    .then(r=>r.json()).then(_=>{
      document.getElementById('group_message').value = '';
      fetchGroupMessages(); 
      loadGroups();
    });
});

loadGroups();
setInterval(loadGroups, 5000);

function closeChat(){ const box=document.getElementById("chat-box"); if(box) box.style.display="none"; }

// Charge les messages et (côté serveur) marque comme lus les entrants
function fetchMessages(){
  const sender=$('#sender').val(), receiver=$('#receiver').val();
  if(!sender||!receiver) return;
  $.post('fetch_messages.php', {sender, receiver}, function(html){
    $('#chat-box-body').html(html);
    const box=$('#chat-box-body'); box.scrollTop(box.prop("scrollHeight"));
  });
}

// Rafraîchit les badges “non lus”
function refreshUnreadBadges(){
  $.getJSON('unread_counts.php', function(resp){
    if(!resp || !resp.ok) return;
    $('.badge-unread').hide().text('Nouveau message');
    const counts = resp.counts || {};
    Object.keys(counts).forEach(function(sender){
      const n = counts[sender];
      if(n>0){
        const badge = $('.user-item[data-username="'+ sender.replace(/"/g,'&quot;') +'"] .badge-unread');
        if(badge.length){
          badge.text(n===1 ? 'Nouveau message' : (n+' nouveaux messages')).show();
        }
      }
    });
  });
}

$(function(){
  // Si la chatbox est là, on rafraîchit
  if(document.getElementById('chat-form')){
    fetchMessages();
    setInterval(fetchMessages, 3000);

    $('#chat-form').on('submit', function(e){
      e.preventDefault();
      const sender=$('#sender').val(), receiver=$('#receiver').val(), message=$('#message').val();
      if(!message.trim()) return;
      $.post('submit_message.php', {sender,receiver,message}, function(){
        $('#message').val(''); fetchMessages(); refreshUnreadBadges();
      }, 'json').fail(function(){ $('#message').val(''); fetchMessages(); refreshUnreadBadges(); });
    });
  }

  // Badges non lus (global)
  refreshUnreadBadges();
  setInterval(refreshUnreadBadges, 5000);
});
</script>
</body>
</html>
