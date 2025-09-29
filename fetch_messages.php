<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['username'])) {
    http_response_code(403);
    exit("You are not logged in");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sender   = isset($_POST['sender'])   ? trim($_POST['sender'])   : '';
    $receiver = isset($_POST['receiver']) ? trim($_POST['receiver']) : '';

    if ($sender !== $_SESSION['username']) {
        http_response_code(403);
        exit("Not authorized");
    }

    $sql = "
        SELECT sender, receiver, message, created_at
        FROM chat_messages
        WHERE (sender = :s1 AND receiver = :r1)
           OR (sender = :s2 AND receiver = :r2)
        ORDER BY created_at ASC, /* si tu as une PK */ id ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':s1' => $sender,
        ':r1' => $receiver,
        ':s2' => $receiver,
        ':r2' => $sender,
    ]);

    while ($row = $stmt->fetch()) {
        $name = $row['sender'] === $sender ? 'You' : ucfirst($row['sender']);
        echo '<div class="message"><strong>'
             . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
             . ':</strong> '
             . nl2br(htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'))
             . '</div>';
    }
}
$upd = $db->prepare("
    UPDATE chat_messages
    SET read_at = NOW()
    WHERE receiver = :me AND sender = :peer AND read_at IS NULL
");
$upd->execute([
    ':me'   => $sender,   // c'est l'utilisateur connecté (côté client: #sender)
    ':peer' => $receiver, // le correspondant
]);