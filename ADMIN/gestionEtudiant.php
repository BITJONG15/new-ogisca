<?php
session_start();

// Connexion à la base de données
$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

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

// Import FPDF si export PDF demandé
if (isset($_GET['action']) && $_GET['action'] === 'export_pdf') {
    require('include/fpdf/fpdf.php');
    exportPdf();
    exit;
}

// Export CSV si demandé
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    exportCsv();
    exit;
}

// Traitement POST (ajout, modifier, supprimer)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'ajouter') {
            ajouterEtudiant();
        } elseif ($_POST['action'] === 'modifier') {
            modifierEtudiant();
        } elseif ($_POST['action'] === 'supprimer') {
            supprimerEtudiant();
        }
    }
    header('Location: gestionEtudiant.php');
    exit;
}

// Fonctions traitement
function ajouterEtudiant() {
    global $conn;
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $sexe = $_POST['sexe'];
    $date_naissance = $_POST['date_naissance'];
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $adresse = trim($_POST['adresse']);
    $niveau_id = intval($_POST['niveau_id']);
    $division_id = intval($_POST['division_id']);

    $matricule = 'ETU' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $mot_de_passe_defaut = 'injs1234';
    $mot_de_passe_hash = password_hash($mot_de_passe_defaut, PASSWORD_DEFAULT);

    $stmtUser = $conn->prepare("INSERT INTO users (matricule, mot_de_passe, nom, prenom, role, date_creation) VALUES (?, ?, ?, ?, 'etudiant', NOW())");
    $stmtUser->bind_param("ssss", $matricule, $mot_de_passe_hash, $nom, $prenom);
    $stmtUser->execute();
    $user_id = $stmtUser->insert_id;
    $stmtUser->close();

    $stmtEtu = $conn->prepare("INSERT INTO etudiants (matricule, nom, prenom, sexe, date_naissance, email, telephone, adresse, user_id, created_at, niveau_id, division_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
    $stmtEtu->bind_param("ssssssssiii", $matricule, $nom, $prenom, $sexe, $date_naissance, $email, $telephone, $adresse, $user_id, $niveau_id, $division_id);
    $stmtEtu->execute();
    $stmtEtu->close();
}

function modifierEtudiant() {
    global $conn;
    $id = intval($_POST['id']);
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $adresse = trim($_POST['adresse']);
    $niveau_id = intval($_POST['niveau_id']);
    $division_id = intval($_POST['division_id']);

    $stmt = $conn->prepare("UPDATE etudiants SET nom=?, prenom=?, email=?, telephone=?, adresse=?, niveau_id=?, division_id=? WHERE id=?");
    $stmt->bind_param("ssssssii", $nom, $prenom, $email, $telephone, $adresse, $niveau_id, $division_id, $id);
    $stmt->execute();
    $stmt->close();
}

function supprimerEtudiant() {
    global $conn;
    $id = intval($_POST['id']);
    $conn->query("DELETE FROM etudiants WHERE id = $id");
}

function exportCsv() {
    global $conn;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $where = "";
    if ($search !== '') {
        $search_esc = $conn->real_escape_string($search);
        $where = "WHERE (e.nom LIKE '%$search_esc%' OR e.prenom LIKE '%$search_esc%' OR e.matricule LIKE '%$search_esc%')";
    }

    $sql = "SELECT e.matricule, e.nom, e.prenom, n.nom AS niveau_nom, d.nom AS division_nom, e.email, e.telephone 
            FROM etudiants e
            LEFT JOIN niveau n ON e.niveau_id = n.id
            LEFT JOIN division d ON e.division_id = d.id
            $where
            ORDER BY e.id DESC";

    $result = $conn->query($sql);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=etudiants_export.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Matricule', 'Nom', 'Prénom', 'Niveau', 'Division', 'Email', 'Téléphone']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['matricule'],
            $row['nom'],
            $row['prenom'],
            $row['niveau_nom'],
            $row['division_nom'],
            $row['email'],
            $row['telephone']
        ]);
    }

    fclose($output);
    exit;
}

function exportPdf() {
    global $conn;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $where = "";
    if ($search !== '') {
        $search_esc = $conn->real_escape_string($search);
        $where = "WHERE (e.nom LIKE '%$search_esc%' OR e.prenom LIKE '%$search_esc%' OR e.matricule LIKE '%$search_esc%')";
    }

    $sql = "SELECT e.matricule, e.nom, e.prenom, n.nom AS niveau_nom, d.nom AS division_nom, e.email, e.telephone 
            FROM etudiants e
            LEFT JOIN niveau n ON e.niveau_id = n.id
            LEFT JOIN division d ON e.division_id = d.id
            $where
            ORDER BY e.id DESC";

    $result = $conn->query($sql);

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'Liste des Etudiants',0,1,'C');

    $pdf->SetFont('Arial','B',10);
    $pdf->SetFillColor(200,200,200);
    $header = ['Matricule', 'Nom', 'Prénom', 'Niveau', 'Division', 'Email', 'Téléphone'];
    $w = [25, 30, 30, 25, 25, 40, 30];

    foreach ($header as $i => $col) {
        $pdf->Cell($w[$i],7,$col,1,0,'C',true);
    }
    $pdf->Ln();

    $pdf->SetFont('Arial','',9);

    while($row = $result->fetch_assoc()){
        $pdf->Cell($w[0],6,$row['matricule'],1);
        $pdf->Cell($w[1],6,$row['nom'],1);
        $pdf->Cell($w[2],6,$row['prenom'],1);
        $pdf->Cell($w[3],6,$row['niveau_nom'],1);
        $pdf->Cell($w[4],6,$row['division_nom'],1);
        $pdf->Cell($w[5],6,$row['email'],1);
        $pdf->Cell($w[6],6,$row['telephone'],1);
        $pdf->Ln();
    }

    $pdf->Output('D', 'etudiants_export.pdf');
    exit;
}

// Récupération des données pour affichage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "";
if ($search !== '') {
    $search_esc = $conn->real_escape_string($search);
    $where = "WHERE (e.nom LIKE '%$search_esc%' OR e.prenom LIKE '%$search_esc%' OR e.matricule LIKE '%$search_esc%')";
}

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$sql = "SELECT e.id, e.matricule, e.nom, e.prenom, e.sexe, e.date_naissance, e.email, e.telephone, e.adresse, n.nom AS niveau_nom, d.nom AS division_nom, e.niveau_id, e.division_id
        FROM etudiants e
        LEFT JOIN niveau n ON e.niveau_id = n.id
        LEFT JOIN division d ON e.division_id = d.id
        $where
        ORDER BY e.id DESC
        LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);
$etudiants = [];
if ($result) {
    $etudiants = $result->fetch_all(MYSQLI_ASSOC);
}

$totalResult = $conn->query("SELECT COUNT(*) as total FROM etudiants e $where");
$totalRow = $totalResult->fetch_assoc();
$total = $totalRow['total'];
$totalPages = ceil($total / $limit);

// Récupération niveaux/divisions pour selects
$niveauRes = $conn->query("SELECT * FROM niveau");
$niveaux = $niveauRes ? $niveauRes->fetch_all(MYSQLI_ASSOC) : [];

$divisionRes = $conn->query("SELECT * FROM division");
$divisions = $divisionRes ? $divisionRes->fetch_all(MYSQLI_ASSOC) : [];

// Stats pour Chart.js
$statsQuery = $conn->query("SELECT niveau_id, COUNT(*) as total FROM etudiants GROUP BY niveau_id");
$promotions = [];
$totals = [];
if ($statsQuery) {
    while ($row = $statsQuery->fetch_assoc()) {
        $promotions[] = "Niveau " . $row['niveau_id'];
        $totals[] = $row['total'];
    }
} else {
    $promotions = ['Aucune donnée'];
    $totals = [0];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des Étudiants</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    .modal { 
      position: fixed; 
      inset: 0; 
      background: rgba(0,0,0,0.4); 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      z-index: 9999;
      padding: 1rem;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .modal.open {
      opacity: 1;
      visibility: visible;
    }

    .modal-box { 
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

    .modal.open .modal-box {
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

    .pagination {
      display: flex;
      justify-content: center;
      gap: 0.5rem;
      margin-top: 1rem;
    }

    .page-link {
      padding: 0.5rem 1rem;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      text-decoration: none;
      color: #374151;
    }

    .page-link.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    .page-link:hover:not(.active) {
      background: #f3f4f6;
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

    .data-table {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .table-row-hover:hover {
      background-color: #f8fafc;
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
      
      .modal-box {
        padding: 1.5rem;
        max-width: 95%;
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
            <li class="nav-item active">
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
                    <h1 class="text-3xl font-bold mb-2">Gestion des Étudiants</h1>
                    <p class="text-blue-100 opacity-90">Gérez les étudiants de l'institut</p>
                </div>
                <div class="flex gap-3 mt-4 md:mt-0">
                    <a href="?action=export_csv&search=<?php echo urlencode($search); ?>" class="btn btn-secondary bg-white bg-opacity-20 hover:bg-opacity-30 border-0">
                        <i class="fas fa-file-csv"></i> Exporter CSV
                    </a>
                    <a href="?action=export_pdf&search=<?php echo urlencode($search); ?>" class="btn btn-secondary bg-white bg-opacity-20 hover:bg-opacity-30 border-0">
                        <i class="fas fa-file-pdf"></i> Exporter PDF
                    </a>
                    <button onclick="openModal('addModal')" class="btn btn-primary bg-white text-blue-600 hover:bg-white hover:text-blue-700 border-0">
                        <i class="fas fa-plus"></i> Ajouter un étudiant
                    </button>
                </div>
            </div>
        </div>

        <!-- Cartes de statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Étudiants</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $total; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card secondary">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Hommes</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php 
                            $hommes = 0;
                            foreach($etudiants as $etudiant) {
                                if($etudiant['sexe'] === 'M') $hommes++;
                            }
                            echo $hommes;
                            ?>
                        </p>
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
                        <p class="text-2xl font-bold text-gray-800">
                            <?php 
                            $femmes = 0;
                            foreach($etudiants as $etudiant) {
                                if($etudiant['sexe'] === 'F') $femmes++;
                            }
                            echo $femmes;
                            ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-female text-amber-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        
        <!-- Recherche et actions -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <form method="get" class="flex items-center gap-2 flex-1 min-w-[300px]">
                <div class="relative flex-1">
                    <input type="text" name="search" placeholder="Rechercher un étudiant..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="form-input pl-10">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <button class="btn btn-primary">
                    <i class="fas fa-search"></i> Rechercher
                </button>
            </form>
        </div>

        <!-- Tableau des étudiants -->
        <div class="data-table">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Liste des étudiants</h3>
                <?php if (!empty($etudiants)): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matricule</th>
                                <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                                <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prénom</th>
                                <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                                <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Niveau</th>
                                <th class="p-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($etudiants as $etudiant): ?>
                                <tr class="table-row-hover">
                                    <td class="p-3 text-sm text-gray-900"><?php echo $etudiant['id']; ?></td>
                                    <td class="p-3 text-sm text-gray-900 font-mono"><?php echo htmlspecialchars($etudiant['matricule']); ?></td>
                                    <td class="p-3 text-sm text-gray-900"><?php echo htmlspecialchars($etudiant['nom']); ?></td>
                                    <td class="p-3 text-sm text-gray-900"><?php echo htmlspecialchars($etudiant['prenom']); ?></td>
                                    <td class="p-3 text-sm text-gray-900"><?php echo htmlspecialchars($etudiant['email']); ?></td>
                                    <td class="p-3 text-sm text-gray-900"><?php echo htmlspecialchars($etudiant['telephone']); ?></td>
                                    <td class="p-3 text-sm text-gray-900">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                            <?php echo htmlspecialchars($etudiant['niveau_nom'] ?? 'Non défini'); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <div class="flex justify-center gap-2">
                                            <button onclick="openEditModal(<?php echo $etudiant['id']; ?>)" class="text-yellow-600 hover:text-yellow-800 p-2 rounded-full hover:bg-yellow-50" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="openDeleteModal(<?php echo $etudiant['id']; ?>)" class="text-red-600 hover:text-red-800 p-2 rounded-full hover:bg-red-50" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-user-graduate text-4xl text-gray-300 mb-4"></i>
                        <p class="text-lg font-medium text-gray-500">Aucun étudiant trouvé</p>
                        <p class="text-sm text-gray-400 mt-2">
                            <?php echo !empty($search) ? 'Aucun résultat pour "' . htmlspecialchars($search) . '"' : 'La liste des étudiants est vide'; ?>
                        </p>
                        <?php if (!empty($search)): ?>
                            <a href="gestionEtudiant.php" class="btn btn-outline mt-4">
                                <i class="fas fa-times"></i> Effacer la recherche
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?php echo date('Y'); ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<!-- Modal Ajouter -->
<div id="addModal" class="modal">
  <div class="modal-box">
    <button type="button" class="close-btn" onclick="closeModal('addModal')">×</button>
    <h3 class="text-xl font-semibold mb-6 text-gray-800 flex items-center gap-2">
      <i class="fas fa-user-plus text-blue-600"></i> Ajouter un étudiant
    </h3>
    <form method="post" action="" class="space-y-4">
      <input type="hidden" name="action" value="ajouter">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Nom <span class="text-red-500">*</span></label>
          <input name="nom" placeholder="Nom" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Prénom <span class="text-red-500">*</span></label>
          <input name="prenom" placeholder="Prénom" class="form-input" required>
        </div>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Sexe <span class="text-red-500">*</span></label>
          <select name="sexe" class="form-input" required>
            <option value="">Sélectionner</option>
            <option value="M">Masculin</option>
            <option value="F">Féminin</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Date de naissance</label>
          <input name="date_naissance" type="date" class="form-input">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input name="email" placeholder="Email" type="email" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input name="telephone" placeholder="Téléphone" class="form-input">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Adresse</label>
        <textarea name="adresse" placeholder="Adresse" class="form-input" rows="3"></textarea>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Niveau <span class="text-red-500">*</span></label>
          <select name="niveau_id" class="form-input" required>
            <option value="">Sélectionner un niveau</option>
            <?php foreach ($niveaux as $niveau): ?>
              <option value="<?php echo $niveau['id']; ?>"><?php echo htmlspecialchars($niveau['nom']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Division</label>
          <select name="division_id" class="form-input">
            <option value="">Sélectionner une division</option>
            <?php foreach ($divisions as $division): ?>
              <option value="<?php echo $division['id']; ?>"><?php echo htmlspecialchars($division['nom']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
        <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">
          <i class="fas fa-times"></i> Annuler
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Modifier -->
<div id="editModal" class="modal">
  <div class="modal-box">
    <button type="button" class="close-btn" onclick="closeModal('editModal')">×</button>
    <h3 class="text-xl font-semibold mb-6 text-gray-800 flex items-center gap-2">
      <i class="fas fa-edit text-yellow-600"></i> Modifier un étudiant
    </h3>
    <form method="post" action="" class="space-y-4">
      <input type="hidden" name="action" value="modifier">
      <input type="hidden" name="id" id="editId">
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Nom <span class="text-red-500">*</span></label>
          <input name="nom" id="editNom" placeholder="Nom" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Prénom <span class="text-red-500">*</span></label>
          <input name="prenom" id="editPrenom" placeholder="Prénom" class="form-input" required>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input name="email" id="editEmail" placeholder="Email" type="email" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input name="telephone" id="editTelephone" placeholder="Téléphone" class="form-input">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Adresse</label>
        <textarea name="adresse" id="editAdresse" placeholder="Adresse" class="form-input" rows="3"></textarea>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Niveau <span class="text-red-500">*</span></label>
          <select name="niveau_id" id="editNiveauId" class="form-input" required>
            <option value="">Sélectionner un niveau</option>
            <?php foreach ($niveaux as $niveau): ?>
              <option value="<?php echo $niveau['id']; ?>"><?php echo htmlspecialchars($niveau['nom']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Division</label>
          <select name="division_id" id="editDivisionId" class="form-input">
            <option value="">Sélectionner une division</option>
            <?php foreach ($divisions as $division): ?>
              <option value="<?php echo $division['id']; ?>"><?php echo htmlspecialchars($division['nom']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">
          <i class="fas fa-times"></i> Annuler
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Modifier
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Supprimer -->
<div id="deleteModal" class="modal">
  <div class="modal-box">
    <button type="button" class="close-btn" onclick="closeModal('deleteModal')">×</button>
    <h3 class="text-xl font-semibold mb-6 text-gray-800 flex items-center gap-2">
      <i class="fas fa-trash text-red-600"></i> Supprimer l'étudiant
    </h3>
    <p class="text-gray-600 mb-4">Êtes-vous sûr de vouloir supprimer cet étudiant ? Cette action est irréversible.</p>
    <form method="post" action="">
      <input type="hidden" name="action" value="supprimer">
      <input type="hidden" name="id" id="deleteId">
      <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
        <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">
          <i class="fas fa-times"></i> Annuler
        </button>
        <button type="submit" class="btn btn-danger">
          <i class="fas fa-trash"></i> Supprimer
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

// Modal functions
function openModal(id) {
    document.getElementById(id).classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

function openEditModal(id) {
    // Pour l'instant, on ouvre juste le modal
    // Dans une vraie application, on ferait une requête AJAX pour récupérer les données
    openModal('editModal');
}

function openDeleteModal(id) {
    document.getElementById('deleteId').value = id;
    openModal('deleteModal');
}

// Chart.js
const ctx = document.getElementById("promoChart");
if (ctx) {
    new Chart(ctx, {
        type: "doughnut",
        data: {
            labels: <?php echo json_encode($promotions); ?>,
            datasets: [{
                data: <?php echo json_encode($totals); ?>,
                backgroundColor: ["#3b82f6", "#10b981", "#f59e0b", "#8b5cf6", "#ef4444", "#14b8a6", "#f97316", "#06b6d4"]
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    position: "bottom",
                    labels: {
                        font: {
                            size: 14
                        },
                        padding: 20
                    }
                } 
            },
            animation: {
                animateScale: true,
                animateRotate: true
            }
        }
    });
}
</script>

</body>
</html>