<?php
// db.php — Connexion PDO centralisée
try {
    $db = new PDO(
        "mysql:host=srv192;dbname=24freddy;charset=utf8",
        "24freddy",
        "freddy",
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
