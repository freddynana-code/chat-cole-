
<?php
// create_group.php — create a group and add initial members
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

$owner = $_SESSION['username'];
$name  = trim($_POST['name'] ?? '');
$members = $_POST['members'] ?? []; // array of usernames (strings)

if ($name === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Missing name']); exit;
}

// Start transaction
$db->beginTransaction();
try {
  // Create group
  $ins = $db->prepare('INSERT INTO groups (name, owner) VALUES (:n, :o)');
  $ins->execute([':n'=>$name, ':o'=>$owner]);
  $group_id = (int)$db->lastInsertId();

  // Add owner as owner role
  $m1 = $db->prepare('INSERT INTO group_members (group_id, username, role) VALUES (:g,:u,:r)');
  $m1->execute([':g'=>$group_id, ':u'=>$owner, ':r'=>'owner']);

  // Add other members (avoid duplicates and owner)
  $add = $db->prepare('INSERT IGNORE INTO group_members (group_id, username, role) VALUES (:g,:u,"member")');
  foreach ($members as $u) {
    $u = trim($u);
    if ($u !== '' && $u !== $owner) {
      $add->execute([':g'=>$group_id, ':u'=>$u]);
    }
  }

  // Initialize group_reads for all members
  $gr = $db->prepare('INSERT IGNORE INTO group_reads (group_id, username, last_read_at) VALUES (:g,:u,NULL)');
  $mems = $db->prepare('SELECT username FROM group_members WHERE group_id = :g');
  $mems->execute([':g'=>$group_id]);
  foreach ($mems->fetchAll() as $row) {
    $gr->execute([':g'=>$group_id, ':u'=>$row['username']]);
  }

  $db->commit();
  echo json_encode(['ok'=>true,'group_id'=>$group_id]);
} catch (Exception $e) {
  $db->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']); 
}
