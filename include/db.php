<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "gestion_academique";

// Connexion
$conn = new mysqli($servername, $username, $password, $database);

// Vérification
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}
?>
