<?php
require_once("../include/db.php");
session_start();

// Vérifier que tous les paramètres nécessaires sont présents
if (!isset($_GET['requete_id']) || !isset($_GET['ec_id']) || !isset($_GET['etudiant_id'])) {
    die("Paramètres manquants");
}

$requete_id = intval($_GET['requete_id']);
$ec_id = $_GET['ec_id'];
$etudiant_id = intval($_GET['etudiant_id']);

// Récupérer l'ID de l'enseignant depuis la session
$enseignant_id = $_SESSION['enseignant_id'] ?? 0;
if (!$enseignant_id) {
    die("Enseignant non identifié");
}

// Récupérer les informations de l'étudiant
$stmt = $conn->prepare("SELECT nom, prenom FROM etudiants WHERE id = ?");
$stmt->bind_param("i", $etudiant_id);
$stmt->execute();
$etudiant_result = $stmt->get_result();
$etudiant = $etudiant_result->fetch_assoc();
$stmt->close();

if (!$etudiant) {
    die("Étudiant non trouvé");
}

// Récupérer les informations de l'EC
$stmt = $conn->prepare("SELECT Nom_EC, Modalites_Controle FROM element_constitutif WHERE ID_EC = ?");
$stmt->bind_param("s", $ec_id);
$stmt->execute();
$ec_result = $stmt->get_result();
$ec = $ec_result->fetch_assoc();
$stmt->close();

if (!$ec) {
    die("Élément constitutif non trouvé");
}

// Modalités de contrôle sous forme de tableau
$modalites = array_map('trim', explode(',', $ec['Modalites_Controle']));

// Récupérer les notes existantes
$notes = [];
$stmt = $conn->prepare("
    SELECT modalite, valeur 
    FROM notes 
    WHERE etudiant_id = ? AND ID_EC = ? AND enseignant_id = ?
");
$stmt->bind_param("isi", $etudiant_id, $ec_id, $enseignant_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notes[$row['modalite']] = $row['valeur'];
}
$stmt->close();

// Vérifier que nous avons des modalités à afficher
if (empty($modalites)) {
    die("Aucune modalité d'évaluation définie pour cet EC");
}
?>

<input type="hidden" name="ec_id" value="<?php echo htmlspecialchars($ec_id); ?>">
<input type="hidden" name="enseignant_id" value="<?php echo $enseignant_id; ?>">
<input type="hidden" name="requete_id" value="<?php echo $requete_id; ?>">

<h4 class="text-lg font-medium mb-4">Notes de <?php echo htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?> - <?php echo htmlspecialchars($ec['Nom_EC']); ?></h4>

<div class="overflow-x-auto">
    <table class="min-w-full bg-white border border-gray-200 mb-4">
        <thead>
            <tr class="bg-gray-100">
                <th class="py-2 px-4 border-b">Modalité</th>
                <th class="py-2 px-4 border-b">Note</th>
                <th class="py-2 px-4 border-b">Moyenne</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($modalites as $mod): 
                $mod_clean = htmlspecialchars($mod);
                $note_value = isset($notes[$mod]) ? $notes[$mod] : '';
            ?>
                <tr data-modalite="<?php echo $mod_clean; ?>">
                    <td class="py-2 px-4 border-b"><?php echo $mod_clean; ?></td>
                    <td class="py-2 px-4 border-b">
                        <input type="number" 
                               name="notes[<?php echo $etudiant_id; ?>][<?php echo $mod_clean; ?>]" 
                               data-etudiant-id="<?php echo $etudiant_id; ?>" 
                               data-modalite="<?php echo $mod_clean; ?>"
                               class="note-input w-20 p-1 border rounded" 
                               min="0" max="20" step="0.01"
                               value="<?php echo $note_value; ?>">
                    </td>
                    <td class="py-2 px-4 border-b note-moyenne">0.00</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>