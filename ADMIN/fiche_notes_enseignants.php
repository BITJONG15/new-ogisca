<?php
session_start();
require_once("../include/db.php"); // adapte si nécessaire

// Vérifier que l'admin est connecté (suppose que le rôle est dans $_SESSION['role'])
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Requête pour récupérer les notes avec infos enseignant, étudiant et EC
$sql = "
    SELECT 
        n.id,
        e.nom AS nom_enseignant,
        e.prenom AS prenom_enseignant,
        ec.Nom_EC,
        n.modalite,
        n.valeur,
        etu.matricule AS matricule_etu,
        etu.nom AS nom_etu,
        etu.prenom AS prenom_etu,
        n.date_ajout
    FROM notes n
    INNER JOIN enseignants e ON n.enseignant_id = e.id
    INNER JOIN element_constitutif ec ON n.ID_EC = ec.ID_EC
    INNER JOIN etudiants etu ON n.etudiant_id = etu.id
    ORDER BY e.nom, ec.Nom_EC, etu.nom
";

$result = $conn->query($sql);
if (!$result) {
    die("Erreur lors de la récupération des notes : " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Fiches de notes - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8 font-sans">

<h1 class="text-3xl font-bold mb-6 text-center text-blue-700">Fiches de notes des enseignants (vue admin)</h1>

<div class="overflow-x-auto shadow-lg rounded-lg bg-white p-4">
<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-blue-600 text-white">
        <tr>
            <th class="px-4 py-2 text-left">Enseignant</th>
            <th class="px-4 py-2 text-left">Cours (EC)</th>
            <th class="px-4 py-2 text-left">Modalité</th>
            <th class="px-4 py-2 text-center">Note</th>
            <th class="px-4 py-2 text-left">Étudiant</th>
            <th class="px-4 py-2 text-left">Date ajout</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr class="hover:bg-blue-50 transition">
            <td class="px-4 py-2"><?= htmlspecialchars($row['nom_enseignant'] . ' ' . $row['prenom_enseignant']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['Nom_EC']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['modalite']) ?></td>
            <td class="px-4 py-2 text-center font-mono font-semibold"><?= htmlspecialchars($row['valeur']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['matricule_etu'] . ' - ' . $row['nom_etu'] . ' ' . $row['prenom_etu']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars(date("d/m/Y", strtotime($row['date_ajout']))) ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

</body>
</html>
