<?php
// get_modalites_notes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connexion DB
if (file_exists(__DIR__ . '/include/db.php')) {
    require_once __DIR__ . '/include/db.php';
} else {
    $conn = new mysqli("localhost", "root", "", "gestion_academique");
    if ($conn->connect_error) die("Erreur DB : " . $conn->connect_error);
}

header('Content-Type: application/json');

if (isset($_GET['ID_EC']) && isset($_GET['etudiant_id'])) {
    $ID_EC = trim($_GET['ID_EC']);
    $etudiant_id = intval($_GET['etudiant_id']);
    
    // Récupérer les modalités disponibles
    $stmt = $conn->prepare("SELECT Modalites_Controle FROM element_constitutif WHERE ID_EC = ?");
    $stmt->bind_param("s", $ID_EC);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $modalites = explode(',', $row['Modalites_Controle']);
        $modalites = array_map('trim', $modalites);
        
        // Récupérer les notes existantes
        $notes_existantes = [];
        $stmt_notes = $conn->prepare("SELECT modalite, valeur FROM notes WHERE etudiant_id = ? AND ID_EC = ?");
        $stmt_notes->bind_param("is", $etudiant_id, $ID_EC);
        $stmt_notes->execute();
        $result_notes = $stmt_notes->get_result();
        
        while ($note_row = $result_notes->fetch_assoc()) {
            $notes_existantes[$note_row['modalite']] = floatval($note_row['valeur']);
        }
        $stmt_notes->close();
        
        echo json_encode([
            'success' => true,
            'modalites' => $modalites,
            'notes_existantes' => $notes_existantes
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'EC non trouvé'
        ]);
    }
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Paramètres manquants'
    ]);
}
?>