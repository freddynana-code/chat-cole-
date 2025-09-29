<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$sender   = isset($_POST['sender'])   ? trim($_POST['sender'])   : '';
$receiver = isset($_POST['receiver']) ? trim($_POST['receiver']) : '';
$message  = isset($_POST['message'])  ? trim($_POST['message'])  : '';

// sécurité : l’expéditeur doit être la session
if ($sender !== $_SESSION['username']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

// petites validations
if ($sender === '' || $receiver === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing fields']);
    exit;
}

// limite simple pour éviter les floods (ajuste si besoin)
if (mb_strlen($message) > 2000) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Message too long']);
    exit;
}

// vérifier que le destinataire existe
$u = $db->prepare("SELECT 1 FROM users WHERE username = :u LIMIT 1");
$u->execute([':u' => $receiver]);
if (!$u->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Receiver not found']);
    exit;
}

// insérer le message
$ins = $db->prepare("
    INSERT INTO chat_messages (sender, receiver, message, created_at)
    VALUES (:s, :r, :m, NOW())
");
$ins->execute([':s' => $sender, ':r' => $receiver, ':m' => $message]);

http_response_code(201);
echo json_encode(['ok' => true]);
