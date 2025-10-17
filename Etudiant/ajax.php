<?php
$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
$action = $_POST['action'] ?? '';
if($action=='niveaux'){
    $div = $_POST['division'];
    $res = $conn->prepare("SELECT id, nom FROM niveau WHERE id_division=? ORDER BY nom");
    $res->bind_param("i",$div); $res->execute();
    $r = $res->get_result(); $data=[];
    while($row=$r->fetch_assoc()) $data[]=$row;
    echo json_encode($data);
}
if($action=='ec'){
    $niv = $_POST['niveau']; $div = $_POST['division'];
    $res = $conn->prepare("SELECT ID_EC, Nom_EC FROM element_constitutif WHERE id_niveau=? AND division=? ORDER BY Nom_EC");
    $res->bind_param("is",$niv,$div); $res->execute();
    $r = $res->get_result(); $data=[];
    while($row=$r->fetch_assoc()) $data[]=$row;
    echo json_encode($data);
}
