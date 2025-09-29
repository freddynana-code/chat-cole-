
<?php
// remove_group_member.php — owner/admin remove a member
session_start();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Not logged in']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit;
}

$user = $_SESSION['username'];
$group_id = (int)($_POST['group_id'] ?? 0);
$username = trim($_POST['username'] ?? '');

if ($group_id <= 0 || $username === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Missing fields']); exit;
}

$role = $db->prepare('SELECT role FROM group_members WHERE group_id=:g AND username=:u LIMIT 1');
$role->execute([':g'=>$group_id, ':u'=>$user]);
$r = $role->fetch();
if (!$r || !in_array($r['role'], ['owner','admin'], true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Insufficient rights']); exit;
}

// cannot remove owner
$own = $db->prepare('SELECT role FROM group_members WHERE group_id=:g AND username=:u LIMIT 1');
$own->execute([':g'=>$group_id, ':u'=>$username]);
$r2 = $own->fetch();
if ($r2 && $r2['role'] === 'owner') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Cannot remove owner']); exit;
}

$del = $db->prepare('DELETE FROM group_members WHERE group_id=:g AND username=:u');
$del->execute([':g'=>$group_id, ':u'=>$username]);

echo json_encode(['ok'=>true]);
