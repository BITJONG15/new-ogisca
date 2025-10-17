<?php
session_start();
$conn = new mysqli("localhost", "root", "", "gestion_academique");

$niveau_id = $_GET['niveau_id'] ?? 0;

$result = $conn->query("SELECT ID_EC, Nom_EC FROM element_constitutif WHERE id_niveau = $niveau_id ORDER BY Nom_EC");
$ecs = [];

while ($row = $result->fetch_assoc()) {
    $ecs[] = $row;
}

header('Content-Type: application/json');
echo json_encode($ecs);
?>