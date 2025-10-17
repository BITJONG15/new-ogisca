<?php
function checkAdminSession() {
    session_start();
    
    if (!isset($_SESSION['matricule']) || empty($_SESSION['matricule'])) {
        header("Location: login.php");
        exit();
    }
    
    // Vérifier en base de données si l'utilisateur est admin
    $conn = new mysqli('localhost', 'root', '', 'gestion_academique');
    if ($conn->connect_error) return false;
    
    $matricule = $_SESSION['matricule'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE matricule = ?");
    $stmt->bind_param("s", $matricule);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    $user = $result->fetch_assoc();
    return in_array($user['role'], ['admin', 'administrateur', 'ADM']);
}