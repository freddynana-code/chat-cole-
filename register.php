<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['username'])) {
    header("Location: chat.php");
    exit();
}

// chemin dossier upload (assure-toi qu'il existe et est inscriptible)
$AVATAR_DIR = __DIR__ . '/uploads/avatars/';
$AVATAR_URL = 'uploads/avatars/'; // pour l'affichage
$DEFAULT_AVATAR = $AVATAR_URL . 'default.png';

// aide : créer un nom de fichier unique
function make_filename(string $ext): string {
    return sprintf('%s_%s.%s', time(), bin2hex(random_bytes(8)), $ext);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $city     = trim($_POST['city'] ?? '');
    $country  = trim($_POST['country'] ?? '');

    // validations simples
    if ($username === '' || $password === '') {
        $error = "Please fill all required fields.";
    } else {
        // vérifier si username existe
        $stmt = $db->prepare("SELECT 1 FROM users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        if ($stmt->fetch()) {
            $error = "Username already exists";
        } else {
            // gestion avatar
            $avatarPathForDb = $DEFAULT_AVATAR;

            if (!empty($_FILES['avatar']['name'])) {
                if (!is_dir($AVATAR_DIR)) {
                    // tente de créer le dossier si absent
                    @mkdir($AVATAR_DIR, 0775, true);
                }

                if (!is_dir($AVATAR_DIR) || !is_writable($AVATAR_DIR)) {
                    $error = "Upload directory is not writable.";
                } else {
                    if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        // limite taille 2 Mo
                        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                            $error = "Avatar too large (max 2 MB).";
                        } else {
                            // validation MIME
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mime  = $finfo->file($_FILES['avatar']['tmp_name']) ?: '';
                            $allowed = [
                                'image/jpeg' => 'jpg',
                                'image/png'  => 'png',
                                'image/webp' => 'webp',
                            ];

                            if (!isset($allowed[$mime])) {
                                $error = "Invalid avatar format. Allowed: JPG, PNG, WEBP.";
                            } else {
                                $ext = $allowed[$mime];
                                $newName = make_filename($ext);
                                $targetPath = $AVATAR_DIR . $newName;

                                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                                    // Chemin stocké en DB = chemin relatif web
                                    $avatarPathForDb = $AVATAR_URL . $newName;
                                } else {
                                    $error = "Failed to save the avatar.";
                                }
                            }
                        }
                    } else {
                        $error = "Upload error (code: " . (int)$_FILES['avatar']['error'] . ")";
                    }
                }
            }

            // si pas d'erreur jusque-là, on insère
            if (empty($error)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $ins = $db->prepare("
                    INSERT INTO users (username, password, city, country, avatar)
                    VALUES (:u, :p, :c, :co, :a)
                ");
                $ok = $ins->execute([
                    ':u'  => $username,
                    ':p'  => $hashedPassword,
                    ':c'  => $city !== '' ? $city : null,
                    ':co' => $country !== '' ? $country : null,
                    ':a'  => $avatarPathForDb,
                ]);

                if ($ok) {
                    $_SESSION['username'] = $username;
                    header("Location: chat.php");
                    exit();
                } else {
                    $error = "Registration failed";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link href="style_main.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <h1>Register</h1>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" autocomplete="off">
        <label for="username">Username*:</label>
        <input type="text" id="username" name="username" required><br>

        <label for="password">Password*:</label>
        <input type="password" id="password" name="password" required><br>

        <label for="city">City:</label>
        <input type="text" id="city" name="city" placeholder="ex: Charleroi"><br>

        <label for="country">Country:</label>
        <input type="text" id="country" name="country" placeholder="ex: Belgium"><br>

        <label for="avatar">Profile photo (JPG/PNG/WEBP, max 2MB):</label>
        <input type="file" id="avatar" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><br>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a>.</p>
</div>
</body>
</html>
