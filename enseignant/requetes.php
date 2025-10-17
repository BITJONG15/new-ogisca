<?php
require_once("../include/db.php");
session_start();

// Vérification de l'authentification
$matricule = $_SESSION['matricule'] ?? '';
if (!str_starts_with($matricule, "EN") && !str_starts_with($matricule, "ADM")) {
    header("Location: ../index.php");
    exit;
}

// Récupération des informations de l'enseignant
$stmt = $conn->prepare("SELECT id, nom, prenom FROM enseignants WHERE matricule = ?");
$stmt->bind_param("s", $matricule);
$stmt->execute();
$enseignant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$enseignant) {
    die("Enseignant non trouvé");
}

$enseignant_id = $enseignant['id'];
$enseignant_nom = $enseignant['nom'] . " " . $enseignant['prenom'];

// Stocker l'ID de l'enseignant dans la session pour les autres scripts
$_SESSION['enseignant_id'] = $enseignant_id;

// Initialisation des variables
$requetes = [];
$message = '';

// Récupérer les requêtes avec statut "traitée"
$sql = "
    SELECT r.*, e.nom as etudiant_nom, e.prenom as etudiant_prenom, e.id as etudiant_id,
           n.ID_EC as ID_EC, ec.Nom_EC as nom_ec
    FROM requetes r
    JOIN etudiants e ON r.etudiant_id = e.id
    LEFT JOIN notes n ON r.etudiant_id = n.etudiant_id
    LEFT JOIN element_constitutif ec ON n.ID_EC = ec.ID_EC
    WHERE r.statut = 'traitée'
    GROUP BY r.id
    ORDER BY r.date_envoi DESC
";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $requetes[] = $row;
    }
}

// Traitement du formulaire "Maintenir"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maintenir'])) {
    $requete_id = intval($_POST['requete_id']);
    
    $update_stmt = $conn->prepare("UPDATE requetes SET statut = 'refusée' WHERE id = ?");
    $update_stmt->bind_param("i", $requete_id);
    
    if ($update_stmt->execute()) {
        $message = "Requête refusée avec succès.";
        // Recharger les requêtes après mise à jour
        header("Location: requetes_traitees.php?message=" . urlencode($message));
        exit;
    } else {
        $message = "Erreur lors de la mise à jour: " . $conn->error;
    }
    $update_stmt->close();
}

// Afficher le message s'il existe
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Requêtes Traitées</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
            }
            .hidden-mobile {
                display: none;
            }
            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            .action-buttons button {
                width: 100%;
            }
        }
        .modal {
            transition: opacity 0.25s ease;
        }
        body {
            background-color: #f3f4f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-message {
            background-color: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #10b981;
        }
        .error-message {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="bg-blue-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center">
            <h1 class="text-xl font-bold mb-2 md:mb-0">OGISCA - Requêtes Traitées</h1>
            <div class="flex flex-col md:flex-row items-center space-y-2 md:space-y-0 md:space-x-4">
                <span class="text-sm">Enseignant: <?php echo htmlspecialchars($enseignant_nom); ?></span>
                <a href="../dashboard_enseignant.php" class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm">Retour</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-4 mt-4">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-semibold mb-6 text-blue-800 border-b pb-2">Requêtes Traitées</h2>
            
            <?php if (!empty($message)): ?>
                <div class="<?php echo strpos($message, 'succès') !== false ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (count($requetes) > 0): ?>
            <div class="table-responsive">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead>
                        <tr class="bg-blue-100">
                            <th class="py-2 px-4 border-b">Étudiant</th>
                            <th class="py-2 px-4 border-b hidden-mobile">EC</th>
                            <th class="py-2 px-4 border-b">Motif</th>
                            <th class="py-2 px-4 border-b hidden-mobile">Date</th>
                            <th class="py-2 px-4 border-b">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requetes as $requete): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 border-b">
                                    <?php echo htmlspecialchars($requete['etudiant_nom'] . ' ' . $requete['etudiant_prenom']); ?>
                                </td>
                                <td class="py-2 px-4 border-b hidden-mobile">
                                    <?php echo htmlspecialchars($requete['nom_ec'] ?? $requete['ID_EC']); ?>
                                </td>
                                <td class="py-2 px-4 border-b">
                                    <?php echo htmlspecialchars($requete['motif']); ?>
                                </td>
                                <td class="py-2 px-4 border-b hidden-mobile">
                                    <?php echo date('d/m/Y H:i', strtotime($requete['date_envoi'])); ?>
                                </td>
                                <td class="py-2 px-4 border-b action-buttons">
                                    <button onclick="openMaintenirModal(<?php echo $requete['id']; ?>)" 
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm mb-1 md:mb-0 md:mr-2">
                                        <i class="fas fa-times-circle mr-1"></i> Maintenir
                                    </button>
                                    <button onclick="openResoudreModal(<?php echo $requete['id']; ?>, '<?php echo $requete['ID_EC']; ?>', '<?php echo $requete['etudiant_id']; ?>')" 
                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-check-circle mr-1"></i> Résoudre
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4 text-gray-400"></i>
                    <p class="text-xl">Aucune requête traitée pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Maintenir (Refuser) -->
    <div id="maintenirModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden modal z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-xl font-semibold mb-4 text-red-600">Maintenir la requête</h3>
            <p class="mb-4">Êtes-vous sûr de vouloir maintenir cette requête ? Le statut sera changé en "refusée".</p>
            <form id="maintenirForm" method="post" class="flex justify-end space-x-3">
                <input type="hidden" name="requete_id" id="maintenirRequeteId">
                <button type="button" onclick="closeMaintenirModal()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded">Annuler</button>
                <button type="submit" name="maintenir" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded">Confirmer</button>
            </form>
        </div>
    </div>

    <!-- Modal Résoudre (Modifier les notes) -->
    <div id="resoudreModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden modal overflow-y-auto z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl my-8 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-green-600">Résoudre la requête</h3>
                <button onclick="closeResoudreModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="notesContainer">
                <!-- Le contenu sera chargé dynamiquement via AJAX -->
            </div>
            <div class="flex justify-end mt-4">
                <button type="button" onclick="closeResoudreModal()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded mr-2">Fermer</button>
                <button type="button" id="saveNotesBtn" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded">Enregistrer les modifications</button>
            </div>
        </div>
    </div>

    <script>
        // Fonctions pour les modals
        function openMaintenirModal(requeteId) {
            document.getElementById('maintenirRequeteId').value = requeteId;
            document.getElementById('maintenirModal').classList.remove('hidden');
        }

        function closeMaintenirModal() {
            document.getElementById('maintenirModal').classList.add('hidden');
        }

        function openResoudreModal(requeteId, ecId, etudiantId) {
            // Afficher un indicateur de chargement
            document.getElementById('notesContainer').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-blue-500 text-3xl mb-3"></i>
                    <p class="text-gray-600">Chargement des notes...</p>
                </div>
            `;
            document.getElementById('resoudreModal').classList.remove('hidden');
            
            // Charger les notes via AJAX
            fetch('get_notes_requete.php?requete_id=' + requeteId + '&ec_id=' + encodeURIComponent(ecId) + '&etudiant_id=' + etudiantId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur réseau: ' + response.statusText);
                    }
                    return response.text();
                })
                .then(data => {
                    document.getElementById('notesContainer').innerHTML = data;
                    attachNoteEventListeners();
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('notesContainer').innerHTML = `
                        <div class="p-4 bg-red-100 text-red-700 rounded">
                            <p class="font-semibold">Erreur lors du chargement des notes</p>
                            <p class="text-sm mt-1">${error.message}</p>
                            <button onclick="openResoudreModal(${requeteId}, '${ecId}', ${etudiantId})" class="mt-3 px-3 py-1 bg-red-600 text-white rounded text-sm">
                                <i class="fas fa-redo mr-1"></i> Réessayer
                            </button>
                        </div>
                    `;
                });
        }

        function closeResoudreModal() {
            document.getElementById('resoudreModal').classList.add('hidden');
        }

        // Calcul automatique des notes
        function attachNoteEventListeners() {
            const noteInputs = document.querySelectorAll('input[type="number"].note-input');
            noteInputs.forEach(input => {
                input.addEventListener('input', calculateAverage);
            });
            
            // Calculer les moyennes initiales
            calculateAverage();
        }

        function calculateAverage() {
            const modalites = document.querySelectorAll('[data-modalite]');
            modalites.forEach(modalite => {
                const inputs = modalite.querySelectorAll('input.note-input');
                let sum = 0;
                let count = 0;
                
                inputs.forEach(input => {
                    if (input.value && !isNaN(parseFloat(input.value))) {
                        sum += parseFloat(input.value);
                        count++;
                    }
                });
                
                const average = count > 0 ? (sum / count).toFixed(2) : 0;
                modalite.querySelector('.note-moyenne').textContent = average;
            });
        }

        // Enregistrement des modifications
        document.getElementById('saveNotesBtn').addEventListener('click', function() {
            const noteInputs = document.querySelectorAll('input.note-input');
            let hasError = false;
            
            // Validation des notes
            noteInputs.forEach(input => {
                const value = parseFloat(input.value);
                if (input.value && (isNaN(value) || value < 0 || value > 20)) {
                    input.classList.add('border-red-500');
                    hasError = true;
                } else {
                    input.classList.remove('border-red-500');
                }
            });
            
            if (hasError) {
                alert('Veuillez saisir des notes valides entre 0 et 20.');
                return;
            }
            
            const formData = new FormData();
            noteInputs.forEach(input => {
                formData.append('notes[' + input.dataset.etudiantId + '][' + input.dataset.modalite + ']', input.value);
            });
            
            formData.append('ec_id', document.querySelector('input[name="ec_id"]').value);
            formData.append('enseignant_id', document.querySelector('input[name="enseignant_id"]').value);
            formData.append('requete_id', document.querySelector('input[name="requete_id"]').value);
            
            // Afficher un indicateur de chargement
            const saveBtn = document.getElementById('saveNotesBtn');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Enregistrement...';
            saveBtn.disabled = true;
            
            fetch('update_notes_requete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Notes mises à jour avec succès !');
                    closeResoudreModal();
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la mise à jour.');
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        });

        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target === document.getElementById('maintenirModal')) {
                closeMaintenirModal();
            }
            if (event.target === document.getElementById('resoudreModal')) {
                closeResoudreModal();
            }
        };

        // Fermer les modals avec la touche Échap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMaintenirModal();
                closeResoudreModal();
            }
        });
    </script>
</body>
</html>