<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "gestion_academique";
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) { 
    die(json_encode(['error' => 'Erreur connexion: '.$conn->connect_error])); 
}

$etudiant_id = $_POST['etudiant_id'] ?? null;
$ID_EC = $_POST['ID_EC'] ?? null;
$modalite = $_POST['modalite'] ?? '';
$motif = $_POST['motif'] ?? '';
$contenu = $_POST['contenu'] ?? '';

// Vérifier champs obligatoires
if (!$etudiant_id || !$ID_EC || !$motif) {
    echo json_encode(['error' => 'Champs manquants']);
    exit;
}

// Upload pièce jointe si présente
$piece_jointe = '';
if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
    $uploads_dir = 'uploads';
    if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0777, true);
    $fileName = time().'_'.basename($_FILES['piece_jointe']['name']);
    $target = $uploads_dir.'/'.$fileName;
    if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $target)) {
        $piece_jointe = $target;
    }
}

// Récupérer l'enseignant associé à l'EC
$professeur = '';
$stmt = $conn->prepare("SELECT e.nom, e.prenom FROM attribution_ec a JOIN enseignants e ON a.id_enseignants = e.id WHERE a.ID_EC = ?");
$stmt->bind_param("s", $ID_EC);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $professeur = $row['nom'].' '.$row['prenom'];
}

// Statut par défaut
$statut = 'en_attente';
$date_envoi = date('Y-m-d');

// Insertion dans la table requetes
$stmt = $conn->prepare("INSERT INTO requetes 
    (etudiant_id, ID_EC, modalite, professeur, motif, contenu, piece_jointe, statut, date_envoi) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssssss", $etudiant_id, $ID_EC, $modalite, $professeur, $motif, $contenu, $piece_jointe, $statut, $date_envoi);

if(!$stmt->execute()){
    echo json_encode(['error' => $stmt->error]);
    exit;
}

// Récupérer la requête insérée pour affichage immédiat
$requete_id = $stmt->insert_id;
$stmt = $conn->prepare("SELECT * FROM requetes WHERE id = ?");
$stmt->bind_param("i", $requete_id);
$stmt->execute();
$newReq = $stmt->get_result()->fetch_assoc();

echo json_encode(['success' => true, 'requete' => $newReq]);
