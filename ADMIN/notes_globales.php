<?php
// === Démarrage session & connexion base de données ===
session_start();
$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) die("Erreur de connexion : " . $conn->connect_error);

// === Vérification de session et autorisation ADMIN ===
if (!isset($_SESSION['matricule']) || !str_starts_with($_SESSION['matricule'], 'ADM')) {
    header("Location: login.php");
    exit();
}

// === Export CSV ===
if (isset($_GET['export']) && isset($_GET['ec_id'])) {
    $ec_id = $conn->real_escape_string($_GET['ec_id']);

    // Récupération des données de l'EC
    $ec = $conn->query("SELECT Nom_EC, Modalites_Controle FROM element_constitutif WHERE ID_EC='$ec_id'")->fetch_assoc();
    
    // Extraction des modalités dynamiques
    $modalites = explode(',', $ec['Modalites_Controle']);
    $modalites = array_map('trim', $modalites);
    
    // Récupération des notes avec toutes les modalités
    $notes_query = $conn->query("
        SELECT et.matricule, et.nom, et.prenom, n.valeur as note, n.modalite
        FROM notes n 
        JOIN etudiants et ON n.etudiant_id = et.id 
        WHERE n.ID_EC = '$ec_id'
        ORDER BY et.nom, et.prenom
    ");

    // Organisation des données par étudiant
    $etudiants_data = [];
    while ($note = $notes_query->fetch_assoc()) {
        $matricule = $note['matricule'];
        if (!isset($etudiants_data[$matricule])) {
            $etudiants_data[$matricule] = [
                'matricule' => $note['matricule'],
                'nom' => $note['nom'],
                'prenom' => $note['prenom'],
                'notes' => [],
                'moyenne' => 0
            ];
        }
        $etudiants_data[$matricule]['notes'][$note['modalite']] = floatval($note['note']);
    }

    // Calcul des moyennes
    foreach ($etudiants_data as &$etudiant) {
        if (!empty($etudiant['notes'])) {
            $etudiant['moyenne'] = number_format(array_sum($etudiant['notes']) / count($etudiant['notes']), 2, ',', ' ');
        }
    }

    // Création du fichier CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Fiche_'.str_replace(' ', '_', $ec['Nom_EC']).'.csv"');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM pour Excel
    
    // En-tête dynamique
    $header = ['Matricule', 'Nom', 'Prénom'];
    foreach ($modalites as $modalite) {
        $header[] = $modalite;
    }
    $header[] = 'Moyenne';
    
    fputcsv($output, $header, ';');
    
    // Données
    foreach ($etudiants_data as $etudiant) {
        $row = [
            $etudiant['matricule'],
            $etudiant['nom'],
            $etudiant['prenom']
        ];
        
        foreach ($modalites as $modalite) {
            $row[] = isset($etudiant['notes'][$modalite]) ? 
                    number_format($etudiant['notes'][$modalite], 2, ',', ' ') : '-';
        }
        
        $row[] = $etudiant['moyenne'];
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

// === Gestion AJAX (niveaux, EC, notes) ===
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $type = $_GET['ajax'];

    // Charger niveaux
    if ($type === 'get_niveaux' && isset($_GET['division_id'])) {
        $id = intval($_GET['division_id']);
        $res = $conn->query("SELECT * FROM niveau WHERE id_division = $id ORDER BY nom");
        $data = [];
        while ($n = $res->fetch_assoc()) $data[] = $n;
        echo json_encode($data);
        exit;
    }

    // Charger EC
    if ($type === 'get_ec' && isset($_GET['niveau_id'])) {
        $id = intval($_GET['niveau_id']);
        $res = $conn->query("SELECT * FROM element_constitutif WHERE id_niveau = $id ORDER BY Nom_EC");
        $data = [];
        while ($ec = $res->fetch_assoc()) $data[] = $ec;
        echo json_encode($data);
        exit;
    }

    // Charger Notes avec modalités dynamiques
    if ($type === 'get_notes' && isset($_GET['ec_id'])) {
        $ec_id = $conn->real_escape_string($_GET['ec_id']);
        
        // Récupérer les infos de l'EC et de l'enseignant
        $ec_query = $conn->query("
            SELECT e.Nom_EC, e.Modalites_Controle, ens.nom, ens.prenom 
            FROM element_constitutif e 
            LEFT JOIN attribution_ec ae ON e.ID_EC = ae.ID_EC 
            LEFT JOIN enseignants ens ON ae.id_enseignants = ens.id 
            WHERE e.ID_EC = '$ec_id'
        ");
        
        if ($ec_query && $ec_query->num_rows > 0) {
            $ec = $ec_query->fetch_assoc();
        } else {
            // Fallback si pas d'attribution trouvée
            $ec = $conn->query("SELECT Nom_EC, Modalites_Controle FROM element_constitutif WHERE ID_EC = '$ec_id'")->fetch_assoc();
            $ec['nom'] = $ec['prenom'] = "Non attribué";
        }

        // Extraire les modalités dynamiques
        $modalites = explode(',', $ec['Modalites_Controle']);
        $modalites = array_map('trim', $modalites);

        // Récupérer les notes
        $notes_query = $conn->query("
            SELECT n.etudiant_id, et.matricule, et.nom, et.prenom, 
                   n.valeur as note, n.modalite
            FROM notes n 
            JOIN etudiants et ON n.etudiant_id = et.id 
            WHERE n.ID_EC = '$ec_id'
            ORDER BY et.nom, et.prenom
        ");

        $etudiants_notes = [];
        while ($note = $notes_query->fetch_assoc()) {
            $etudiant_id = $note['etudiant_id'];
            
            if (!isset($etudiants_notes[$etudiant_id])) {
                $etudiants_notes[$etudiant_id] = [
                    'matricule' => $note['matricule'],
                    'nom' => $note['nom'],
                    'prenom' => $note['prenom'],
                    'notes' => [],
                    'moyenne' => 0
                ];
            }
            
            $etudiants_notes[$etudiant_id]['notes'][$note['modalite']] = floatval($note['note']);
        }

        // Calculer la moyenne pour chaque étudiant
        foreach ($etudiants_notes as &$etudiant) {
            if (!empty($etudiant['notes'])) {
                $etudiant['moyenne'] = number_format(array_sum($etudiant['notes']) / count($etudiant['notes']), 2);
            }
        }

        echo json_encode([
            "success" => count($etudiants_notes) > 0,
            "ec" => $ec,
            "enseignant" => trim($ec['prenom'] . " " . $ec['nom']),
            "modalites" => $modalites,
            "notes" => array_values($etudiants_notes)
        ]);
        exit;
    }
}

// === Liste des divisions ===
$divisions = $conn->query("SELECT * FROM division ORDER BY nom ASC");

// Récupération des informations de l'utilisateur connecté
$matricule = $_SESSION['matricule'];
$sql = "SELECT nom, prenom, matricule FROM users WHERE matricule = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $matricule);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { header('Location: logout.php'); exit; }
$user = $result->fetch_assoc();
$photo_url = 'C:\xampp\htdocs\SYSTOGISCA\ADMIN\uploads/' . $user['matricule'] . '.jpg';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fiche des Notes - OGISCA</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

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
        grid-template-columns: repeat(4, 1fr);
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

.requete-invalide {
    background-color: #fef2f2;
    opacity: 0.7;
}

.requete-invalide:hover {
    background-color: #fee2e2;
}

.table-row-hover:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    transform: translateY(-1px);
}

.btn-primary {
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(79, 70, 229, 0.3);
}

.form-input {
    border: 1px solid #d1d5db;
    border-radius: 10px;
    padding: 0.75rem;
    transition: all 0.3s ease;
    width: 100%;
}

.form-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    outline: none;
}

.popup {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(5px);
}

.popup.open {
    display: flex;
}

.popup-content {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    width: 95%;
    max-width: 700px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.close-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    cursor: pointer;
    font-size: 1.5rem;
    font-weight: bold;
    color: #6b7280;
    transition: color 0.2s;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close-btn:hover {
    background: #f3f4f6;
    color: #ef4444;
}

/* Styles spécifiques pour la page fiche des notes */
.filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin: 0 2rem 2rem 2rem;
}

@media (min-width: 768px) {
    .filter-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.fiche-container {
    margin: 0 2rem 2rem 2rem;
}

.fiche-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid var(--primary);
}

.data-table-container {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.table-header-bg {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
}

.table-modalite-header {
    background: var(--primary-dark);
}

.table-moyenne-header {
    background: var(--accent);
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
        <img src="logo simple sans fond.png" class="w-10 h-10 rounded" alt="logo" />
        <h1 class="text-xl font-bold">OGISCA</h1>
        <button class="lg:hidden ml-auto text-2xl" @click="mobileMenuOpen = false">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <nav class="flex-grow">
        <ul class="space-y-2 mt-8 text-base font-medium">
            <li class="nav-item">
                <a href="index.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-home w-6 text-center mr-3"></i>
                    <span>ACCUEIL</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="dashboard.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-chalkboard-teacher w-6 text-center mr-3"></i>
                    <span>GESTION DES ENSEIGNANTS</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="gestionEtudiant.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-user-graduate w-6 text-center mr-3"></i>
                    <span>GESTION DES ÉTUDIANTS</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="gestion_corrections.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-calendar-alt w-6 text-center mr-3"></i>
                    <span>PROGRAMMER DES CORRECTIONS</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_requetes.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-tasks w-6 text-center mr-3"></i>
                    <span>GERER LES REQUÊTES</span>
                </a>
            </li>
            <li class="nav-item active">
                <a href="fiche_notes_enseignants.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-clipboard-list w-6 text-center mr-3"></i>
                    <span>VOIR LES NOTES</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../enseignant/index.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-chalkboard w-6 text-center mr-3"></i>
                    <span>TABLEAU DE BORD ENSEIGNANT</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="pt-6 border-t border-white border-opacity-20 mt-auto">
        <a href="logout.php" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-500 hover:bg-opacity-20 rounded-lg">
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
        <img src="logoinjs.png" alt="Logo" class="w-10 h-10 rounded-full border-2 border-gray-200" />
        <div>INSTITUT NATIONAL DE LA JEUNESSE ET DES SPORTS</div>
      </div>
    </div>
  </div>
  
  <div class="user-profile">
    <div class="flex items-center space-x-3 cursor-pointer">
      <div class="text-right hidden md:block">
        <div class="text-gray-700 font-semibold">
          Bonjour, <?= htmlspecialchars($user['prenom']) ?> <?= htmlspecialchars($user['nom']) ?>
        </div>
        <div class="text-gray-500 text-sm">
          Matricule : <?= htmlspecialchars($user['matricule']) ?>
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
          <div class="font-semibold text-gray-800"><?= htmlspecialchars($user['prenom']) ?> <?= htmlspecialchars($user['nom']) ?></div>
          <div class="text-sm text-gray-500"><?= htmlspecialchars($user['matricule']) ?></div>
        </div>
      </div>
      <div class="py-3 space-y-2">
        <a href="#" class="flex items-center py-2 px-3 text-gray-700 hover:bg-blue-50 rounded-lg">
          <i class="fas fa-user-circle mr-3 text-blue-500"></i>
          <span>Mon profil</span>
        </a>
        <a href="#" class="flex items-center py-2 px-3 text-gray-700 hover:bg-blue-50 rounded-lg">
          <i class="fas fa-cog mr-3 text-blue-500"></i>
          <span>Paramètres</span>
        </a>
      </div>
      <div class="pt-3 border-t border-gray-100">
        <a href="logout.php" class="flex items-center py-2 px-3 text-red-600 hover:bg-red-50 rounded-lg">
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
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Fiche des Notes - Administration</h1>
                    <p class="text-blue-100 opacity-90 text-sm md:text-base">Consultez et exportez les notes des étudiants par élément constitutif</p>
                </div>
                <div class="text-white text-sm mt-3 md:mt-0">
                    <i class="far fa-calendar-alt mr-2"></i>
                    <?php echo date('d/m/Y'); ?>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-filter mr-3 text-blue-500"></i>
                Filtres de recherche
            </h3>
            
            <div class="filter-grid">
                <div class="form-group">
                    <label class="form-label">Division</label>
                    <select id="division" class="form-input">
                        <option value="">-- Choisir une division --</option>
                        <?php 
                        $divisions->data_seek(0); // Reset du pointeur
                        while($div = $divisions->fetch_assoc()): ?>
                            <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nom']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Niveau</label>
                    <select id="niveau" class="form-input" disabled>
                        <option value="">-- Sélectionnez la division --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Élément Constitutif (EC)</label>
                    <select id="ec" class="form-input" disabled>
                        <option value="">-- Sélectionnez le niveau --</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Fiche de notes -->
        <div id="ficheNotes" class="hidden">
            <div class="fiche-container">
                <div class="fiche-header">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div>
                            <h2 id="ecNom" class="text-xl font-semibold text-gray-800"></h2>
                            <p id="enseignantNom" class="text-gray-600 mt-1"></p>
                            <p id="modalitesInfo" class="text-sm text-blue-600 mt-1 font-medium"></p>
                            <p id="infoNotes" class="text-sm text-gray-500 mt-1"></p>
                        </div>
                        <div class="flex gap-3 mt-4 md:mt-0">
                            <button id="btnExcel" class="btn-primary bg-green-600 hover:bg-green-700">
                                <i class="fas fa-file-excel"></i> Exporter CSV
                            </button>
                            <button onclick="window.print()" class="btn-primary bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-print"></i> Imprimer
                            </button>
                        </div>
                    </div>
                </div>

                <div class="data-table-container">
                    <table id="tableNotes" class="w-full text-sm">
                        <thead class="table-header-bg text-white" id="tableHeader">
                            <tr>
                                <th class="p-3 text-left">Matricule</th>
                                <th class="p-3 text-left">Nom</th>
                                <th class="p-3 text-left">Prénom</th>
                                <!-- Les colonnes de modalités seront ajoutées dynamiquement ici -->
                                <th class="p-3 text-center font-bold table-moyenne-header">Moyenne</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody" class="bg-white"></tbody>
                    </table>
                </div>
                
                <div id="aucuneNote" class="hidden text-center py-8 text-gray-500">
                    <i class="fas fa-exclamation-circle text-3xl mb-2"></i>
                    <p>Aucune note trouvée pour cet élément constitutif.</p>
                </div>
            </div>
        </div>

    </main>

    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<script>
$(document).ready(function(){
    let dataTable = null;
    let currentModalites = [];

    // Division → Niveaux
    $('#division').change(function(){
        const id = $(this).val();
        $('#niveau').prop('disabled', true).html('<option>Chargement...</option>');
        $('#ec').prop('disabled', true).html('<option>-- Sélectionnez le niveau --</option>');
        $('#ficheNotes').addClass('hidden');
        
        resetDataTable();
        
        if(id){
            $.get('?ajax=get_niveaux&division_id='+id, function(data){
                let opt = '<option value="">-- Choisir un niveau --</option>';
                data.forEach(n => opt += `<option value="${n.id}">${n.nom}</option>`);
                $('#niveau').html(opt).prop('disabled', false);
            }).fail(function() {
                $('#niveau').html('<option value="">Erreur de chargement</option>');
            });
        }
    });

    // Niveau → EC
    $('#niveau').change(function(){
        const id = $(this).val();
        $('#ec').prop('disabled', true).html('<option>Chargement...</option>');
        $('#ficheNotes').addClass('hidden');
        
        resetDataTable();
        
        if(id){
            $.get('?ajax=get_ec&niveau_id='+id, function(data){
                let opt = '<option value="">-- Choisir un EC --</option>';
                data.forEach(e => opt += `<option value="${e.ID_EC}">${e.Nom_EC}</option>`);
                $('#ec').html(opt).prop('disabled', false);
            }).fail(function() {
                $('#ec').html('<option value="">Erreur de chargement</option>');
            });
        }
    });

    // EC → Fiche Notes
    $('#ec').change(function(){
        const id = $(this).val();
        if(id){
            $.getJSON('?ajax=get_notes&ec_id='+id, function(res){
                if(res.success && res.notes.length > 0){
                    currentModalites = res.modalites || [];
                    $('#ficheNotes').removeClass('hidden');
                    $('#aucuneNote').addClass('hidden');
                    $('#ecNom').text(res.ec.Nom_EC);
                    $('#enseignantNom').text("Enseignant : " + res.enseignant);
                    $('#modalitesInfo').text("Modalités : " + currentModalites.join(', '));
                    $('#infoNotes').text(`${res.notes.length} étudiant(s) trouvé(s)`);
                    
                    // Reconstruire l'en-tête du tableau avec les modalités dynamiques
                    rebuildTableHeader();
                    
                    // Remplir le corps du tableau
                    fillTableBody(res.notes);
                    
                    // Initialiser DataTable
                    initializeDataTable();
                    
                } else {
                    $('#ficheNotes').removeClass('hidden');
                    $('#tableBody').html('');
                    $('#aucuneNote').removeClass('hidden');
                    $('#ecNom').text(res.ec ? res.ec.Nom_EC : '');
                    $('#enseignantNom').text(res.enseignant ? "Enseignant : " + res.enseignant : '');
                    $('#modalitesInfo').text(res.ec ? "Modalités : " + res.ec.Modalites_Controle : '');
                    $('#infoNotes').text('Aucune note disponible');
                    
                    resetDataTable();
                }
            }).fail(function() {
                alert("Erreur lors du chargement des notes.");
                $('#ficheNotes').addClass('hidden');
            });
        }
    });

    // Fonction pour reconstruire l'en-tête du tableau
    function rebuildTableHeader() {
        let headerHtml = `
            <tr>
                <th class="p-3 text-left">Matricule</th>
                <th class="p-3 text-left">Nom</th>
                <th class="p-3 text-left">Prénom</th>`;
        
        // Ajouter les colonnes pour chaque modalité
        currentModalites.forEach(modalite => {
            headerHtml += `<th class="p-3 text-center table-modalite-header">${modalite}</th>`;
        });
        
        headerHtml += `<th class="p-3 text-center font-bold table-moyenne-header">Moyenne</th></tr>`;
        
        $('#tableHeader').html(headerHtml);
    }

    // Fonction pour remplir le corps du tableau
    function fillTableBody(notes) {
        let rows = '';
        
        notes.forEach(etudiant => {
            const notesEtudiant = etudiant.notes;
            
            let row = `
                <tr class="border-b hover:bg-gray-50 table-row-hover">
                    <td class="p-3 font-mono">${etudiant.matricule}</td>
                    <td class="p-3">${etudiant.nom}</td>
                    <td class="p-3">${etudiant.prenom}</td>`;
            
            // Ajouter les cellules pour chaque modalité
            currentModalites.forEach(modalite => {
                const note = notesEtudiant[modalite];
                row += `<td class="p-3 text-center">${note !== undefined ? note : '-'}</td>`;
            });
            
            row += `<td class="p-3 text-center font-bold bg-yellow-100">${etudiant.moyenne}</td></tr>`;
            
            rows += row;
        });
        
        $('#tableBody').html(rows);
    }

    // Fonction pour initialiser DataTable
    function initializeDataTable() {
        if(dataTable) {
            dataTable.destroy();
        }
        
        dataTable = $('#tableNotes').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            },
            pageLength: 25,
            order: [[1, 'asc']], // Tri par nom
            dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            responsive: true
        });
    }

    // Fonction pour réinitialiser DataTable
    function resetDataTable() {
        if(dataTable) {
            dataTable.destroy();
            dataTable = null;
        }
        currentModalites = [];
    }

    // Export CSV
    $('#btnExcel').click(function(){
        const ec = $('#ec').val();
        if(ec) {
            window.location.href = '?export=1&ec_id=' + ec;
        } else {
            alert('Veuillez sélectionner un élément constitutif.');
        }
    });
});
</script>

</body>
</html>