<?php
require_once("../include/db.php");
require_once("../tcpdf_min/tcpdf.php");

session_start();
if (!isset($_SESSION['matricule'])) die("Non autorisé");

$matricule = $_SESSION['matricule'];
$stmt = $conn->prepare("SELECT id, nom, prenom FROM enseignants WHERE matricule = ?");
$stmt->bind_param("s", $matricule);
$stmt->execute();
$enseignant = $stmt->get_result()->fetch_assoc();
$enseignant_nom = $enseignant['nom'] . " " . $enseignant['prenom'];
$enseignant_id = $enseignant['id'];

$ec_id = $_GET['ec'] ?? '';
$stmt = $conn->prepare("SELECT Nom_EC, niveau, division FROM element_constitutif WHERE ID_EC = ?");
$stmt->bind_param("s", $ec_id);
$stmt->execute();
$ec = $stmt->get_result()->fetch_assoc();

$niveau = $ec['niveau'];
$division = $ec['division'];

// Récupération des étudiants avec leurs notes
$stmt = $conn->prepare("SELECT e.nom, e.prenom, n.valeur
    FROM etudiants e
    JOIN notes n ON e.id = n.etudiant_id
    WHERE n.ID_EC = ? AND n.enseignant_id = ?");
$stmt->bind_param("si", $ec_id, $enseignant_id);
$stmt->execute();
$etudiants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Création du PDF
$pdf = new TCPDF();
$pdf->SetCreator('OGISCA');
$pdf->SetAuthor($enseignant_nom);
$pdf->SetTitle('Fiche Notes - ' . $ec['Nom_EC']);
$pdf->AddPage();

// Couleurs bleu et blanc
$colorBlue = [0, 102, 204]; // Bleu moyen
$colorLightBlue = [224, 238, 255]; // Bleu très clair pour fond

// Titre
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(...$colorBlue);
$pdf->Cell(0, 15, 'OGISCA - Fiche de Notes', 0, 1, 'C');
$pdf->Ln(3);

// Infos cours
$pdf->SetFont('helvetica', '', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, "Cours : " . $ec['Nom_EC'], 0, 1);
$pdf->Cell(0, 8, "Niveau : $niveau - Division : $division", 0, 1);
$pdf->Cell(0, 8, "Enseignant : $enseignant_nom", 0, 1);
$pdf->Ln(5);

// Table des notes avec style bleu/blanc
// Construire HTML de la table avec styles inline
$html = '<table cellspacing="0" cellpadding="6" border="1" style="border-color: rgb(0,102,204); border-collapse: collapse; width: 100%;">';
$html .= '<thead style="background-color: rgb(0,102,204); color: white;">';
$html .= '<tr>';
$html .= '<th style="width: 35%; text-align: left;">Nom</th>';
$html .= '<th style="width: 35%; text-align: left;">Prénom</th>';
$html .= '<th style="width: 30%; text-align: center;">Note</th>';
$html .= '</tr></thead><tbody>';

$rowToggle = false;
foreach ($etudiants as $e) {
    $bgcolor = $rowToggle ? 'background-color: rgb(224,238,255);' : '';
    $html .= "<tr style=\"$bgcolor\">";
    $html .= "<td>{$e['nom']}</td>";
    $html .= "<td>{$e['prenom']}</td>";
    $html .= "<td style='text-align: center;'>{$e['valeur']}</td>";
    $html .= "</tr>";
    $rowToggle = !$rowToggle;
}
$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, false, false, '');

// QR Code (taille réduite 25x25 mm) en bas à droite, toujours sur la même page
$qr = "Enseignant: $enseignant_nom\nEC: {$ec['Nom_EC']}\nDate: " . date('Y-m-d H:i');
$style = [
    'border' => 0,
    'padding' => 0,
    'fgcolor' => [0, 0, 0],
    'bgcolor' => false
];

// Position en bas droite (A4 hauteur = 297mm, largeur = 210mm)
// 25 mm carré, positionné à 180 mm X (largeur - QR width - marge droite 10mm)
$qrX = 180;
$qrY = 265;

$pdf->write2DBarcode($qr, 'QRCODE,H', $qrX, $qrY, 25, 25, $style);

// Générer le PDF dans le navigateur
$pdf->Output('fiche_notes.pdf', 'I');
