<?php
session_start();

// On détruit toutes les variables de session
$_SESSION = [];

// On détruit la session
session_destroy();

// On redirige vers la page de connexion (ou d’accueil)
header('Location: ../login.php');
exit;
    