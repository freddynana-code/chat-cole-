
<?php
// fetch_group_messages.php — fetch messages for a group and mark as read
session_start();
require_once 'db.php';

if (!isset($_SESSION['username'])) {
  http_response_code(403);
  exit('Not logged in');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method not allowed');
}

$user = $_SESSION['username'];
$group_id = (int)($_POST['group_id'] ?? 0);
if ($group_id <= 0) {
  http_response_code(400);
  exit('Missing group_id');
}

// Ensure membership
$chk = $db->prepare('SELECT 1 FROM group_members WHERE group_id=:g AND username=:u');
$chk->execute([':g'=>$group_id, ':u'=>$user]);
if (!$chk->fetchColumn()) {
  http_response_code(403);
  exit('Not a member');
}

// Fetch messages
$sel = $db->prepare('
  SELECT gm.id, gm.sender, gm.message, gm.created_at
  FROM group_messages gm
  WHERE gm.group_id = :g
  ORDER BY gm.created_at ASC, gm.id ASC
');
$sel->execute([':g'=>$group_id]);

$html = '';
while ($row = $sel->fetch()) {
  $name = htmlspecialchars($row['sender'], ENT_QUOTES, 'UTF-8');
  $msg  = nl2br(htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'));
  $time = htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8');
  $html .= '<div class="message"><strong>'.$name.'</strong> — <small>'.$time.'</small><br>'.$msg.'</div>';
}

// Mark read
$upd = $db->prepare('UPDATE group_reads SET last_read_at = NOW() WHERE group_id=:g AND username=:u');
$upd->execute([':g'=>$group_id, ':u'=>$user]);

header('Content-Type: text/html; charset=utf-8');
echo $html;
