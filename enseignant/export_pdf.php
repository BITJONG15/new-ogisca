<?php
session_start();
require_once("../include/db.php");
require_once("../tcpdf_min/tcpdf.php");

if (!isset($_SESSION['matricule'])) {
    die("Non autorisé");
}

$matricule = $_SESSION['matricule'];

// Récupérer enseignant
$stmt = $conn->prepare("SELECT id, nom, prenom FROM enseignants WHERE matricule = ?");
$stmt->bind_param("s", $matricule);
$stmt->execute();
$enseignant = $stmt->get_result()->fetch_assoc();
if (!$enseignant) die("Enseignant introuvable.");
$enseignant_id = $enseignant['id'];
$enseignant_nom = $enseignant['nom'] . " " . $enseignant['prenom'];

// EC et infos niveau/division
$ec_id = $_GET['ec'] ?? '';
$stmt = $conn->prepare("SELECT Nom_EC, niveau, division FROM element_constitutif WHERE ID_EC = ?");
$stmt->bind_param("s", $ec_id);
$stmt->execute();
$ec = $stmt->get_result()->fetch_assoc();
if (!$ec) die("EC introuvable.");

// Récupérer niveau_id et division_id
$stmt = $conn->prepare("SELECT id FROM niveau WHERE nom = ?");
$stmt->bind_param("s", $ec['niveau']);
$stmt->execute();
$niveau = $stmt->get_result()->fetch_assoc();
$niveau_id = $niveau ? $niveau['id'] : 0;
$stmt->close();

$stmt = $conn->prepare("SELECT id FROM division WHERE nom = ?");
$stmt->bind_param("s", $ec['division']);
$stmt->execute();
$division = $stmt->get_result()->fetch_assoc();
$division_id = $division ? $division['id'] : 0;
$stmt->close();

if (!$niveau_id || !$division_id) die("Niveau ou division introuvable.");

// Récupérer étudiants concernés et leurs notes
$stmt = $conn->prepare("SELECT e.nom, e.prenom, n.valeur 
    FROM etudiants e 
    LEFT JOIN notes n ON e.id = n.etudiant_id AND n.ID_EC = ? AND n.enseignant_id = ?
    WHERE e.niveau_id = ? AND e.division_id = ?
    ORDER BY e.nom, e.prenom");
$stmt->bind_param("siii", $ec_id, $enseignant_id, $niveau_id, $division_id);
$stmt->execute();
$etudiants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Création du PDF
$pdf = new TCPDF();
$pdf->SetCreator('OGISCA');
$pdf->SetAuthor($enseignant_nom);
$pdf->SetTitle("Fiche Notes - ".$ec['Nom_EC']);
$pdf->AddPage();

// Titre et entête
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'OGISCA - Fiche de Notes', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, "Cours : ".$ec['Nom_EC'], 0, 1);
$pdf->Cell(0, 8, "Niveau : ".$ec['niveau']." - Division : ".$ec['division'], 0, 1);
$pdf->Cell(0, 8, "Enseignant : ".$enseignant_nom, 0, 1);

$pdf->Ln(5);

// Table des notes
$html = '<table border="1" cellpadding="4">
<tr style="background-color:#007bff; color:#ffffff;"><th>Nom</th><th>Prénom</th><th>Note</th></tr>';
foreach ($etudiants as $e) {
    $note = $e['valeur'] !== null ? $e['valeur'] : '-';
    $html .= "<tr><td>{$e['nom']}</td><td>{$e['prenom']}</td><td>$note</td></tr>";
}
$html .= '</table>';
$pdf->writeHTML($html, true, false, false, false, '');

// QR Code en bas à droite, petit (30x30)
$qrText = "Enseignant: $enseignant_nom\nEC: {$ec['Nom_EC']}\nDate: " . date('Y-m-d H:i');
$pdf->write2DBarcode($qrText, 'QRCODE,H', 160, 260, 30, 30);

$pdf->Output('fiche_notes.pdf', 'I');
