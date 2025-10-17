<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

$id = $_GET['id'] ?? '';
if($id) {
    $stmt = $conn->prepare("DELETE FROM etudiants WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header("Location: gestionEtudiant.php");
exit;
