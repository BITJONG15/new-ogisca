<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Vérification de la session
if (!isset($_SESSION['matricule'])) {
    header("Location: login.php");
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

// Gestion des actions (rediriger / rejeter)
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    $nouveauStatut = null;
    if ($action === "rediriger") $nouveauStatut = "traitée";
    if ($action === "rejeter") $nouveauStatut = "refusée";

    if ($nouveauStatut) {
        $stmt = $conn->prepare("UPDATE requetes SET statut=? WHERE id=?");
        $stmt->bind_param("si", $nouveauStatut, $id);
        $stmt->execute();
    }

    header("Location: admin_requetes.php");
    exit;
}

// Gestion recherche et pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5; // nombre de lignes par page
$offset = ($page - 1) * $limit;

// Préparer la requête SQL
$sqlBase = "FROM requetes r
            JOIN etudiants e ON r.etudiant_id = e.id
            WHERE (e.nom LIKE ? OR e.prenom LIKE ? 
                  OR r.professeur_nom LIKE ? 
                  OR r.professeur_prenom LIKE ? 
                  OR r.motif LIKE ? 
                  OR r.element_constitutif LIKE ?)";

$param = "%$search%";
$stmtCount = $conn->prepare("SELECT COUNT(*) $sqlBase");
$stmtCount->bind_param("ssssss", $param, $param, $param, $param, $param, $param);
$stmtCount->execute();
$stmtCount->bind_result($totalRows);
$stmtCount->fetch();
$stmtCount->close();

$totalPages = ceil($totalRows / $limit);

// Récupérer les données avec pagination
$stmt = $conn->prepare("SELECT r.id, e.nom AS etu_nom, e.prenom AS etu_prenom,
                               r.professeur_nom, r.professeur_prenom, 
                               r.motif, r.statut, r.piece_jointe, r.element_constitutif
                        $sqlBase
                        ORDER BY r.date_envoi DESC
                        LIMIT ? OFFSET ?");
$stmt->bind_param("ssssssii", $param, $param, $param, $param, $param, $param, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Récupérer les statistiques pour les cartes
$stmt_stats = $conn->prepare("SELECT COUNT(*) FROM requetes WHERE statut='en attente'");
$stmt_stats->execute();
$stmt_stats->bind_result($en_attente);
$stmt_stats->fetch();
$stmt_stats->close();

$stmt_stats = $conn->prepare("SELECT COUNT(*) FROM requetes WHERE statut='traitée'");
$stmt_stats->execute();
$stmt_stats->bind_result($traitees);
$stmt_stats->fetch();
$stmt_stats->close();

$stmt_stats = $conn->prepare("SELECT COUNT(*) FROM requetes WHERE statut='refusée'");
$stmt_stats->execute();
$stmt_stats->bind_result($refusees);
$stmt_stats->fetch();
$stmt_stats->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Requêtes</title>
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

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-en-attente {
            background: #fef3c7;
            color: #92400e;
        }

        .status-traitee {
            background: #d1fae5;
            color: #065f46;
        }

        .status-refusee {
            background: #fee2e2;
            color: #991b1b;
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

        .stat-card.danger {
            border-left-color: var(--danger);
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

        /* Popup */
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
            <li class="nav-item">
                <a href="gestion_corrections.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-calendar-alt w-6 text-center mr-3"></i>
                    <span>PROGRAMMER DES CORRECTIONS</span>
                </a>
            </li>
            <li class="nav-item active">
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
                        <h1 class="text-3xl font-bold mb-2">Gestion des Requêtes</h1>
                        <p class="text-blue-100 opacity-90">Gérez les requêtes des étudiants</p>
                    </div>
                </div>
            </div>

            <!-- Cartes de statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Requêtes</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $totalRows; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-list text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card secondary">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">En Attente</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $en_attente; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card accent">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Traitées</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $traitees; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-amber-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card danger">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Refusées</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $refusees; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Recherche -->
            <div class="card p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <div class="flex-1">
                        <form method="get" class="flex">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                    placeholder="Rechercher une requête..." 
                                    class="form-input pl-10">
                            </div>
                            <button type="submit" class="btn btn-primary ml-2">
                                <i class="fas fa-search"></i> Rechercher
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tableau des requêtes -->
            <div class="data-table">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4 text-gray-800 flex items-center gap-2">
                        <i class="fas fa-list-alt text-blue-600"></i> Liste des requêtes
                        <span class="text-sm font-normal text-gray-500 ml-2">(<?php echo $totalRows; ?> requêtes au total)</span>
                    </h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Étudiant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enseignant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Motif</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Élément Constitutif</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pièce Jointe</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr class="table-row-hover">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['etu_nom'] . " " . $row['etu_prenom']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($row['professeur_nom'] . " " . $row['professeur_prenom']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($row['motif']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= !empty($row['element_constitutif']) ? htmlspecialchars($row['element_constitutif']) : '-' ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                    $status_class = '';
                                                    if ($row['statut'] == 'en attente') $status_class = 'status-en-attente';
                                                    elseif ($row['statut'] == 'traitée') $status_class = 'status-traitee';
                                                    elseif ($row['statut'] == 'refusée') $status_class = 'status-refusee';
                                                ?>
                                                <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($row['statut']) ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if (!empty($row['piece_jointe'])): ?>
                                                    <a href="../Etudiant/uploads/<?= htmlspecialchars($row['piece_jointe']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800 flex items-center">
                                                        <i class="fas fa-file-pdf mr-1"></i> Voir PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if ($row['statut'] == "en attente"): ?>
                                                    <div class="flex space-x-2">
                                                        <a href="?action=rediriger&id=<?= $row['id'] ?>" class="btn btn-success text-white">
                                                            <i class="fas fa-forward mr-1"></i> Rediriger
                                                        </a>
                                                        <a href="?action=rejeter&id=<?= $row['id'] ?>" class="btn btn-danger text-white">
                                                            <i class="fas fa-times mr-1"></i> Rejeter
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-500 italic">Aucune action</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                            <div class="flex flex-col items-center justify-center py-8">
                                                <i class="fas fa-inbox text-gray-300 text-4xl mb-2"></i>
                                                <p>Aucune requête trouvée.</p>
                                                <?php if (!empty($search)): ?>
                                                    <p class="text-sm mt-2">Essayez de modifier vos critères de recherche.</p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="page-link">
                                    <i class="fas fa-chevron-left mr-1"></i> Précédent
                                </a>
                            <?php endif; ?>

                            <span class="page-info">
                                Page <?= $page ?> sur <?= $totalPages ?>
                            </span>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="page-link">
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

// Confirmation pour les actions
document.addEventListener('DOMContentLoaded', function() {
    const actionLinks = document.querySelectorAll('a[href*="action="]');
    actionLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const action = this.textContent.trim();
            if (!confirm(`Êtes-vous sûr de vouloir ${action.toLowerCase()} cette requête?`)) {
                e.preventDefault();
            }
        });
    });
});

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