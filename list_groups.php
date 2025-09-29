# Patch: fix a PHP syntax error in list_groups.php and provide a more robust groups UI snippet (v2)
from pathlib import Path
from textwrap import dedent

base = Path("/mnt/data")

# Fixed list_groups.php ('=>' instead of ':' for array key)
fixed_list_groups = dedent("""
<?php
// list_groups.php — list groups the current user is a member of + unread counts
session_start();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Not logged in']); exit;
}

$user = $_SESSION['username'];

$groups = $db->prepare('
  SELECT g.id, g.name, g.owner, g.created_at
  FROM group_members gm
  JOIN groups g ON g.id = gm.group_id
  WHERE gm.username = :u
  ORDER BY g.created_at DESC
');
$groups->execute([':u'=>$user]);
$out = [];
while ($g = $groups->fetch()) {
  // unread count since last_read_at
  $lr = $db->prepare('SELECT last_read_at FROM group_reads WHERE group_id=:g AND username=:u');
  $lr->execute([':g'=>$g['id'], ':u'=>$user]);
  $last = $lr->fetchColumn();
  if ($last) {
    $uc = $db->prepare('SELECT COUNT(*) FROM group_messages WHERE group_id=:g AND created_at > :t');
    $uc->execute([':g'=>$g['id'], ':t'=>$last]);
  } else {
    $uc = $db->prepare('SELECT COUNT(*) FROM group_messages WHERE group_id=:g');
    $uc->execute([':g'=>$g['id']]);
  }
  $unread = (int)$uc->fetchColumn();
  $out[] = ['id'=>(int)$g['id'], 'name'=>$g['name'], 'owner'=>$g['owner'], 'unread'=>$unread];
}

echo json_encode(['ok'=>true, 'groups'=>$out]);
""")
(base / "list_groups.php").write_text(fixed_list_groups, encoding="utf-8")

# More robust UI snippet that logs errors and refreshes messages
ui_v2 = dedent("""
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
</script>
""")

(base / "groups_ui_snippet_v2.html").write_text(ui_v2, encoding="utf-8")

sorted(["list_groups.php", "groups_ui_snippet_v2.html"])

