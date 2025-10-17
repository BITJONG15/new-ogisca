<?php
session_start();
header('Content-Type: application/json');
require_once("../include/db.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['matricule'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
    exit;
}

$enseignant_id = intval($data['enseignant_id'] ?? 0);
$ec_id = $conn->real_escape_string($data['ec_id'] ?? '');
$classe_id = intval($data['classe_id'] ?? 0);
$palier_id = intval($data['palier_id'] ?? 0);
$notes = $data['notes'] ?? [];

if (!$enseignant_id || !$ec_id || !$classe_id || !$palier_id || !is_array($notes)) {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants ou invalides']);
    exit;
}

foreach ($notes as $etudiant_id => $valeur) {
    $etudiant_id = intval($etudiant_id);
    $valeur = trim($valeur);
    if ($valeur === '') continue; // Ignore empty

    if (!is_numeric($valeur) || $valeur < 0 || $valeur > 20) {
        echo json_encode(['success' => false, 'error' => "Note invalide pour l'étudiant ID $etudiant_id"]);
        exit;
    }

    // Vérifier si note existe déjà
    $check = $conn->prepare("SELECT id FROM notes WHERE etudiant_id=? AND ID_EC=? AND enseignant_id=?");
    $check->bind_param("isi", $etudiant_id, $ec_id, $enseignant_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => "Note déjà enregistrée pour l'étudiant ID $etudiant_id, modification interdite"]);
        $check->close();
        exit;
    }
    $check->close();

   // Insérer la note
$insert = $conn->prepare("INSERT INTO notes (etudiant_id, classe_id, ID_EC, palier_id, enseignant_id, valeur, date_ajout) VALUES (?, ?, ?, ?, ?, ?, NOW())");
if (!$insert) {
    echo json_encode(['success' => false, 'error' => 'Erreur préparation requête : '.$conn->error]);
    exit;
}
$insert->bind_param("isisid", $etudiant_id, $classe_id, $ec_id, $palier_id, $enseignant_id, $valeur);
if (!$insert->execute()) {
    echo json_encode(['success' => false, 'error' => 'Erreur exécution requête : '.$insert->error]);
    $insert->close();
    exit;
}
$insert->close();
}

echo json_encode(['success' => true]);
