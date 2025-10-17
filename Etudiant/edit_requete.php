<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

$id = intval($_POST['id']);
$motif = $_POST['motif'] ?? '';
$contenu = $_POST['contenu'] ?? '';
$piece_jointe = null;

$servername = "localhost";
$username = "root";
$password = "";
$database = "gestion_academique";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) die(json_encode(['success'=>false,'error'=>$conn->connect_error]));

// Gestion pièce jointe
if(!empty($_FILES['piece_jointe']['name'])){
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileName = time() . '_' . basename($_FILES['piece_jointe']['name']);
    move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $uploadDir . $fileName);
    $piece_jointe = $fileName;
}

if($piece_jointe){
    $stmt = $conn->prepare("UPDATE requetes SET motif=?, contenu=?, piece_jointe=? WHERE id=?");
    $stmt->bind_param("sssi", $motif, $contenu, $piece_jointe, $id);
}else{
    $stmt = $conn->prepare("UPDATE requetes SET motif=?, contenu=? WHERE id=?");
    $stmt->bind_param("ssi", $motif, $contenu, $id);
}

if($stmt->execute()){
    echo json_encode(['success'=>true]);
}else{
    echo json_encode(['success'=>false, 'error'=>'Erreur lors de la modification']);
}
