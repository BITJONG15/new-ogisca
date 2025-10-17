<?php
// Vérifier si une session n'est pas déjà active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli("localhost", "root", "", "gestion_academique");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Vérification session utilisateur
if (!isset($_SESSION['matricule'])) {
    header('Location: login.php');
    exit;
}

$matricule = $_SESSION['matricule'];
$sql = "SELECT nom, prenom, matricule FROM users WHERE matricule = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $matricule);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: logout.php');
    exit;
}

$user = $result->fetch_assoc();
$photo_url = 'https://www.svgrepo.com/show/382106/user-circle.svg';

$message = "";

// --- POST gestion ---
// Ajout Palier
if (isset($_POST['ajouter_palier'])) {
    $palier = trim($_POST['palier']);
    $division_id = (int)$_POST['division_id'];
    $niveau_id = (int)$_POST['niveau_id'];
    $ecs = $_POST['ecs'] ?? [];
    $date_creation = date('Y-m-d');

    if (!$palier) {
        $message = "Le nom du palier est requis.";
    } elseif (!$division_id || !$niveau_id) {
        $message = "Division et niveau obligatoires.";
    } elseif (count($ecs) === 0) {
        $message = "Veuillez choisir au moins un EC.";
    } else {
        $division_nom = $conn->query("SELECT nom FROM division WHERE id = $division_id")->fetch_assoc()['nom'];
        $niveau_nom = $conn->query("SELECT nom FROM niveau WHERE id = $niveau_id")->fetch_assoc()['nom'];
        
        $stmt = $conn->prepare("INSERT INTO paliers (nom, division, niveau, created_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $palier, $division_nom, $niveau_nom, $date_creation);
        
        if ($stmt->execute()) {
            $palier_id = $conn->insert_id;
            
            foreach ($ecs as $ec) {
                $stmt_ec = $conn->prepare("INSERT INTO palier_ec (palier_id, ID_EC) VALUES (?, ?)");
                $stmt_ec->bind_param("is", $palier_id, $ec);
                $stmt_ec->execute();
                $stmt_ec->close();
            }
            
            $message = "Palier ajouté avec succès.";
        } else {
            $message = "Erreur ajout palier : " . $stmt->error;
        }
        $stmt->close();
    }
}

// Programmer un palier
if (isset($_POST['programmer_palier'])) {
    $id = (int)$_POST['id_palier'];
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];

    if (!$date_debut || !$date_fin) {
        $message = "Les dates sont obligatoires.";
    } else {
        $check = $conn->query("SELECT id FROM programmations WHERE palier_id = $id");
        
        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE programmations SET date_debut=?, date_fin=? WHERE palier_id=?");
        } else {
            $stmt = $conn->prepare("INSERT INTO programmations (date_debut, date_fin, palier_id) VALUES (?, ?, ?)");
        }
        
        $stmt->bind_param("ssi", $date_debut, $date_fin, $id);
        if ($stmt->execute()) {
            $message = "Palier programmé avec succès.";
        } else {
            $message = "Erreur programmation : " . $stmt->error;
        }
        $stmt->close();
    }
}

// Supprimer palier
if (isset($_POST['supprimer_palier'])) {
    $id = (int)$_POST['id_palier_suppr'];
    
    $stmt_ec = $conn->prepare("DELETE FROM palier_ec WHERE palier_id = ?");
    $stmt_ec->bind_param("i", $id);
    $stmt_ec->execute();
    $stmt_ec->close();
    
    $stmt = $conn->prepare("DELETE FROM paliers WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Palier supprimé.";
    } else {
        $message = "Erreur suppression : " . $stmt->error;
    }
    $stmt->close();
}

// Modifier palier
if (isset($_POST['modifier_palier'])) {
    $id = $_POST['id_palier_mod'];
    $nom = trim($_POST['nom_palier_mod']);
    $division_id = $_POST['division_mod'];
    $niveau_id = $_POST['niveau_mod'];
    $ecs_mod = $_POST['ecs_mod'] ?? [];

    if ($nom === '' || !$division_id || !$niveau_id || empty($ecs_mod)) {
        $message = "Tous les champs sont obligatoires pour modifier un palier.";
    } else {
        $division_nom = $conn->query("SELECT nom FROM division WHERE id = $division_id")->fetch_assoc()['nom'];
        $niveau_nom = $conn->query("SELECT nom FROM niveau WHERE id = $niveau_id")->fetch_assoc()['nom'];
        
        $stmt = $conn->prepare("UPDATE paliers SET nom = ?, division = ?, niveau = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nom, $division_nom, $niveau_nom, $id);
        $stmt->execute();
        $stmt->close();

        $conn->query("DELETE FROM palier_ec WHERE palier_id = $id");

        foreach ($ecs_mod as $ec_id) {
            $stmt = $conn->prepare("INSERT INTO palier_ec (palier_id, ID_EC) VALUES (?, ?)");
            $stmt->bind_param("is", $id, $ec_id);
            $stmt->execute();
            $stmt->close();
        }
        $message = "Palier modifié avec succès.";
    }
}

// Récupérer les données
$divisions_res = $conn->query("SELECT id, nom FROM division ORDER BY nom");
$niveaux_res = $conn->query("SELECT id, nom, id_division FROM niveau ORDER BY nom");
$ecs_res = $conn->query("SELECT ID_EC, Nom_EC, id_niveau FROM element_constitutif ORDER BY Nom_EC");

// Pagination
$limit = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Récupérer le total des paliers
$total_result = $conn->query("SELECT COUNT(*) as total FROM paliers");
$total_row = $total_result->fetch_assoc();
$total_paliers = $total_row['total'];
$total_pages = ceil($total_paliers / $limit);

// Récupérer les paliers avec pagination
$programmations_res = $conn->query("
    SELECT p.*, pr.date_debut, pr.date_fin, 
           COUNT(pe.ID_EC) as nb_ec,
           GROUP_CONCAT(pe.ID_EC) as ec_ids
    FROM paliers p 
    LEFT JOIN programmations pr ON p.id = pr.palier_id 
    LEFT JOIN palier_ec pe ON p.id = pe.palier_id
    GROUP BY p.id, pr.date_debut, pr.date_fin
    ORDER BY p.id DESC
    LIMIT $limit OFFSET $offset
");

$ecs_map = [];
$ecs_result = $conn->query("SELECT ID_EC, Nom_EC FROM element_constitutif");
while ($ec = $ecs_result->fetch_assoc()) {
    $ecs_map[$ec['ID_EC']] = $ec['Nom_EC'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Gestion des Paliers & Programmations</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --primary: #4f46e5;
  --primary-light: #6366f1;
  --primary-dark: #4338ca;
  --secondary: #10b981;
  --accent: #f59e0b;
  --danger: #ef4444;
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

.topbar {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
}

.card {
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  background: white;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
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

.btn {
  padding: 0.6rem 1.2rem;
  border-radius: 10px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.btn-primary {
  background: var(--primary);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.btn-success {
  background: var(--secondary);
  color: white;
}

.btn-success:hover {
  background: #0d9669;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-danger {
  background: var(--danger);
  color: white;
}

.btn-danger:hover {
  background: #dc2626;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-outline {
  background: transparent;
  border: 1px solid var(--primary);
  color: var(--primary);
}

.btn-outline:hover {
  background: var(--primary);
  color: white;
}

.form-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  transition: all 0.2s;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #374151;
}

.form-group {
  margin-bottom: 1.25rem;
}

.popup {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  justify-content: center;
  align-items: center;
  z-index: 9999;
  padding: 1rem;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.popup.active {
  display: flex;
  opacity: 1;
  visibility: visible;
}

.popup-content {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  width: 95%;
  max-width: 600px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.2);
  position: relative;
  max-height: 90vh;
  overflow-y: auto;
  transform: translateY(20px);
  transition: transform 0.3s ease;
}

.popup.active .popup-content {
  transform: translateY(0);
}

.close-btn {
  position: absolute;
  top: 1rem;
  right: 1rem;
  cursor: pointer;
  font-size: 1.5rem;
  font-weight: bold;
  color: #444;
  transition: color 0.2s;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.close-btn:hover {
  background: #f3f4f6;
  color: #e53e3e;
}

.ec-container {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  padding: 1rem;
  background: #f9fafb;
}

.ec-checkbox {
  margin-bottom: 0.5rem;
}

.ec-checkbox label {
  cursor: pointer;
  display: flex;
  align-items: center;
  padding: 0.5rem;
  border-radius: 6px;
  transition: background-color 0.2s;
}

.ec-checkbox label:hover {
  background: #f3f4f6;
}

.ec-checkbox input {
  margin-right: 0.5rem;
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

.status-inactive {
  background: #fef3c7;
  color: #92400e;
}

.action-btn {
  padding: 0.5rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn:hover {
  transform: scale(1.1);
}

.table-container {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.ec-badge {
  background: #e0f2fe;
  color: #0369a1;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

/* Améliorations du contenu principal */
.main-content {
  background: linear-gradient(135deg, #f5f7fd 0%, #f0f4ff 100%);
  min-height: calc(100vh - 80px);
  overflow-y: auto;
}

.page-header {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  color: white;
  box-shadow: 0 8px 25px rgba(79, 70, 229, 0.2);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
  border-left: 4px solid var(--primary);
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.stat-card.secondary {
  border-left-color: var(--secondary);
}

.stat-card.accent {
  border-left-color: var(--accent);
}

.data-table {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.table-row-hover:hover {
  background-color: #f8fafc;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 0.5rem;
  margin-top: 2rem;
  padding: 1rem;
}

.page-link {
  padding: 0.5rem 1rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  text-decoration: none;
  color: #374151;
  transition: all 0.2s;
  font-weight: 500;
}

.page-link:hover {
  background: #f3f4f6;
}

.page-link.active {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
}

.page-info {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0 1rem;
}

/* Scrollable content */
.scrollable-content {
  max-height: calc(100vh - 200px);
  overflow-y: auto;
  padding-right: 0.5rem;
}

.scrollable-content::-webkit-scrollbar {
  width: 6px;
}

.scrollable-content::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.scrollable-content::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

.scrollable-content::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

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
  
  .popup-content {
    padding: 1.5rem;
    max-width: 95%;
  }
  
  .pagination {
    flex-wrap: wrap;
    gap: 0.25rem;
  }
  
  .page-link {
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
  }
}
</style>
</head>
<body class="flex bg-gray-100 min-h-screen">

<!-- Overlay for mobile menu -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar EXACTEMENT comme fourni -->
<div class="sidebar w-64 h-screen text-white p-6 flex flex-col fixed lg:relative z-50">
    <div class="flex items-center gap-3 mb-8">
        <img src="logo simple sans fond.png" class="w-10 h-10 rounded" alt="logo" />
        <h1 class="text-xl font-bold">OGISCA - INJS</h1>
        <button class="lg:hidden ml-auto text-2xl" id="closeSidebar">
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
            <li class="nav-item active">
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
            <li class="nav-item">
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
    <!-- Topbar EXACTEMENT comme fourni -->
    <!-- Topbar repositionné -->
<header class="topbar flex items-center justify-between px-6 py-4 sticky top-0 z-40">
  <div class="flex items-center space-x-4">
    <!-- Bouton burger -->
    <button id="btnMenuToggle" class="lg:hidden text-gray-700 focus:outline-none" aria-label="Toggle menu">
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
    
    <div class="user-dropdown">
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

    <main class="main-content">
        <div class="scrollable-content p-6">
            <!-- En-tête de page amélioré -->
            <div class="page-header">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Gestion des Paliers & Programmations</h1>
                        <p class="text-blue-100 opacity-90">Programmez les corrections et gestion des paliers</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <button onclick="ouvrirPopupAjouter()" class="btn btn-primary bg-white text-blue-600 hover:bg-white hover:text-blue-700 border-0">
                            <i class="fas fa-plus"></i> Ajouter un Palier
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cartes de statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Paliers</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $total_paliers; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-layer-group text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card secondary">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Paliers Actifs</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php 
                                $paliers_actifs = $conn->query("SELECT COUNT(*) as total FROM programmations WHERE date_debut <= CURDATE() AND date_fin >= CURDATE()")->fetch_assoc()['total'];
                                echo $paliers_actifs;
                                ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-play-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card accent">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Paliers Programmes</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php 
                                $paliers_programmes = $conn->query("SELECT COUNT(*) as total FROM programmations WHERE date_fin >= CURDATE()")->fetch_assoc()['total'];
                                echo $paliers_programmes;
                                ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-amber-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="card p-4 bg-green-50 border-l-4 border-green-500 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <p class="text-green-700"><?= htmlspecialchars($message) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Liste des paliers -->
            <div class="data-table">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4 text-gray-800 flex items-center gap-2">
                        <i class="fas fa-list-alt text-blue-600"></i> Liste des paliers programmés
                        <span class="text-sm font-normal text-gray-500 ml-2">(<?php echo $total_paliers; ?> paliers au total)</span>
                    </h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Palier</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Division/Niveau</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Éléments constitutifs</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                if ($programmations_res->num_rows > 0) {
                                    $programmations_res->data_seek(0);
                                    while ($p = $programmations_res->fetch_assoc()): 
                                        $ec_ids = !empty($p['ec_ids']) ? explode(',', $p['ec_ids']) : [];
                                        $is_programmed = ($p['date_debut'] && $p['date_fin']);
                                        $is_active = $is_programmed && (date('Y-m-d') >= $p['date_debut'] && date('Y-m-d') <= $p['date_fin']);
                                        $nb_ec = $p['nb_ec'] ?: 0;
                                ?>
                                    <tr class="table-row-hover">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nom']) ?></div>
                                            <div class="text-sm text-gray-500">ID: <?= $p['id'] ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?= htmlspecialchars($p['division']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($p['niveau']) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <span class="ec-badge">
                                                    <i class="fas fa-book mr-1"></i><?= $nb_ec ?> EC(s)
                                                </span>
                                                <div class="text-sm text-gray-900 max-w-xs truncate">
                                                    <?php 
                                                    $ecs_display = [];
                                                    foreach (array_slice($ec_ids, 0, 3) as $ec_id) {
                                                        if (isset($ecs_map[$ec_id])) {
                                                            $ecs_display[] = $ecs_map[$ec_id];
                                                        }
                                                    }
                                                    echo implode(', ', $ecs_display);
                                                    if (count($ec_ids) > 3) echo '...';
                                                    ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($is_programmed): ?>
                                                <div class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($p['date_debut'])) ?></div>
                                                <div class="text-sm text-gray-900">au <?= date('d/m/Y', strtotime($p['date_fin'])) ?></div>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-400">Non programmé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($is_active): ?>
                                                <span class="status-badge status-active">
                                                    <i class="fas fa-play-circle mr-1"></i> Actif
                                                </span>
                                            <?php elseif ($is_programmed): ?>
                                                <span class="status-badge status-inactive">
                                                    <i class="fas fa-pause-circle mr-1"></i> Programmé
                                                </span>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="ouvrirPopupProgrammer(<?= $p['id'] ?>)" class="action-btn text-blue-600 hover:text-blue-800" title="Programmer">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </button>
                                                <button onclick="ouvrirPopupModifier(<?= $p['id'] ?>)" class="action-btn text-yellow-600 hover:text-yellow-800" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="ouvrirPopupSupprimer(<?= $p['id'] ?>)" class="action-btn text-red-600 hover:text-red-800" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; 
                                } else { ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-layer-group text-4xl text-gray-300 mb-3"></i>
                                            <p class="text-lg font-medium">Aucun palier trouvé</p>
                                            <p class="text-sm mt-2">Cliquez sur "Ajouter un Palier" pour créer votre premier palier</p>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="page-link">
                                    <i class="fas fa-chevron-left mr-1"></i> Précédent
                                </a>
                            <?php endif; ?>

                            <span class="page-info">
                                Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                            </span>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="page-link">
                                    Suivant <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?php echo date('Y'); ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<!-- Popup Ajouter -->
<div class="popup" id="popupAjouter">
    <div class="popup-content">
        <button type="button" class="close-btn" onclick="fermerPopup('popupAjouter')">×</button>
        <h3 class="text-xl font-semibold mb-6 text-gray-800 flex items-center gap-2">
            <i class="fas fa-plus-circle text-blue-600"></i> Ajouter un nouveau palier
        </h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="ajouter_palier" value="1">
            <div class="form-group">
                <label class="form-label">Nom du palier <span class="text-red-500">*</span></label>
                <input type="text" name="palier" required class="form-input" placeholder="Ex: Palier de rattrapage 2024">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Division <span class="text-red-500">*</span></label>
                    <select id="division" name="division_id" required class="form-input">
                        <option value="">Sélectionner une division</option>
                        <?php 
                        $divisions_res->data_seek(0);
                        while ($div = $divisions_res->fetch_assoc()): ?>
                            <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nom']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Niveau <span class="text-red-500">*</span></label>
                    <select id="niveau" name="niveau_id" required class="form-input" disabled>
                        <option value="">Sélectionner d'abord une division</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Éléments constitutifs (EC) <span class="text-red-500">*</span></label>
                <div id="ec-container" class="ec-container">
                    <p class="text-gray-500 text-center py-4">Veuillez d'abord sélectionner un niveau</p>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" class="btn btn-outline" onclick="fermerPopup('popupAjouter')">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Créer le palier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Popup Programmer -->
<div class="popup" id="popupProgrammer">
    <div class="popup-content">
        <button type="button" class="close-btn" onclick="fermerPopup('popupProgrammer')">×</button>
        <h3 class="text-xl font-semibold mb-6 text-gray-800 flex items-center gap-2">
            <i class="fas fa-calendar-alt text-blue-600"></i> Programmer le palier
        </h3>
        <form method="POST">
            <input type="hidden" name="id_palier" id="prog_id" />
            <div class="space-y-4">
                <div class="form-group">
                    <label class="form-label">Date de début <span class="text-red-500">*</span></label>
                    <input type="date" name="date_debut" id="date_debut_prog" required class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Date de fin <span class="text-red-500">*</span></label>
                    <input type="date" name="date_fin" id="date_fin_prog" required class="form-input">
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" class="btn btn-outline" onclick="fermerPopup('popupProgrammer')">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" name="programmer_palier" class="btn btn-success">
                    <i class="fas fa-calendar-check"></i> Programmer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Popup Supprimer -->
<div class="popup" id="popupSupprimer">
    <div class="popup-content">
        <button type="button" class="close-btn" onclick="fermerPopup('popupSupprimer')">×</button>
        <h3 class="text-xl font-semibold mb-6 text-gray-800 flex items-center gap-2">
            <i class="fas fa-trash text-red-600"></i> Confirmer la suppression
        </h3>
        <form method="POST">
            <input type="hidden" name="id_palier_suppr" id="suppr_id" />
            <p class="text-gray-600 mb-4">Êtes-vous sûr de vouloir supprimer ce palier ? Cette action est irréversible.</p>
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" class="btn btn-outline" onclick="fermerPopup('popupSupprimer')">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" name="supprimer_palier" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Popup Modifier -->
<div class="popup" id="popupModifier">
    <div class="popup-content">
        <button type="button" class="close-btn" onclick="fermerPopup('popupModifier')">×</button>
        <h3 class="text-xl font-semibold mb-6 text-gray-800 flex items-center gap-2">
            <i class="fas fa-edit text-yellow-600"></i> Modifier le palier
        </h3>
        <form method="POST">
            <input type="hidden" name="id_palier_mod" id="mod_id" />
            <div class="space-y-4">
                <div class="form-group">
                    <label class="form-label">Nom du palier <span class="text-red-500">*</span></label>
                    <input type="text" name="nom_palier_mod" id="mod_nom" required class="form-input">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Division <span class="text-red-500">*</span></label>
                        <select id="mod_division" name="division_mod" required class="form-input">
                            <option value="">Sélectionner une division</option>
                            <?php 
                            $divisions_res->data_seek(0);
                            while ($div = $divisions_res->fetch_assoc()): ?>
                                <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nom']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Niveau <span class="text-red-500">*</span></label>
                        <select id="mod_niveau" name="niveau_mod" required class="form-input" disabled>
                            <option value="">Sélectionner d'abord une division</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Éléments constitutifs (EC) <span class="text-red-500">*</span></label>
                    <div id="mod_ec_container" class="ec-container">
                        <p class="text-gray-500 text-center py-4">Veuillez d'abord sélectionner un niveau</p>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" class="btn btn-outline" onclick="fermerPopup('popupModifier')">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" name="modifier_palier" class="btn btn-primary">
                    <i class="fas fa-save"></i> Modifier
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Menu mobile OBLIGATOIRE comme fourni
document.getElementById('btnMenuToggle').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.add('open');
    document.getElementById('overlay').classList.add('active');
});

document.getElementById('closeSidebar').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('active');
});

document.getElementById('overlay').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.remove('open');
    this.classList.remove('active');
});

// Fonctions pour charger les données dynamiquement
function chargerNiveaux(divisionId, niveauSelectId, niveauId = null) {
    if (!divisionId) {
        document.getElementById(niveauSelectId).innerHTML = '<option value="">Sélectionner d\'abord une division</option>';
        document.getElementById(niveauSelectId).disabled = true;
        return;
    }
    
    fetch('get_niveaux.php?division_id=' + divisionId)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById(niveauSelectId);
            select.innerHTML = '<option value="">Sélectionner un niveau</option>';
            
            data.forEach(niveau => {
                const option = document.createElement('option');
                option.value = niveau.id;
                option.textContent = niveau.nom;
                if (niveauId && niveau.id == niveauId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            select.disabled = false;
        })
        .catch(error => {
            console.error('Erreur chargement niveaux:', error);
        });
}

function chargerECs(niveauId, ecContainerId, ecsSelectionnes = []) {
    const ecContainer = document.getElementById(ecContainerId);
    
    if (!niveauId) {
        ecContainer.innerHTML = '<p class="text-gray-500 text-center py-4">Veuillez d\'abord sélectionner un niveau</p>';
        return;
    }
    
    fetch('get_ecs.php?niveau_id=' + niveauId)
        .then(response => response.json())
        .then(data => {
            ecContainer.innerHTML = '';
            
            if (data.length === 0) {
                ecContainer.innerHTML = '<p class="text-gray-500 text-center py-4">Aucun EC disponible pour ce niveau</p>';
                return;
            }
            
            data.forEach(ec => {
                const div = document.createElement('div');
                div.className = 'ec-checkbox';
                
                const label = document.createElement('label');
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = ecContainerId === 'ec-container' ? 'ecs[]' : 'ecs_mod[]';
                checkbox.value = ec.ID_EC;
                checkbox.className = 'mr-2';
                
                if (ecsSelectionnes.includes(ec.ID_EC)) {
                    checkbox.checked = true;
                }
                
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(ec.Nom_EC + " (" + ec.ID_EC + ")"));
                div.appendChild(label);
                ecContainer.appendChild(div);
            });
        })
        .catch(error => {
            console.error('Erreur chargement ECs:', error);
            ecContainer.innerHTML = '<p class="text-red-500 text-center py-4">Erreur lors du chargement des ECs</p>';
        });
}

// Événements pour le formulaire d'ajout
document.getElementById('division').addEventListener('change', function() {
    const divisionId = this.value;
    chargerNiveaux(divisionId, 'niveau');
    document.getElementById('ec-container').innerHTML = '<p class="text-gray-500 text-center py-4">Veuillez d\'abord sélectionner un niveau</p>';
});

document.getElementById('niveau').addEventListener('change', function() {
    const niveauId = this.value;
    chargerECs(niveauId, 'ec-container');
});

// Événements pour le formulaire de modification
document.getElementById('mod_division').addEventListener('change', function() {
    const divisionId = this.value;
    chargerNiveaux(divisionId, 'mod_niveau');
    document.getElementById('mod_ec_container').innerHTML = '<p class="text-gray-500 text-center py-4">Veuillez d\'abord sélectionner un niveau</p>';
});

document.getElementById('mod_niveau').addEventListener('change', function() {
    const niveauId = this.value;
    chargerECs(niveauId, 'mod_ec_container');
});

// Gestion des popups
function ouvrirPopupAjouter() {
    document.getElementById('popupAjouter').classList.add('active');
    // Réinitialiser le formulaire
    document.querySelector('#popupAjouter form').reset();
    document.getElementById('niveau').innerHTML = '<option value="">Sélectionner d\'abord une division</option>';
    document.getElementById('niveau').disabled = true;
    document.getElementById('ec-container').innerHTML = '<p class="text-gray-500 text-center py-4">Veuillez d\'abord sélectionner un niveau</p>';
}

function ouvrirPopupProgrammer(id) {
    document.getElementById('prog_id').value = id;
    document.getElementById('date_debut_prog').value = '';
    document.getElementById('date_fin_prog').value = '';
    document.getElementById('popupProgrammer').classList.add('active');
}

function ouvrirPopupSupprimer(id) {
    document.getElementById('suppr_id').value = id;
    document.getElementById('popupSupprimer').classList.add('active');
}

function ouvrirPopupModifier(id) {
    fetch('get_palier.php?id=' + id)
        .then(response => response.json())
        .then(palier => {
            document.getElementById('mod_id').value = palier.id;
            document.getElementById('mod_nom').value = palier.nom;
            
            const modDivision = document.getElementById('mod_division');
            modDivision.value = palier.division_id;
            
            chargerNiveaux(palier.division_id, 'mod_niveau', palier.niveau_id);
            
            setTimeout(() => {
                chargerECs(palier.niveau_id, 'mod_ec_container', palier.ecs);
            }, 300);
            
            document.getElementById('popupModifier').classList.add('active');
        })
        .catch(error => {
            console.error('Erreur chargement palier:', error);
            alert('Erreur lors du chargement des données du palier');
        });
}

function fermerPopup(id) {
    document.getElementById(id).classList.remove('active');
}

// Fermer les popups avec la touche Echap
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const popups = document.querySelectorAll('.popup.active');
        popups.forEach(popup => {
            popup.classList.remove('active');
        });
    }
});
</script>

</body>
</html>