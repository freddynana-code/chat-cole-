
<?php
// submit_group_message.php — post a message into a group
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
$message  = trim($_POST['message'] ?? '');

if ($group_id <= 0 || $message === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Missing fields']); exit;
}

// Ensure membership
$chk = $db->prepare('SELECT 1 FROM group_members WHERE group_id=:g AND username=:u');
$chk->execute([':g'=>$group_id, ':u'=>$user]);
if (!$chk->fetchColumn()) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Not a member']); exit;
}

// Insert
$ins = $db->prepare('INSERT INTO group_messages (group_id, sender, message) VALUES (:g,:s,:m)');
$ins->execute([':g'=>$group_id, ':s'=>$user, ':m'=>$message]);

echo json_encode(['ok'=>true]);
