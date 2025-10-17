<?php
session_start();
$conn = new mysqli("localhost", "root", "", "gestion_academique");

$division_id = $_GET['division_id'] ?? 0;

$result = $conn->query("SELECT id, nom FROM niveau WHERE id_division = $division_id ORDER BY nom");
$niveaux = [];

while ($row = $result->fetch_assoc()) {
    $niveaux[] = $row;
}

header('Content-Type: application/json');
echo json_encode($niveaux);
?>