
<?php
// list_users.php — renvoie la liste des usernames pour cocher des membres
// Hypothèse: table `users` avec au moins la colonne `username`.
session_start();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Not logged in']); exit;
}

try{
  // Exclure l'utilisateur courant de la liste (il sera ajouté automatiquement comme owner)
  $me = $_SESSION['username'];
  // Adapter le nom de la table/colonne si besoin (users/username)
  $stmt = $db->prepare('SELECT username FROM users WHERE username <> :me ORDER BY username ASC');
  $stmt->execute([':me'=>$me]);
  $users = array_map(fn($r) => $r['username'], $stmt->fetchAll());
  echo json_encode(['ok'=>true, 'users'=>$users]);
}catch(Exception $e){
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Server error']);
}
