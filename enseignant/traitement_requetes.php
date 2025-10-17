<?php
require_once("../include/db.php");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $requete_id = intval($_POST['requete_id']);

    if ($action === "resoudre") {
        $note_id = intval($_POST['note_id']);
        $valeur = floatval($_POST['valeur']);

        // Mettre à jour la note
        $stmt = $conn->prepare("UPDATE notes SET valeur=?, date_ajout=NOW() WHERE id=?");
        $stmt->bind_param("di", $valeur, $note_id);
        $stmt->execute();

        // Mettre à jour la requête
        $stmt = $conn->prepare("UPDATE requetes SET statut='validée' WHERE id=?");
        $stmt->bind_param("i", $requete_id);
        $stmt->execute();

        echo json_encode(["success" => true, "requete_id" => $requete_id]);
        exit;
    }

    if ($action === "maintenir") {
        // Mettre le statut à refusée
        $stmt = $conn->prepare("UPDATE requetes SET statut='refusée' WHERE id=?");
        $stmt->bind_param("i", $requete_id);
        $stmt->execute();

        echo json_encode(["success" => true, "requete_id" => $requete_id]);
        exit;
    }
}

echo json_encode(["success" => false, "message" => "Requête invalide"]);
