<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$database = "gestion_academique";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) { die("Erreur connexion : " . $conn->connect_error); }

if (isset($_GET['ID_EC'])) {
    $ID_EC = $_GET['ID_EC'];

    // Récupérer l'enseignant attribué à cet EC
    $sql = "SELECT ens.nom, ens.prenom, ec.Modalites_Controle 
            FROM attribution_ec ae
            JOIN enseignants ens ON ae.id_enseignants = ens.id
            JOIN element_constitutif ec ON ae.ID_EC = ec.ID_EC
            WHERE ae.ID_EC = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ID_EC);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        echo json_encode($result);
    } else {
        echo json_encode([]);
    }
}
?>
