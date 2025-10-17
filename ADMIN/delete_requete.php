<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID manquant']);
    exit;
}

$id = intval($_POST['id']);

$servername = "localhost";
$username = "root";
$password = "";
$database = "gestion_academique";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) die(json_encode(['success'=>false,'error'=>$conn->connect_error]));

// Supprime la requête
$stmt = $conn->prepare("DELETE FROM requetes WHERE id=?");
$stmt->bind_param("i", $id);
if($stmt->execute()){
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'error'=>'Erreur suppression']);
}
