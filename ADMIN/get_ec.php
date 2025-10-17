<?php
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'gestion_academique');

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion à la base de données']);
    exit;
}

$niveau = $_GET['niveau'] ?? '';
$division = $_GET['division'] ?? '';

if (!$niveau || !$division) {
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT ec.ID_EC, ec.Nom_EC
    FROM element_constitutif ec
    INNER JOIN niveau n ON ec.id_niveau = n.id
    INNER JOIN division d ON ec.division_id = d.id
    WHERE n.nom = ? AND d.nom = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $niveau, $division);
$stmt->execute();
$result = $stmt->get_result();

$ecs = [];
while ($row = $result->fetch_assoc()) {
    $ecs[] = $row;
}

echo json_encode($ecs);
