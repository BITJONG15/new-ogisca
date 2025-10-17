<?php
require_once("../include/db.php");
require_once '../include/auth.php';

// 🔐 Protection d'accès
if (!isset($_SESSION['matricule'])) {
    header("Location: ../login.php");
    exit();
}

// Récupération enseignant
$stmt = $conn->prepare("SELECT id, nom, prenom, photo FROM enseignants WHERE matricule = ?");
$stmt->bind_param("s", $matricule);
$stmt->execute();
$enseignant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$enseignant) die("Enseignant non trouvé");
$enseignant_id = $enseignant['id'];
$enseignant_nom = $enseignant['nom'] . " " . $enseignant['prenom'];
$photo_url = !empty($enseignant['photo']) ? "../uploads/" . $enseignant['photo'] : 'https://www.svgrepo.com/show/382106/user-circle.svg';

$ec_id = $_GET['ec'] ?? '';
if (!$ec_id) die("Élément constitutif non spécifié.");

// Récupération infos EC avec modalités
$stmt = $conn->prepare("
    SELECT ec.Nom_EC, ec.Modalites_Controle, n.nom AS niveau, ec.division
    FROM element_constitutif ec
    LEFT JOIN niveau n ON ec.id_niveau = n.id
    WHERE ec.ID_EC = ?
");
$stmt->bind_param("s", $ec_id);
$stmt->execute();
$ec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ec) die("Élément constitutif introuvable.");

// Modalités de contrôle sous forme de tableau
$modalites = array_map('trim', explode(',', $ec['Modalites_Controle']));

// Récupérer ID niveau et division
$stmt = $conn->prepare("SELECT id FROM niveau WHERE nom = ?");
$stmt->bind_param("s", $ec['niveau']);
$stmt->execute();
$res = $stmt->get_result();
$niveau_row = $res->fetch_assoc();
$stmt->close();
if (!$niveau_row) die("Niveau introuvable");
$niveau_id = $niveau_row['id'];

$stmt = $conn->prepare("SELECT id FROM division WHERE nom = ?");
$stmt->bind_param("s", $ec['division']);
$stmt->execute();
$res = $stmt->get_result();
$division_row = $res->fetch_assoc();
$stmt->close();
if (!$division_row) die("Division introuvable");
$division_id = $division_row['id'];

// Récupérer dynamiquement le palier_id lié à cet EC (avant insertion)
$stmt = $conn->prepare("
    SELECT p.id 
    FROM paliers p
    JOIN palier_ec pec ON pec.palier_id = p.id
    WHERE pec.ID_EC = ?
    LIMIT 1
");
$stmt->bind_param("s", $ec_id);
$stmt->execute();
$stmt->bind_result($palier_id);
if (!$stmt->fetch()) {
    die("Erreur : Aucun palier trouvé pour cet EC.");
}
$stmt->close();

$message = "";

// --- Traitement POST insertion notes ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notes']) && is_array($_POST['notes'])) {
    foreach ($_POST['notes'] as $etudiant_id => $modalites_notes) {
        $etudiant_id = intval($etudiant_id);
        foreach ($modalites_notes as $modalite => $valeur) {
            $modalite = strtoupper(trim($modalite));
            $valeur = trim($valeur);
            if ($valeur === '') continue;

            if (!in_array($modalite, $modalites)) {
                $message = "Modalité inconnue : $modalite";
                break 2;
            }

            if (!is_numeric($valeur) || $valeur < 0 || $valeur > 20) {
                $message = "Note invalide pour un étudiant.";
                break 2;
            }

            // Vérifier si note déjà enregistrée
            $check = $conn->prepare("SELECT id FROM notes WHERE etudiant_id = ? AND ID_EC = ? AND enseignant_id = ? AND modalite = ?");
            $check->bind_param("isis", $etudiant_id, $ec_id, $enseignant_id, $modalite);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $message = "Note déjà enregistrée pour la modalité $modalite, modification interdite.";
                $check->close();
                break 2;
            }
            $check->close();

            // Insertion note
            $insert = $conn->prepare("
                INSERT INTO notes (etudiant_id, ID_EC, palier_id, enseignant_id, niveau_id, division_id, modalite, valeur, date_ajout)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert->bind_param(
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
            if (!$insert->execute()) {
                $message = "Erreur lors de l'insertion : " . $insert->error;
                $insert->close();
                break 2;
            }
            $insert->close();
            $message = "Notes enregistrées avec succès.";
        }
    }
}

// Pagination
$limit = 15; // Nombre d'étudiants par page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Compter le nombre total d'étudiants
$count_stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM etudiants 
    WHERE niveau_id = ? AND division_id = ?
");
$count_stmt->bind_param("ii", $niveau_id, $division_id);
$count_stmt->execute();
$count_stmt->bind_result($total_etudiants);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_etudiants / $limit);

// Récupération notes pour les étudiants de la page actuelle
$stmt = $conn->prepare("
    SELECT et.id AS etudiant_id, et.nom, et.prenom, n.valeur, n.date_ajout, n.modalite
    FROM etudiants et
    LEFT JOIN notes n ON et.id = n.etudiant_id AND n.ID_EC = ? AND n.enseignant_id = ?
    WHERE et.niveau_id = ? AND et.division_id = ?
    ORDER BY et.nom, et.prenom
    LIMIT ? OFFSET ?
");
$stmt->bind_param("siiiii", $ec_id, $enseignant_id, $niveau_id, $division_id, $limit, $offset);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Organiser notes par étudiant + modalité
$etudiants = [];
foreach ($results as $row) {
    $id = $row['etudiant_id'];
    if (!isset($etudiants[$id])) {
        $etudiants[$id] = [
            'nom' => $row['nom'],
            'prenom' => $row['prenom'],
            'notes' => []
        ];
    }
    if ($row['modalite']) {
        $etudiants[$id]['notes'][strtoupper($row['modalite'])] = $row['valeur'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fiche Notes - <?=htmlspecialchars($ec['Nom_EC'])?> | OGISCA</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<style>
:root {
    --primary: #4f46e5;
    --primary-light: #6366f1;
    --primary-dark: #4338ca;
    --secondary: #10b981;
    --accent: #f59e0b;
    --danger: #ef4444;
}

* {
    transition: all 0.3s ease;
}

body {
    font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    background-color: #f5f7fd;
}

.sidebar {
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
    box-shadow: 0 0 25px rgba(79, 70, 229, 0.15);
}

.nav-item {
    border-radius: 12px;
    margin-bottom: 0.5rem;
}

.nav-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
}

.nav-item.active {
    background-color: rgba(255, 255, 255, 0.15);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.card {
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    background: white;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.stat-card {
    position: relative;
    padding-left: 5rem;
}

.stat-icon {
    position: absolute;
    left: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.topbar {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
}

.user-profile {
    position: relative;
}

.user-profile:hover .user-dropdown {
    display: block;
}

.user-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    width: 250px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    padding: 1rem;
    margin-top: 0.5rem;
    z-index: 100;
}

.fade-slide-up {
    opacity: 0;
    transform: translateY(10px);
    animation: fadeSlideUp 0.6s forwards;
}

.fade-slide-up.delay-1 { animation-delay: 0.1s; }
.fade-slide-up.delay-2 { animation-delay: 0.2s; }
.fade-slide-up.delay-3 { animation-delay: 0.3s; }
.fade-slide-up.delay-4 { animation-delay: 0.4s; }

@keyframes fadeSlideUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        left: -100%;
        z-index: 1000;
        height: 100vh;
        transition: left 0.3s ease;
    }
    
    .sidebar.open {
        left: 0;
    }
    
    .overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }
    
    .overlay.active {
        display: block;
    }
    
    .stat-card {
        padding-left: 1.5rem;
    }
    
    .stat-icon {
        position: relative;
        left: 0;
        top: 0;
        transform: none;
        margin-bottom: 1rem;
    }
}

.page-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 16px;
    padding: 2rem;
    margin: 2rem;
    margin-bottom: 1.5rem;
    color: white;
    box-shadow: 0 8px 25px rgba(79, 70, 229, 0.2);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.5rem;
    margin: 0 2rem 2rem 2rem;
}

@media (min-width: 640px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.search-bar {
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.search-bar:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-expired {
    background: #fee2e2;
    color: #991b1b;
}

.status-info {
    background: #dbeafe;
    color: #1e40af;
}

.status-pending {
    background: #f3f4f6;
    color: #6b7280;
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    margin: 0 2rem;
}

.table-responsive {
    overflow-x: auto;
}

.table {
    width: 100%;
    min-width: 600px;
}

.table-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
}

.table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.875rem;
    color: white;
}

.table td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    color: #4b5563;
}

.table tbody tr:hover {
    background-color: #f9fafb;
}

/* Inputs */
.note-input {
    width: 80px;
    text-align: center;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 0.5rem;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.note-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    outline: none;
}

.note-input:disabled {
    background-color: #f3f4f6;
    color: #6b7280;
    cursor: not-allowed;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(79, 70, 229, 0.3);
}

.btn-success {
    background: linear-gradient(90deg, var(--secondary) 0%, #34d399 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-2px);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin: 2rem 0;
    padding: 0 2rem;
}

.pagination-btn {
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-btn:hover:not(.disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-info {
    text-align: center;
    color: #6b7280;
    font-size: 0.875rem;
    margin: 1rem 0;
}

/* Mobile optimizations */
@media (max-width: 640px) {
    .table-container {
        margin: 0 1rem;
    }
    
    .pagination {
        padding: 0 1rem;
        flex-wrap: wrap;
    }
    
    .stats-grid {
        margin: 0 1rem 2rem 1rem;
    }
}
</style>
</head>
<body class="flex bg-gray-100 min-h-screen" x-data="{ mobileMenuOpen: false, userDropdownOpen: false }">

<!-- Overlay for mobile menu -->
<div class="overlay" id="overlay" x-show="mobileMenuOpen" @click="mobileMenuOpen = false"></div>

<!-- Sidebar -->
<div class="sidebar w-64 h-screen text-white p-6 flex flex-col fixed lg:relative z-50"
     :class="{'open': mobileMenuOpen}"
     x-show="window.innerWidth >= 1024 || mobileMenuOpen">
    <div class="flex items-center gap-3 mb-8">
        <img src="../admin/logo simple sans fond.png" class="w-10 h-10 rounded" alt="logo" />
        <h1 class="text-xl font-bold">OGISCA - INJS</h1>
        <button class="lg:hidden ml-auto text-2xl" @click="mobileMenuOpen = false">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <nav class="flex-grow">
        <ul class="space-y-2 mt-8 text-base font-medium">
            <li class="nav-item">
                <a href="index.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-home w-6 text-center mr-3"></i>
                    <span>DASHBOARD</span>
                </a>
            </li>
            <li class="nav-item active">
                <a href="mes_ec.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-book w-6 text-center mr-3"></i>
                    <span>MES EC</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="requetes_traitees.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-tasks w-6 text-center mr-3"></i>
                    <span>REQUÊTES</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="profil.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-user w-6 text-center mr-3"></i>
                    <span>PROFIL</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="pt-6 border-t border-white border-opacity-20 mt-auto">
        <a href="../logout.php" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-500 hover:bg-opacity-20 rounded-lg">
            <i class="fas fa-sign-out-alt w-6 text-center mr-3"></i>
            <span>Déconnexion</span>
        </a>
    </div>
</div>

<div class="flex-1 flex flex-col lg:ml-0">

<!-- Topbar -->
<header class="topbar flex items-center justify-between px-6 py-4 sticky top-0 z-40">
  <div class="flex items-center space-x-4">
    <!-- Bouton burger -->
    <button @click="mobileMenuOpen = true" class="lg:hidden text-gray-700 focus:outline-none" aria-label="Toggle menu">
      <i class="fas fa-bars text-xl"></i>
    </button>
   
    <div class="font-semibold text-blue-700 uppercase text-center text-lg">
      <div class="flex items-center space-x-3">
        <img src="../admin/logoinjs.png" alt="Logo" class="w-10 h-10 rounded-full border-2 border-gray-200" />
        <div>INSTITUT NATIONAL DE LA JEUNESSE ET DES SPORTS</div>
      </div>
    </div>
  </div>
  
  <div class="user-profile">
    <div class="flex items-center space-x-3 cursor-pointer">
      <div class="text-right hidden md:block">
        <div class="text-gray-700 font-semibold">
          Bonjour, <?= htmlspecialchars($enseignant['prenom']) ?> <?= htmlspecialchars($enseignant['nom']) ?>
        </div>
        <div class="text-gray-500 text-sm">
          Matricule : <?= htmlspecialchars($matricule) ?>
        </div>
      </div>
      <div class="relative">
        <img src="<?= $photo_url ?>" alt="Profil" class="w-12 h-12 rounded-full border-2 border-white shadow-md" />
        <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></span>
      </div>
    </div>
    
    <div class="user-dropdown" x-show="userDropdownOpen" @click.outside="userDropdownOpen = false">
      <div class="flex items-center space-x-3 pb-3 border-b border-gray-100">
        <img src="<?= $photo_url ?>" alt="Profil" class="w-14 h-14 rounded-full border-2 border-gray-200" />
        <div>
          <div class="font-semibold text-gray-800"><?= htmlspecialchars($enseignant['prenom']) ?> <?= htmlspecialchars($enseignant['nom']) ?></div>
          <div class="text-sm text-gray-500"><?= htmlspecialchars($matricule) ?></div>
        </div>
      </div>
      <div class="py-3 space-y-2">
        <a href="profil.php" class="flex items-center py-2 px-3 text-gray-700 hover:bg-blue-50 rounded-lg">
          <i class="fas fa-user-circle mr-3 text-blue-500"></i>
          <span>Mon profil</span>
        </a>
        <a href="#" class="flex items-center py-2 px-3 text-gray-700 hover:bg-blue-50 rounded-lg">
          <i class="fas fa-cog mr-3 text-blue-500"></i>
          <span>Paramètres</span>
        </a>
      </div>
      <div class="pt-3 border-t border-gray-100">
        <a href="../logout.php" class="flex items-center py-2 px-3 text-red-600 hover:bg-red-50 rounded-lg">
          <i class="fas fa-sign-out-alt mr-3"></i>
          <span>Déconnexion</span>
        </a>
      </div>
    </div>
  </div>
</header>

    <main class="p-6 space-y-6 overflow-auto">

        <!-- En-tête de page -->
        <div class="page-header">
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Fiche de Notes</h1>
                    <p class="text-blue-100 opacity-90 text-sm md:text-base">
                        <?= htmlspecialchars($ec['Nom_EC']) ?> - <?= htmlspecialchars($ec['niveau']) ?> <?= htmlspecialchars($ec['division']) ?>
                    </p>
                </div>
                <div class="text-white text-sm mt-3 md:mt-0">
                    <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">
                        Page <?= $page ?> sur <?= $total_pages ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Informations EC -->
        <div class="card p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="flex items-center">
                    <i class="fas fa-book text-blue-500 text-xl mr-3"></i>
                    <div>
                        <p class="text-sm text-gray-500">Élément Constitutif</p>
                        <p class="font-semibold"><?= htmlspecialchars($ec['Nom_EC']) ?></p>
                    </div>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-graduation-cap text-blue-500 text-xl mr-3"></i>
                    <div>
                        <p class="text-sm text-gray-500">Niveau & Division</p>
                        <p class="font-semibold"><?= htmlspecialchars($ec['niveau']) ?> - <?= htmlspecialchars($ec['division']) ?></p>
                    </div>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-tasks text-blue-500 text-xl mr-3"></i>
                    <div>
                        <p class="text-sm text-gray-500">Modalités</p>
                        <p class="font-semibold"><?= htmlspecialchars($ec['Modalites_Controle']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="card p-6 <?= strpos($message, 'succès') !== false ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?>">
                <div class="flex items-center gap-3">
                    <i class="fas <?= strpos($message, 'succès') !== false ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($message) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="flex justify-between items-center">
            <div class="flex space-x-4">
                
                <button id="printBtn" class="btn btn-secondary">
                    <i class="fas fa-print"></i>
                    Imprimer
                </button>
            </div>
            <a href="mes_ec.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i>
                Retour aux EC
            </a>
        </div>

        <!-- Informations pagination -->
        <div class="pagination-info">
            Affichage des étudiants <?= $offset + 1 ?> à <?= min($offset + $limit, $total_etudiants) ?> sur <?= $total_etudiants ?> au total
        </div>

        <!-- Formulaire de notes -->
        <form method="post" id="notesForm">
            <div class="table-container">
                <div class="table-responsive">
                    <table id="notesTable" class="table">
                        <thead class="table-header">
                            <tr>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <?php foreach ($modalites as $mod): ?>
                                    <th class="text-center"><?= htmlspecialchars($mod) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($etudiants as $id => $e): ?>
                                <tr>
                                    <td class="font-medium"><?= htmlspecialchars($e['nom']) ?></td>
                                    <td><?= htmlspecialchars($e['prenom']) ?></td>
                                    <?php foreach ($modalites as $mod):
                                        $valeur = $e['notes'][$mod] ?? '';
                                        $is_disabled = ($valeur !== '');
                                    ?>
                                    <td class="text-center">
                                        <?php if ($is_disabled): ?>
                                            <input 
                                                type="text" 
                                                value="<?= htmlspecialchars($valeur) ?>" 
                                                class="note-input bg-gray-100 cursor-not-allowed" 
                                                disabled
                                                title="Note déjà enregistrée"
                                            >
                                        <?php else: ?>
                                            <input
                                                type="number"
                                                name="notes[<?= $id ?>][<?= htmlspecialchars($mod) ?>]"
                                                min="0" max="20" step="0.01"
                                                class="note-input"
                                                placeholder="0.00"
                                                required
                                            >
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <!-- Previous Button -->
                <a href="?ec=<?= urlencode($ec_id) ?>&page=<?= $page - 1 ?>" 
                   class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left"></i>
                    Précédent
                </a>

                <!-- Page Numbers -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?ec=<?= urlencode($ec_id) ?>&page=<?= $i ?>" 
                       class="pagination-btn <?= $i == $page ? 'pagination-active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <!-- Next Button -->
                <a href="?ec=<?= urlencode($ec_id) ?>&page=<?= $page + 1 ?>" 
                   class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    Suivant
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>

            <div class="mt-6 text-center">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Enregistrer les notes
                </button>
                <p class="text-sm text-gray-500 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    Les champs grisés ne peuvent pas être modifiés
                </p>
            </div>
        </form>

    </main>

    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('app', () => ({
            mobileMenuOpen: false,
            userDropdownOpen: false,
            
            init() {
                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 1024) {
                        this.mobileMenuOpen = false;
                    }
                });
            }
        }));
    });

    // Menu mobile
    document.addEventListener('DOMContentLoaded', function() {
        // Exporter Excel
        document.getElementById('exportExcelBtn').addEventListener('click', function() {
            var wb = XLSX.utils.table_to_book(document.getElementById('notesTable'), {sheet:"Fiche Notes"});
            XLSX.writeFile(wb, 'fiche_notes_<?= htmlspecialchars($ec['Nom_EC']) ?>.xlsx');
        });

        // Imprimer
        document.getElementById('printBtn').addEventListener('click', function() {
            window.print();
        });

        // Validation formulaire
        document.getElementById('notesForm').addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[type=number][name^="notes"]');
            let hasError = false;
            
            for (const input of inputs) {
                const val = parseFloat(input.value);
                if (isNaN(val) || val < 0 || val > 20) {
                    alert('Merci de saisir une note valide entre 0 et 20 pour tous les étudiants.');
                    input.focus();
                    hasError = true;
                    break;
                }
            }
            
            if (hasError) {
                e.preventDefault();
                return false;
            }
            
            if (!confirm('Êtes-vous sûr de vouloir enregistrer ces notes ? Cette action est irréversible.')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });

        // Focus sur le premier champ vide
        const firstEmptyInput = document.querySelector('input[type=number][name^="notes"]:not([disabled])');
        if (firstEmptyInput) {
            firstEmptyInput.focus();
        }
    });
</script>
</body>
</html>