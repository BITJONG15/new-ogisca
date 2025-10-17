<?php
session_start();
$conn = new mysqli("localhost", "root", "", "gestion_academique");

$id = $_GET['id'] ?? 0;

// Récupérer le palier
$result = $conn->query("SELECT * FROM paliers WHERE id = $id");
$palier = $result->fetch_assoc();

// Récupérer les ECs associés
$result_ec = $conn->query("SELECT ID_EC FROM palier_ec WHERE palier_id = $id");
$ecs = [];
while ($row = $result_ec->fetch_assoc()) {
    $ecs[] = $row['ID_EC'];
}

// Trouver l'ID de division correspondant au nom
$division_id = null;
$divisions = $conn->query("SELECT id, nom FROM division");
while ($d = $divisions->fetch_assoc()) {
    if ($d['nom'] === $palier['division']) {
        $division_id = $d['id'];
        break;
    }
}

// Trouver l'ID de niveau correspondant au nom
$niveau_id = null;
$niveaux = $conn->query("SELECT id, nom FROM niveau");
while ($n = $niveaux->fetch_assoc()) {
    if ($n['nom'] === $palier['niveau']) {
        $niveau_id = $n['id'];
        break;
    }
}

$response = [
    'id' => $palier['id'],
    'nom' => $palier['nom'],
    'division_id' => $division_id,
    'niveau_id' => $niveau_id,
    'ecs' => $ecs
];

header('Content-Type: application/json');
echo json_encode($response);
?>