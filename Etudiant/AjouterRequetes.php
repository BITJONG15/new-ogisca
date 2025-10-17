<?php
session_start();
require_once("include/db.php");

// Vérification session
if (!isset($_SESSION['matricule'])) {
    header("Location: login.php");
    exit;
}

// Récupérer l'ID étudiant connecté
$matricule = $_SESSION['matricule'];
$stmt = $conn->prepare("SELECT id, nom, prenom FROM etudiants WHERE matricule=?");
$stmt->bind_param("s", $matricule);
$stmt->execute();
$result = $stmt->get_result();
$etudiant = $result->fetch_assoc();
$etudiant_id = $etudiant['id'];

// ----- Traitement de l'envoi de requête -----
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $motif = $_POST['motif'];
    $contenu = $_POST['contenu'];
    $prof_nom = $_POST['prof_nom'];
    $prof_prenom = $_POST['prof_prenom'];
    $niveau_nom = $_POST['niveau_nom'];
    $division_nom = $_POST['division_nom'];

    // Gestion upload PDF
    $piece_jointe = null;
    if (!empty($_FILES['piece_jointe']['name'])) {
        $fileName = time() . "_" . basename($_FILES["piece_jointe"]["name"]);
        $targetPath = "uploads/" . $fileName;
        if (move_uploaded_file($_FILES["piece_jointe"]["tmp_name"], $targetPath)) {
            $piece_jointe = $fileName;
        }
    }

    // Insertion
    $stmt = $conn->prepare("INSERT INTO requetes (etudiant_id, motif, professeur_nom, professeur_prenom, niveau_nom, division_nom, contenu, piece_jointe, statut) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en attente')");
    $stmt->bind_param("isssssss", $etudiant_id, $motif, $prof_nom, $prof_prenom, $niveau_nom, $division_nom, $contenu, $piece_jointe);
    $stmt->execute();
}

// ----- Filtres -----
$filtreStatut = isset($_GET['statut']) ? $_GET['statut'] : "";
$filtreMotif = isset($_GET['motif']) ? $_GET['motif'] : "";

$query = "SELECT * FROM requetes WHERE etudiant_id=?";
$params = [$etudiant_id];
$types = "i";

if ($filtreStatut != "") {
    $query .= " AND statut=?";
    $params[] = $filtreStatut;
    $types .= "s";
}
if ($filtreMotif != "") {
    $query .= " AND motif=?";
    $params[] = $filtreMotif;
    $types .= "s";
}

$query .= " ORDER BY date_envoi DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$requetes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Mes Requêtes</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

  <h1 class="text-2xl font-bold mb-6 text-center">📌 Mes Requêtes</h1>

  <!-- Formulaire -->
  <form method="post" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-md max-w-3xl mx-auto mb-8">
    <h2 class="text-xl font-semibold mb-4">Nouvelle Requête</h2>

    <div class="mb-3">
      <label class="block mb-1">Motif</label>
      <select name="motif" class="w-full border px-3 py-2 rounded" required>
        <option value="Erreur de saisie">Erreur de saisie</option>
        <option value="Absence de note">Absence de note</option>
        <option value="Autre">Autre</option>
      </select>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-3">
      <div>
        <label class="block mb-1">Nom Professeur</label>
        <input type="text" name="prof_nom" class="w-full border px-3 py-2 rounded" required>
      </div>
      <div>
        <label class="block mb-1">Prénom Professeur</label>
        <input type="text" name="prof_prenom" class="w-full border px-3 py-2 rounded" required>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-3">
      <div>
        <label class="block mb-1">Niveau</label>
        <input type="text" name="niveau_nom" class="w-full border px-3 py-2 rounded" required>
      </div>
      <div>
        <label class="block mb-1">Division</label>
        <input type="text" name="division_nom" class="w-full border px-3 py-2 rounded" required>
      </div>
    </div>

    <div class="mb-3">
      <label class="block mb-1">Contenu</label>
      <textarea name="contenu" class="w-full border px-3 py-2 rounded" rows="3"></textarea>
    </div>

    <div class="mb-3">
      <label class="block mb-1">Pièce jointe (PDF)</label>
      <input type="file" name="piece_jointe" accept="application/pdf" class="w-full">
    </div>

    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Envoyer</button>
  </form>

  <!-- Filtres -->
  <div class="flex justify-center mb-4 space-x-4">
    <form method="get" class="flex space-x-2">
      <select name="statut" class="border px-3 py-2 rounded">
        <option value="">-- Filtrer par statut --</option>
        <option value="en attente" <?= $filtreStatut=="en attente"?"selected":"" ?>>En attente</option>
        <option value="validée" <?= $filtreStatut=="validée"?"selected":"" ?>>Validée</option>
        <option value="refusée" <?= $filtreStatut=="refusée"?"selected":"" ?>>Refusée</option>
        <option value="traitée" <?= $filtreStatut=="traitée"?"selected":"" ?>>Traitée</option>
      </select>
      <select name="motif" class="border px-3 py-2 rounded">
        <option value="">-- Filtrer par motif --</option>
        <option value="Erreur de saisie" <?= $filtreMotif=="Erreur de saisie"?"selected":"" ?>>Erreur de saisie</option>
        <option value="Absence de note" <?= $filtreMotif=="Absence de note"?"selected":"" ?>>Absence de note</option>
        <option value="Autre" <?= $filtreMotif=="Autre"?"selected":"" ?>>Autre</option>
      </select>
      <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Filtrer</button>
    </form>
  </div>

  <!-- Tableau -->
  <div class="overflow-x-auto bg-white shadow-md rounded-lg">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-200 text-gray-700 uppercase">
        <tr>
          <th class="px-4 py-2">Motif</th>
          <th class="px-4 py-2">Statut</th>
          <th class="px-4 py-2">Pièce jointe</th>
          <th class="px-4 py-2">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($requetes->num_rows > 0): ?>
          <?php while ($row = $requetes->fetch_assoc()): ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="px-4 py-2"><?= htmlspecialchars($row['motif']) ?></td>
              <td class="px-4 py-2">
                <span class="px-2 py-1 rounded text-white 
                    <?php if ($row['statut']=='en attente') echo 'bg-yellow-500';
                          elseif ($row['statut']=='traitée') echo 'bg-blue-500';
                          elseif ($row['statut']=='validée') echo 'bg-green-500';
                          elseif ($row['statut']=='refusée') echo 'bg-red-500'; ?>">
                  <?= htmlspecialchars($row['statut']) ?>
                </span>
              </td>
              <td class="px-4 py-2">
                <?php if (!empty($row['piece_jointe'])): ?>
                  <a href="uploads/<?= htmlspecialchars($row['piece_jointe']) ?>" target="_blank" class="text-blue-600 underline">📄 Voir PDF</a>
                <?php else: ?> - <?php endif; ?>
              </td>
              <td class="px-4 py-2"><?= $row['date_envoi'] ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="4" class="text-center py-4">Aucune requête trouvée.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</body>
</html>
