<?php
$conn = new mysqli("localhost", "root", "", "gestion_academique");
if ($conn->connect_error) {
    http_response_code(500);
    die("Erreur de connexion à la base");
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID manquant']);
    exit;
}

$id = intval($_GET['id']);

$sql = "SELECT e.*, n.nom AS niveau_nom, d.nom AS division_nom 
        FROM etudiants e
        LEFT JOIN niveau n ON e.niveau_id = n.id
        LEFT JOIN division d ON e.division_id = d.id
        WHERE e.id = $id
        LIMIT 1";

$result = $conn->query($sql);

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Étudiant non trouvé']);
    exit;
}

$data = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($data);
