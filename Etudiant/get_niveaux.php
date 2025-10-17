<?php
$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) die("Erreur de connexion : " . $conn->connect_error);

if(isset($_GET['division_id']) && isset($_GET['niveau_id'])){
    $division_id = intval($_GET['division_id']);
    $niveau_id = intval($_GET['niveau_id']);

    $stmt = $conn->prepare("SELECT ID_EC, Nom_EC FROM element_constitutif WHERE division=? AND id_niveau=? ORDER BY Nom_EC ASC");
    $stmt->bind_param("ii", $division_id, $niveau_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">-- Choisir --</option>';
    while($row = $result->fetch_assoc()){
        echo '<option value="'.$row['ID_EC'].'">'.$row['Nom_EC'].'</option>';
    }
}
?>
