<?php
require_once("../include/db.php");
session_start();

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Journaliser le contenu de $_POST pour le débogage
$log_file = '../logs/update_notes_debug.log';
$log_content = "[" . date('Y-m-d H:i:s') . "] Début de traitement\n";
$log_content .= "[" . date('Y-m-d H:i:s') . "] Méthode: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log_content .= "[" . date('Y-m-d H:i:s') . "] POST data: " . print_r($_POST, true) . "\n";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $log_content .= "[" . date('Y-m-d H:i:s') . "] Erreur: Méthode non autorisée\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer l'ID de l'enseignant depuis la session
$enseignant_id = $_SESSION['enseignant_id'] ?? 0;
$log_content .= "[" . date('Y-m-d H:i:s') . "] Enseignant ID: " . $enseignant_id . "\n";

if (!$enseignant_id) {
    $log_content .= "[" . date('Y-m-d H:i:s') . "] Erreur: Enseignant non identifié\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Enseignant non identifié']);
    exit;
}

// Récupérer et valider les paramètres
$ec_id = $_POST['ec_id'] ?? '';
$requete_id = $_POST['requete_id'] ?? '';
$notes = $_POST['notes'] ?? [];

$log_content .= "[" . date('Y-m-d H:i:s') . "] Paramètres reçus:\n";
$log_content .= "[" . date('Y-m-d H:i:s') . "] - EC ID: " . $ec_id . "\n";
$log_content .= "[" . date('Y-m-d H:i:s') . "] - Requête ID: " . $requete_id . "\n";
$log_content .= "[" . date('Y-m-d H:i:s') . "] - Notes: " . print_r($notes, true) . "\n";

// Validation des paramètres requis
if (empty($ec_id)) {
    $log_content .= "[" . date('Y-m-d H:i:s') . "] Erreur: Paramètre EC manquant\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Paramètre EC manquant']);
    exit;
}

if (empty($requete_id)) {
    $log_content .= "[" . date('Y-m-d H:i:s') . "] Erreur: Paramètre requête manquant\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Paramètre requête manquant']);
    exit;
}

if (empty($notes)) {
    $log_content .= "[" . date('Y-m-d H:i:s') . "] Erreur: Aucune note à mettre à jour\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Aucune note à mettre à jour']);
    exit;
}

try {
    $conn->begin_transaction();
    $log_content .= "[" . date('Y-m-d H:i:s') . "] Début de transaction\n";
    
    foreach ($notes as $etudiant_id => $modalites_notes) {
        $etudiant_id = intval($etudiant_id);
        $log_content .= "[" . date('Y-m-d H:i:s') . "] Traitement étudiant ID: " . $etudiant_id . "\n";
        
        foreach ($modalites_notes as $modalite => $valeur) {
            $modalite = strtoupper(trim($modalite));
            $valeur = trim($valeur) === '' ? null : floatval($valeur);
            
            $log_content .= "[" . date('Y-m-d H:i:s') . "] - Modalité: " . $modalite . ", Valeur: " . $valeur . "\n";
            
            // Vérifier si la note existe déjà
            $check_stmt = $conn->prepare("SELECT id FROM notes WHERE etudiant_id = ? AND ID_EC = ? AND modalite = ? AND enseignant_id = ?");
            $check_stmt->bind_param("issi", $etudiant_id, $ec_id, $modalite, $enseignant_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $log_content .= "[" . date('Y-m-d H:i:s') . "] - Note existante trouvée, mise à jour\n";
                
                // Mettre à jour la note existante
                if ($valeur === null) {
                    $update_stmt = $conn->prepare("DELETE FROM notes WHERE etudiant_id = ? AND ID_EC = ? AND modalite = ? AND enseignant_id = ?");
                    $update_stmt->bind_param("issi", $etudiant_id, $ec_id, $modalite, $enseignant_id);
                } else {
                    $update_stmt = $conn->prepare("UPDATE notes SET valeur = ? WHERE etudiant_id = ? AND ID_EC = ? AND modalite = ? AND enseignant_id = ?");
                    $update_stmt->bind_param("dissi", $valeur, $etudiant_id, $ec_id, $modalite, $enseignant_id);
                }
                $update_stmt->execute();
                $update_stmt->close();
            } else if ($valeur !== null) {
                $log_content .= "[" . date('Y-m-d H:i:s') . "] - Nouvelle note, insertion\n";
                
                // Récupérer les informations nécessaires pour l'insertion
                $ec_info_stmt = $conn->prepare("SELECT id_niveau, division FROM element_constitutif WHERE ID_EC = ?");
                $ec_info_stmt->bind_param("s", $ec_id);
                $ec_info_stmt->execute();
                $ec_info = $ec_info_stmt->get_result()->fetch_assoc();
                $ec_info_stmt->close();
                
                if (!$ec_info) {
                    throw new Exception("Informations de l'EC non trouvées");
                }
                
                // Récupérer le palier_id
                $palier_stmt = $conn->prepare("SELECT p.id FROM paliers p JOIN palier_ec pec ON pec.palier_id = p.id WHERE pec.ID_EC = ? LIMIT 1");
                $palier_stmt->bind_param("s", $ec_id);
                $palier_stmt->execute();
                $palier_result = $palier_stmt->get_result();
                $palier_row = $palier_result->fetch_assoc();
                $palier_id = $palier_row['id'] ?? null;
                $palier_stmt->close();
                
                if ($palier_id) {
                    // Insérer une nouvelle note
                    $insert_stmt = $conn->prepare("
                        INSERT INTO notes (etudiant_id, ID_EC, palier_id, enseignant_id, niveau_id, division_id, modalite, valeur, date_ajout)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $niveau_id = $ec_info['id_niveau'];
                    $division_id = $ec_info['division'];
                    
                    $insert_stmt->bind_param(
                        "isiisisd",
                        $etudiant_id,
                        $ec_id,
                        $palier_id,
                        $enseignant_id,
                        $niveau_id,
                        $division_id,
                        $modalite,
                        $valeur
                    );
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
            }
            
            $check_stmt->close();
        }
    }
    
    // Mettre à jour le statut de la requête à "validée"
    $update_requete_stmt = $conn->prepare("UPDATE requetes SET statut = 'validée' WHERE id = ?");
    $update_requete_stmt->bind_param("i", $requete_id);
    $update_requete_stmt->execute();
    $update_requete_stmt->close();
    
    $log_content .= "[" . date('Y-m-d H:i:s') . "] Statut de la requête mis à jour\n";
    
    $conn->commit();
    $log_content .= "[" . date('Y-m-d H:i:s') . "] Transaction commitée avec succès\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
    
    echo json_encode(['success' => true, 'message' => 'Notes mises à jour avec succès']);
    
} catch (Exception $e) {
    $conn->rollback();
    $log_content .= "[" . date('Y-m-d H:i:s') . "] Erreur: " . $e->getMessage() . "\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}