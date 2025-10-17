<?php
// view_file.php
session_start();
error_reporting(0);

// Vérification session enseignant
if (!isset($_SESSION['matricule']) || !isset($_SESSION['user_id']) || !str_starts_with($_SESSION['matricule'], "EN")) {
    header("HTTP/1.0 403 Forbidden");
    exit();
}

if (isset($_GET['requete_id'])) {
    $requete_id = intval($_GET['requete_id']);
    
    // Connexion DB
    $conn = new mysqli("localhost", "root", "", "gestion_academique");
    if ($conn->connect_error) die("Erreur DB : " . $conn->connect_error);
    
    // Récupérer la pièce jointe depuis la base de données
    $stmt = $conn->prepare("SELECT piece_jointe FROM requetes WHERE id = ?");
    $stmt->bind_param("i", $requete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $piece_jointe = $row['piece_jointe'];
        
        if (!empty($piece_jointe)) {
            // Déterminer le type MIME basé sur l'extension
            $extension = strtolower(pathinfo($piece_jointe, PATHINFO_EXTENSION));
            
            switch($extension) {
                case 'pdf':
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="' . $piece_jointe . '"');
                    break;
                case 'jpg':
                case 'jpeg':
                    header('Content-Type: image/jpeg');
                    break;
                case 'png':
                    header('Content-Type: image/png');
                    break;
                case 'gif':
                    header('Content-Type: image/gif');
                    break;
                default:
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $piece_jointe . '"');
            }
            
            // Ici, vous devez récupérer le contenu du fichier
            // Si le fichier est stocké physiquement sur le serveur
            $file_path = '../Etudiant/uploads/' . $piece_jointe;
            if (file_exists($file_path)) {
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
            } else {
                // Si le fichier n'existe pas physiquement, chercher dans d'autres emplacements
                $possible_paths = [
                    'uploads/requetes/' . $piece_jointe,
                    '../uploads/' . $piece_jointe,
                    'uploads/' . $piece_jointe,
                    $piece_jointe
                ];
                
                $found = false;
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        header('Content-Length: ' . filesize($path));
                        readfile($path);
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    header("HTTP/1.0 404 Not Found");
                    echo "Fichier non trouvé sur le serveur";
                }
            }
            exit;
        }
    }
    
    $stmt->close();
    $conn->close();
}

header("HTTP/1.0 404 Not Found");
echo "Requête ou fichier non trouvé";
?>