
<?php
// add_group_member.php — owner or admin can add members
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

$user   = $_SESSION['username'];
$group_id = (int)($_POST['group_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$role     = 'member';

if ($group_id <= 0 || $username === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Missing fields']); exit;
}

// Check permission
$perm = $db->prepare('SELECT role FROM group_members WHERE group_id=:g AND username=:u LIMIT 1');
$perm->execute([':g'=>$group_id, ':u'=>$user]);
$row = $perm->fetch();
if (!$row || !in_array($row['role'], ['owner','admin'], true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Insufficient rights']); exit;
}

// Add member
$ins = $db->prepare('INSERT IGNORE INTO group_members (group_id, username, role) VALUES (:g,:u,:r)');
$ins->execute([':g'=>$group_id, ':u'=>$username, ':r'=>$role]);

// Ensure group_reads row
$gr = $db->prepare('INSERT IGNORE INTO group_reads (group_id, username, last_read_at) VALUES (:g,:u,NULL)');
$gr->execute([':g'=>$group_id, ':u'=>$username]);

echo json_encode(['ok'=>true]);
