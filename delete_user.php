<?php
session_start();
require_once 'db.php';

// Vérifier si l'admin est connecté ou autorisé
// Ici je suppose que seul un admin peut supprimer. À adapter selon ton système.
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    http_response_code(403);
    exit("Not authorized");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userToDelete = trim($_POST['username'] ?? '');

    if ($userToDelete === '') {
        echo "No username provided.";
        exit;
    }

    // Récupérer l'avatar pour le supprimer du serveur
    $stmt = $db->prepare("SELECT avatar FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $userToDelete]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "User not found.";
        exit;
    }

    // Supprimer d'abord les messages liés
    $delMessages = $db->prepare("DELETE FROM chat_messages WHERE sender = :u OR receiver = :u");
    $delMessages->execute([':u' => $userToDelete]);

    // Supprimer l'utilisateur
    $delUser = $db->prepare("DELETE FROM users WHERE username = :u");
    $delUser->execute([':u' => $userToDelete]);

    // Supprimer l'avatar physique si ce n'est pas le défaut
    if (!empty($user['avatar']) && $user['avatar'] !== 'uploads/avatars/default.png') {
        $filePath = __DIR__ . '/' . $user['avatar'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    echo "User '$userToDelete' deleted successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete User</title>
</head>
<body>
    <h1>Delete User</h1>
    <form method="post">
        <label for="username">Username to delete:</label>
        <input type="text" name="username" id="username" required>
        <button type="submit">Delete</button>
    </form>
</body>
</html>
