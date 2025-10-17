<?php
session_start();
require_once("../include/db.php");

// Vérification session enseignant
if (!isset($_SESSION['matricule']) || !str_starts_with($_SESSION['matricule'], "EN")) {
    header("Location: ../login.php");
    exit;
}

$matricule = $_SESSION['matricule'];

// Récupérer l'enseignant
$stmt = $conn->prepare("SELECT id, nom, prenom FROM enseignants WHERE matricule = ?");
$stmt->bind_param("s", $matricule);
$stmt->execute();
$enseignant = $stmt->get_result()->fetch_assoc();
if (!$enseignant) {
    die("Enseignant non trouvé");
}
$enseignant_id = $enseignant['id'];

// Récupérer EC depuis GET ou POST
$ec_id = $_GET['ec'] ?? ($_POST['ec_id'] ?? '');
if (!$ec_id) {
    die("EC non spécifié");
}

// Récupérer infos EC avec jointure pour avoir le nom du niveau
$stmt = $conn->prepare("
    SELECT ec.ID_EC, ec.Nom_EC, ec.id_niveau, n.nom as niveau_nom, ec.division 
    FROM element_constitutif ec
    JOIN niveau n ON ec.id_niveau = n.id
    WHERE ec.ID_EC = ?
");
$stmt->bind_param("s", $ec_id);
$stmt->execute();
$ec = $stmt->get_result()->fetch_assoc();
if (!$ec) {
    die("EC introuvable");
}

// Vérifier que cet EC est bien attribué à cet enseignant
$stmt = $conn->prepare("
    SELECT id FROM attribution_ec 
    WHERE ID_EC = ? AND id_enseignants = ?
");
$stmt->bind_param("si", $ec_id, $enseignant_id);
$stmt->execute();
$attribution = $stmt->get_result()->fetch_assoc();
if (!$attribution) {
    die("Vous n'êtes pas autorisé à saisir les notes pour cet EC");
}

// Récupérer les étudiants du même niveau et division que l'EC
$stmt = $conn->prepare("
    SELECT e.id, e.nom, e.prenom, e.matricule
    FROM etudiants e
    WHERE e.niveau_id = ? AND e.division_id = (SELECT id FROM division WHERE nom = ?)
    ORDER BY e.nom, e.prenom
");
$stmt->bind_param("is", $ec['id_niveau'], $ec['division']);
$stmt->execute();
$etudiants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupérer les notes existantes
$notes_existantes = [];
if (!empty($etudiants)) {
    $etudiant_ids = array_column($etudiants, 'id');
    $placeholders = str_repeat('?,', count($etudiant_ids) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT etudiant_id, valeur 
        FROM notes 
        WHERE etudiant_id IN ($placeholders) AND ID_EC = ? AND enseignant_id = ?
    ");
    
    $types = str_repeat('i', count($etudiant_ids)) . 'si';
    $params = array_merge($etudiant_ids, [$ec_id, $enseignant_id]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notes_existantes[$row['etudiant_id']] = $row['valeur'];
    }
}

// Gestion de la soumission du formulaire
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notes'])) {
    $conn->begin_transaction();
    $errors = [];
    $success_count = 0;
    
    foreach ($_POST['notes'] as $etudiant_id => $valeur) {
        $etudiant_id = intval($etudiant_id);
        $valeur = trim($valeur);
        
        // Si champ vide, on passe à l'étudiant suivant
        if ($valeur === '') continue;

        // Validation de la note
        if (!is_numeric($valeur) || $valeur < 0 || $valeur > 20) {
            $errors[] = "Note invalide pour l'étudiant ID $etudiant_id : $valeur";
            continue;
        }

        $valeur = floatval($valeur);

        // Vérifier si note existe déjà
        if (isset($notes_existantes[$etudiant_id])) {
            // Mettre à jour la note existante
            $update = $conn->prepare("UPDATE notes SET valeur = ?, date_ajout = NOW() WHERE etudiant_id = ? AND ID_EC = ? AND enseignant_id = ?");
            $update->bind_param("disi", $valeur, $etudiant_id, $ec_id, $enseignant_id);
            if ($update->execute()) {
                $success_count++;
            } else {
                $errors[] = "Erreur mise à jour note pour étudiant ID $etudiant_id";
            }
            $update->close();
        } else {
            // Insérer nouvelle note
            $insert = $conn->prepare("INSERT INTO notes (etudiant_id, ID_EC, enseignant_id, valeur, date_ajout) VALUES (?, ?, ?, ?, NOW())");
            $insert->bind_param("isid", $etudiant_id, $ec_id, $enseignant_id, $valeur);
            if ($insert->execute()) {
                $success_count++;
            } else {
                $errors[] = "Erreur insertion note pour étudiant ID $etudiant_id";
            }
            $insert->close();
        }
    }

    if (empty($errors)) {
        $conn->commit();
        $message = "$success_count note(s) enregistrée(s) avec succès.";
        $message_type = "success";
        
        // Recharger les notes existantes après mise à jour
        $notes_existantes = [];
        if (!empty($etudiants)) {
            $etudiant_ids = array_column($etudiants, 'id');
            $placeholders = str_repeat('?,', count($etudiant_ids) - 1) . '?';
            $stmt = $conn->prepare("
                SELECT etudiant_id, valeur 
                FROM notes 
                WHERE etudiant_id IN ($placeholders) AND ID_EC = ? AND enseignant_id = ?
            ");
            
            $types = str_repeat('i', count($etudiant_ids)) . 'si';
            $params = array_merge($etudiant_ids, [$ec_id, $enseignant_id]);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $notes_existantes[$row['etudiant_id']] = $row['valeur'];
            }
        }
    } else {
        $conn->rollback();
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie des notes - <?= htmlspecialchars($ec['Nom_EC']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .card {
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            background: white;
            transition: all 0.3s ease;
        }
        
        .table-row:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen p-4">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="card bg-gradient-to-r from-blue-600 to-purple-700 text-white p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold mb-2">Saisie des Notes</h1>
                    <p class="text-blue-100 text-lg"><?= htmlspecialchars($ec['Nom_EC']) ?></p>
                    <div class="flex flex-wrap gap-4 mt-3">
                        <span class="bg-white/20 px-3 py-1 rounded-full text-sm">
                            <i class="fas fa-graduation-cap mr-2"></i>
                            <?= htmlspecialchars($ec['niveau_nom']) ?>
                        </span>
                        <span class="bg-white/20 px-3 py-1 rounded-full text-sm">
                            <i class="fas fa-users mr-2"></i>
                            <?= htmlspecialchars($ec['division']) ?>
                        </span>
                        <span class="bg-white/20 px-3 py-1 rounded-full text-sm">
                            <i class="fas fa-user-tie mr-2"></i>
                            <?= htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']) ?>
                        </span>
                    </div>
                </div>
                <div class="mt-4 lg:mt-0">
                    <a href="mes_ec.php" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition-all duration-300 inline-flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i>
                        Retour aux EC
                    </a>
                </div>
            </div>
        </div>

        <!-- Message d'alerte -->
        <?php if ($message): ?>
            <div class="card p-4 mb-6 <?= $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?>">
                <div class="flex items-center gap-3">
                    <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> text-lg"></i>
                    <span><?= $message ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Formulaire de saisie -->
        <div class="card p-6">
            <form method="POST" onsubmit="return validateForm();">
                <input type="hidden" name="ec_id" value="<?= htmlspecialchars($ec_id) ?>" />
                
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Liste des Étudiants</h2>
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                        <?= count($etudiants) ?> étudiant(s)
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Matricule</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Nom</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Prénom</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Note /20</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($etudiants as $e): 
                                $note_existante = $notes_existantes[$e['id']] ?? null;
                                $has_note = $note_existante !== null;
                            ?>
                            <tr class="table-row border-b border-gray-100 last:border-b-0 transition-all duration-200">
                                <td class="px-4 py-3 text-gray-600 font-mono"><?= htmlspecialchars($e['matricule']) ?></td>
                                <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($e['nom']) ?></td>
                                <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($e['prenom']) ?></td>
                                <td class="px-4 py-3">
                                    <input
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="20"
                                        name="notes[<?= $e['id'] ?>]"
                                        value="<?= $has_note ? htmlspecialchars($note_existante) : '' ?>"
                                        class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 <?= $has_note ? 'bg-green-50 border-green-300' : '' ?>"
                                        placeholder="0.0"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($has_note): ?>
                                        <span class="inline-flex items-center gap-1 bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                            <i class="fas fa-check text-xs"></i>
                                            Saisie
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm">
                                            <i class="fas fa-clock text-xs"></i>
                                            En attente
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($etudiants)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-users text-4xl text-gray-300 mb-3 block"></i>
                                    <p class="text-lg">Aucun étudiant trouvé pour ce cours.</p>
                                    <p class="text-sm mt-1">Vérifiez l'attribution de l'EC ou contactez l'administrateur.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($etudiants)): ?>
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mt-6 pt-6 border-t border-gray-200">
                    <button
                        type="submit"
                        class="bg-gradient-to-r from-blue-600 to-purple-700 text-white px-8 py-3 rounded-lg hover:from-blue-700 hover:to-purple-800 transition-all duration-300 transform hover:scale-105 shadow-lg flex items-center gap-2 w-full sm:w-auto justify-center"
                    >
                        <i class="fas fa-save"></i>
                        Enregistrer les Notes
                    </button>

                    <a href="fiche_notes.php?ec=<?= urlencode($ec_id) ?>" target="_blank" 
                       class="bg-gradient-to-r from-green-600 to-emerald-700 text-white px-6 py-3 rounded-lg hover:from-green-700 hover:to-emerald-800 transition-all duration-300 flex items-center gap-2 w-full sm:w-auto justify-center">
                        <i class="fas fa-file-pdf"></i>
                        Exporter PDF
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        function validateForm() {
            const inputs = document.querySelectorAll("input[name^='notes']");
            let hasError = false;
            
            for (let input of inputs) {
                const val = input.value.trim();
                if (val !== "") {
                    const num = parseFloat(val);
                    if (isNaN(num) || num < 0 || num > 20) {
                        input.classList.add('border-red-500', 'bg-red-50');
                        hasError = true;
                    } else {
                        input.classList.remove('border-red-500', 'bg-red-50');
                    }
                }
            }
            
            if (hasError) {
                alert("Certaines notes sont invalides. Veuillez vérifier que toutes les notes sont des nombres entre 0 et 20.");
                return false;
            }
            
            return true;
        }

        // Validation en temps réel
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll("input[name^='notes']");
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const val = this.value.trim();
                    if (val !== "") {
                        const num = parseFloat(val);
                        if (isNaN(num) || num < 0 || num > 20) {
                            this.classList.add('border-red-500', 'bg-red-50');
                        } else {
                            this.classList.remove('border-red-500', 'bg-red-50');
                            this.classList.add('bg-green-50', 'border-green-300');
                        }
                    } else {
                        this.classList.remove('border-red-500', 'bg-red-50', 'bg-green-50', 'border-green-300');
                    }
                });
            });
        });
    </script>
</body>
</html>