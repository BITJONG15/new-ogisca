<?php
// get_palier_details.php
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion à la base de données']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'ID palier invalide']);
    exit;
}

// Récupérer les infos du palier
$stmt = $conn->prepare("SELECT id, nom, division, niveau FROM paliers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Palier non trouvé']);
    exit;
}

$palier = $result->fetch_assoc();

// Récupérer les EC liés
$stmt_ec = $conn->prepare("SELECT ID_EC FROM palier_ec WHERE palier_id = ?");
$stmt_ec->bind_param("i", $id);
$stmt_ec->execute();
$res_ec = $stmt_ec->get_result();

$ec_ids = [];
while ($row = $res_ec->fetch_assoc()) {
    $ec_ids[] = $row['ID_EC'];
}

$palier['ec_ids'] = $ec_ids;

echo json_encode($palier);
