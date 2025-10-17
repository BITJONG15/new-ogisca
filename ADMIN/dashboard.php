<?php
session_start();

// Connexion à la base de données
$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) die("Erreur de connexion : " . $conn->connect_error);

// Vérification session
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
if ($result->num_rows === 0) { header('Location: logout.php'); exit; }
$user = $result->fetch_assoc();
$photo_url = 'C:\xampp\htdocs\SYSTOGISCA\ADMIN\uploads/' . $user['matricule'] . '.jpg';

// Fonction pour générer matricule unique
function genererMatricule() {
    return "EN" . time() . rand(100,999);
}

// Gestion AJAX pour DataTables
if(isset($_GET['ajax']) && $_GET['ajax'] == 1){
    $draw = $_GET['draw'];
    $start = $_GET['start'];
    $length = $_GET['length'];
    $searchValue = $_GET['search']['value'];

    $sql_base = "SELECT * FROM enseignants WHERE 1";
    if(!empty($searchValue)){
        $searchValueEsc = $conn->real_escape_string($searchValue);
        $sql_base .= " AND (nom LIKE '%$searchValueEsc%' OR prenom LIKE '%$searchValueEsc%' OR matricule LIKE '%$searchValueEsc%')";
    }
    $totalFiltered = $conn->query($sql_base)->num_rows;
    $sql_base .= " LIMIT $start, $length";
    $dataResult = $conn->query($sql_base);
    $data = [];
    while($row = $dataResult->fetch_assoc()) $data[] = $row;
    $totalRecords = $conn->query("SELECT * FROM enseignants")->num_rows;

    echo json_encode([
        "draw"=>intval($draw),
        "recordsTotal"=>$totalRecords,
        "recordsFiltered"=>$totalFiltered,
        "data"=>$data
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Enseignants</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
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

.popup { 
  display:none; 
  position:fixed; 
  inset:0; 
  background:rgba(0,0,0,0.4); 
  z-index:9999; 
  justify-content:center; 
  align-items:center; 
  padding: 1rem;
}

.popup.open { 
  display:flex; 
  animation: fadeIn 0.3s ease;
}

.popup-content { 
  background:white; 
  border-radius:16px; 
  padding: 2rem; 
  width:95%; 
  max-width:900px; 
  box-shadow:0 10px 40px rgba(0,0,0,0.2); 
  position:relative; 
  max-height:90vh; 
  overflow-y:auto;
  animation: slideUp 0.3s ease;
}

.close-btn { 
  position:absolute; 
  top:1rem; 
  right:1rem; 
  cursor:pointer; 
  font-size:1.5rem; 
  font-weight:bold; 
  color:#444; 
  transition:color 0.2s; 
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.close-btn:hover { 
  background: #f3f4f6;
  color:#e53e3e; 
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

.btn-secondary {
  background: var(--secondary);
  color: white;
}

.btn-secondary:hover {
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

.table-container {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.dataTables_wrapper {
  padding: 0.5rem;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUp {
  from { 
    opacity: 0;
    transform: translateY(20px);
  }
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
  
  .popup-content {
    padding: 1.5rem;
  }
}

/* Améliorations du contenu principal */
.main-content {
  background: linear-gradient(135deg, #f5f7fd 0%, #f0f4ff 100%);
  min-height: calc(100vh - 80px);
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

.action-buttons {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}

.data-table {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
  padding: 1rem;
}

.dataTables_wrapper .dataTables_info {
  padding: 1rem;
  color: #6b7280;
}

.dataTables_wrapper .dataTables_paginate {
  padding: 1rem;
}

.table-row-hover:hover {
  background-color: #f8fafc;
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
            <li class="nav-item active">
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

    <main class="main-content p-6 flex-1 overflow-auto">
        <!-- En-tête de page amélioré -->
        <div class="page-header">
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2">Gestion des Enseignants</h1>
                    <p class="text-blue-100 opacity-90">Gérez le personnel enseignant de l'institut</p>
                </div>
                <div class="flex gap-3 mt-4 md:mt-0">
                    <button onclick="window.location.href='attribution_ec.php';" class="btn btn-secondary bg-white bg-opacity-20 hover:bg-opacity-30 border-0">
                        <i class="fas fa-book"></i> Attribuer un cours
                    </button>
                    <button onclick="ouvrirPopup('popupAdd')" class="btn btn-primary bg-white text-blue-600 hover:bg-white hover:text-blue-700 border-0">
                        <i class="fas fa-plus"></i> Ajouter un enseignant
                    </button>
                </div>
            </div>
        </div>

        <!-- Cartes de statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Enseignants</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $conn->query("SELECT COUNT(*) FROM enseignants")->fetch_array()[0] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card secondary">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Hommes</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $conn->query("SELECT COUNT(*) FROM enseignants WHERE sexe = 'M'")->fetch_array()[0] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-male text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card accent">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Femmes</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $conn->query("SELECT COUNT(*) FROM enseignants WHERE sexe = 'F'")->fetch_array()[0] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-female text-amber-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau des enseignants -->
        <div class="data-table">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Liste des enseignants</h3>
                <table id="tableEnseignants" class="w-full text-sm text-gray-700 display" style="width:100%">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3 text-left font-semibold text-gray-600">Matricule</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Photo</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Nom</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Prénom</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Sexe</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Titre</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Grade</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Spécialité</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Domaine d'expertise</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Email</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Téléphone</th>
                            <th class="p-3 text-left font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </main>
    
    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<!-- POPUP Ajouter / Modifier (conservé identique) -->
<div id="popupAdd" class="popup">
    <div class="popup-content">
        <button type="button" class="close-btn" onclick="fermerPopup('popupAdd')">×</button>
        <h3 id="formTitle" class="text-xl font-semibold mb-6 text-gray-800 flex items-center gap-2">
            <i class="fas fa-user-plus text-blue-600"></i>
            <span>Ajouter un enseignant</span>
        </h3>

        <form id="formEnseignant" action="ajouter_enseignant.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="id" id="enseignantId" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="form-group">
                        <label class="form-label">Matricule</label>
                        <input type="text" id="matricule" name="matricule" readonly class="form-input bg-gray-100 cursor-not-allowed" value="<?=genererMatricule()?>" />
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nom <span class="text-red-500">*</span></label>
                        <input type="text" name="nom" id="nom" required class="form-input" placeholder="Entrez le nom" />
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Prénom</label>
                        <input type="text" name="prenom" id="prenom" class="form-input" placeholder="Entrez le prénom" />
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Sexe</label>
                        <select name="sexe" id="sexe" class="form-input">
                            <option value="">-- Sélectionner --</option>
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Titre</label>
                        <input type="text" name="titre" id="titre" class="form-input" placeholder="Ex: Dr, Prof, etc." />
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Grade</label>
                        <input type="text" name="grade" id="grade" class="form-input" placeholder="Ex: Maître de conférences" />
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div class="form-group">
                        <label class="form-label">Spécialité</label>
                        <input type="text" name="specialite" id="specialite" class="form-input" placeholder="Ex: Informatique" />
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Domaine d'expertise</label>
                        <input type="text" name="domaine_expertise" id="domaine_expertise" class="form-input" placeholder="Ex: Intelligence artificielle" />
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-input" placeholder="exemple@injs.edu" />
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" id="telephone" class="form-input" placeholder="+225 XX XX XX XX" />
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="mot_de_passe" id="mot_de_passe" class="form-input" autocomplete="new-password" placeholder="Laissez vide pour ne pas modifier" />
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Photo (jpg, png)</label>
                        <input type="file" name="photo" id="photo" accept="image/png, image/jpeg" class="form-input p-2" />
                        <div class="mt-3">
                            <img id="previewPhoto" class="w-24 h-24 object-cover rounded-full border-2 border-gray-200 hidden" alt="Preview" />
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" class="btn btn-outline" onclick="fermerPopup('popupAdd')">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer
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

// Fonctions existantes conservées identiques
function ouvrirPopup(id){ 
    document.getElementById(id).classList.add('open'); 
    if(id=='popupAdd'){
        resetForm(); 
        document.getElementById('matricule').value='<?=genererMatricule()?>'; 
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-user-plus text-blue-600"></i><span>Ajouter un enseignant</span>'; 
    } 
}

function fermerPopup(id){ 
    document.getElementById(id).classList.remove('open'); 
}

function resetForm(){ 
    document.getElementById('formEnseignant').reset(); 
    document.getElementById('enseignantId').value=''; 
    const preview=document.getElementById('previewPhoto'); 
    preview.src=''; 
    preview.classList.add('hidden'); 
}

document.getElementById('photo').addEventListener('change',function(e){ 
    const [file]=e.target.files; 
    const preview=document.getElementById('previewPhoto'); 
    if(file){ 
        preview.src=URL.createObjectURL(file); 
        preview.classList.remove('hidden'); 
    } else{ 
        preview.src=''; 
        preview.classList.add('hidden'); 
    } 
});

$(document).ready(function(){
    $('#tableEnseignants').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": "?ajax=1",
        "columns": [
            {"data":"matricule"},
            {"data":"photo","render":function(d){ 
                return d ? '<img src="'+d+'" class="w-10 h-10 rounded-full object-cover mx-auto"/>' : 
                '<div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center text-gray-500 mx-auto"><i class="fas fa-user text-sm"></i></div>'; 
            }},
            {"data":"nom"}, 
            {"data":"prenom"}, 
            {"data":"sexe", "render": function(data) {
                if (data === 'M') return 'Masculin';
                if (data === 'F') return 'Féminin';
                return data;
            }}, 
            {"data":"titre"}, 
            {"data":"grade"},
            {"data":"specialite"}, 
            {"data":"domaine_expertise"}, 
            {"data":"email"}, 
            {"data":"telephone"},
            {"data":"id","orderable":false,"render":function(data,type,row){
                return `<div class="flex space-x-2">
                    <button class="text-blue-600 hover:text-blue-800 p-1 rounded-full hover:bg-blue-50" title="Modifier" onclick='modifierEnseignant(${JSON.stringify(row)})'>
                        <i class="fas fa-edit"></i>
                    </button>
                    <a href="supprimer_enseignant.php?id=${data}" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet enseignant ?')" class="text-red-600 hover:text-red-800 p-1 rounded-full hover:bg-red-50" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>`;
            }}
        ],
        "pageLength":10,
        "lengthChange":false,
        "language":{
            "search":"Rechercher :",
            "paginate":{
                "first":"Premier",
                "last":"Dernier",
                "next":"Suivant",
                "previous":"Précédent"
            },
            "zeroRecords":"Aucun enseignant trouvé",
            "info":"Page _PAGE_ sur _PAGES_",
            "infoEmpty":"Aucun enseignant disponible",
            "infoFiltered":"(filtré à partir de _MAX_ enseignants)",
            "processing": "<div class='flex justify-center items-center'><i class='fas fa-spinner fa-spin mr-2'></i> Chargement...</div>"
        },
        "dom": "<'flex flex-col md:flex-row md:items-center justify-between'<'mb-4 md:mb-0'l>f>rt<'flex flex-col md:flex-row md:items-center justify-between'<'mb-4 md:mb-0'i>p>",
        "initComplete": function() {
            $('.dataTables_filter input').addClass('form-input');
            $('.dataTables_length select').addClass('form-input');
        },
        "createdRow": function(row, data, dataIndex) {
            $(row).addClass('table-row-hover');
        }
    });
});

function modifierEnseignant(data){
    ouvrirPopup('popupAdd');
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-user-edit text-blue-600"></i><span>Modifier l\'enseignant</span>';
    document.getElementById('enseignantId').value=data.id;
    document.getElementById('matricule').value=data.matricule;
    document.getElementById('nom').value=data.nom;
    document.getElementById('prenom').value=data.prenom;
    document.getElementById('sexe').value=data.sexe;
    document.getElementById('titre').value=data.titre;
    document.getElementById('grade').value=data.grade;
    document.getElementById('specialite').value=data.specialite;
    document.getElementById('domaine_expertise').value=data.domaine_expertise;
    document.getElementById('email').value=data.email;
    document.getElementById('telephone').value=data.telephone;
    
    if(data.photo) {
        document.getElementById('previewPhoto').src = data.photo;
        document.getElementById('previewPhoto').classList.remove('hidden');
    }
}
</script>
</body>
</html>