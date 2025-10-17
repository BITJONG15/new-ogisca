<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$database = "gestion_academique";

// Connexion
$conn = new mysqli($servername, $username, $password, $database);

// Vérification
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

// Vérifier que l'utilisateur est un enseignant connecté
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ENSEIGNANT') {
    header('Location: ../login.php');
    exit();
}

// Vérifier les paramètres requis
if (!isset($_GET['ec']) || !isset($_GET['etudiant'])) {
    die("Paramètres manquants. Veuillez spécifier un élément constitutif et un étudiant.");
}

$ec_id = $_GET['ec'];
$etudiant_id = $_GET['etudiant'];

// Récupérer les informations de l'élément constitutif
$stmt = $conn->prepare("SELECT * FROM element_constitutif WHERE ID_EC = ?");
$stmt->bind_param("s", $ec_id);
$stmt->execute();
$result = $stmt->get_result();
$ec = $result->fetch_assoc();

if (!$ec) {
    die("Élément constitutif non trouvé.");
}

// Récupérer les modalités de contrôle
$modalites = explode(',', $ec['Modalites_Controle']);

// Récupérer l'enseignant connecté
$enseignant_user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM enseignants WHERE user_id = ?");
$stmt->bind_param("i", $enseignant_user_id);
$stmt->execute();
$result = $stmt->get_result();
$enseignant = $result->fetch_assoc();

// Récupérer tous les étudiants du même niveau et division
$stmt = $conn->prepare("
    SELECT * FROM etudiants 
    WHERE niveau_id = ? AND division_id = ?
    ORDER BY nom, prenom
");
$stmt->bind_param("ii", $ec['id_niveau'], $ec['division']);
$stmt->execute();
$result = $stmt->get_result();
$etudiants = $result->fetch_all(MYSQLI_ASSOC);

// Récupérer les notes existantes
$notes = [];
$stmt = $conn->prepare("
    SELECT * FROM notes 
    WHERE ID_EC = ? AND niveau_id = ? AND division_id = ?
");
$stmt->bind_param("sii", $ec_id, $ec['id_niveau'], $ec['division']);
$stmt->execute();
$result = $stmt->get_result();
$notes_results = $result->fetch_all(MYSQLI_ASSOC);

foreach ($notes_results as $note) {
    $notes[$note['etudiant_id']][$note['modalite']] = $note;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier que c'est bien l'étudiant concerné par la requête
    if ($_POST['etudiant_id'] != $etudiant_id) {
        die("Vous ne pouvez modifier que les notes de l'étudiant concerné par la requête.");
    }
    
    // Récupérer le palier_id (à adapter selon votre logique)
    $palier_id = 1; // Exemple, à remplacer par votre logique
    
    foreach ($modalites as $modalite) {
        $valeur = $_POST['notes'][$modalite] ?? null;
        
        if ($valeur !== null && $valeur !== '') {
            // Validation
            if (!is_numeric($valeur) || $valeur < 0 || $valeur > 20) {
                die("La note doit être un nombre entre 0 et 20.");
            }
            
            // Vérifier si la note existe déjà
            $stmt = $conn->prepare("
                SELECT id FROM notes 
                WHERE etudiant_id = ? AND ID_EC = ? AND modalite = ?
            ");
            $stmt->bind_param("iss", $etudiant_id, $ec_id, $modalite);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing_note = $result->fetch_assoc();
            
            if ($existing_note) {
                // Mettre à jour la note existante
                $stmt = $conn->prepare("
                    UPDATE notes 
                    SET valeur = ?, enseignant_id = ?, date_ajout = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("dii", $valeur, $enseignant['id'], $existing_note['id']);
                $stmt->execute();
            } else {
                // Insérer une nouvelle note
                $stmt = $conn->prepare("
                    INSERT INTO notes 
                    (etudiant_id, ID_EC, palier_id, enseignant_id, valeur, modalite, niveau_id, division_id, date_ajout)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("isidssii", 
                    $etudiant_id, $ec_id, $palier_id, $enseignant['id'], 
                    $valeur, $modalite, $ec['id_niveau'], $ec['division']
                );
                $stmt->execute();
            }
        }
    }
    
    // Marquer la requête comme résolue
    $stmt = $conn->prepare("
        UPDATE requetes 
        SET statut = 'validée' 
        WHERE etudiant_id = ? AND element_constitutif = ? AND statut = 'traitée'
    ");
    $stmt->bind_param("is", $etudiant_id, $ec_id);
    $stmt->execute();
    
    header('Location: requete.php?success=1');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des notes - <?= htmlspecialchars($ec['Nom_EC']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold mb-2">Gestion des notes</h1>
                <h2 class="text-xl text-gray-600"><?= htmlspecialchars($ec['Nom_EC']) ?></h2>
            </div>
            <a href="requete.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Retour aux requêtes
            </a>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="etudiant_id" value="<?= $etudiant_id ?>">
            
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Étudiant</th>
                            <?php foreach ($modalites as $modalite): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($modalite) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($etudiants as $etudiant): 
                            $is_editable = $etudiant['id'] == $etudiant_id;
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= htmlspecialchars($etudiant['nom']) ?> <?= htmlspecialchars($etudiant['prenom']) ?>
                                <?php if ($is_editable): ?>
                                <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded">Modifiable</span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($modalites as $modalite): 
                                $note_value = isset($notes[$etudiant['id']][$modalite]) ? $notes[$etudiant['id']][$modalite]['valeur'] : '';
                            ?>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($is_editable): ?>
                                <input type="number" 
                                       name="notes[<?= htmlspecialchars($modalite) ?>]" 
                                       value="<?= htmlspecialchars($note_value) ?>" 
                                       min="0" max="20" step="0.01"
                                       class="w-20 px-2 py-1 border rounded <?= $is_editable ? 'border-blue-500' : 'border-gray-300' ?>"
                                       required>
                                <?php else: ?>
                                <span class="px-2 py-1"><?= htmlspecialchars($note_value ?: '-') ?></span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Enregistrer les modifications
                </button>
                <a href="requete.php" class="ml-4 bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</body>
</html>