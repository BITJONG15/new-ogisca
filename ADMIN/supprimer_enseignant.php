<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // On peut d'abord récupérer la photo pour supprimer le fichier si besoin
    $res = $conn->query("SELECT photo FROM enseignants WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['photo']) && file_exists($row['photo'])) {
            unlink($row['photo']); // supprime la photo du serveur
        }
    }

    $stmt = $conn->prepare("DELETE FROM enseignants WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        header("Location: dashboard.php?deleted=1");
    } else {
        echo "Erreur lors de la suppression.";
    }

    $stmt->close();
} else {
    echo "ID invalide.";
}

$conn->close();
