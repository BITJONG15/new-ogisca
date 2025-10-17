<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "gestion_academique";
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) { die("Erreur connexion : " . $conn->connect_error); }

if (isset($_GET['ID_EC'])) {
    $id_ec = intval($_GET['ID_EC']);
    $sql = "SELECT ec.Modalites_Controle, ec.id_enseignants, ens.nom, ens.prenom
            FROM element_constitutif ec
            JOIN enseignants ens ON ens.id = ec.id_enseignants
            WHERE ec.ID_EC = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_ec);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    echo json_encode($result);
}
?>
