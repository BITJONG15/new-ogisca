<?php
session_start();

function redirectToLogin() {
    header("Location: ../login.php");
    exit;
}

// Vérifier existence de session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['matricule'])) {
    redirectToLogin();
}

$matricule = $_SESSION['matricule'];
$prefix = strtoupper(substr($matricule, 0, 2)); // EN, AD, ET (2 caractères)

// Vérifier que l'utilisateur est bien autorisé
$allowed_prefixes = ['EN', 'AD', 'ET']; // AD pour ADM
if (!in_array($prefix, $allowed_prefixes)) {
    redirectToLogin();
}
?>