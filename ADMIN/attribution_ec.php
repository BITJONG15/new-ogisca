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
if (file_exists("../images/enseignants/{$matricule}.jpg")) {
    $photo_url = "../images/enseignants/{$matricule}.jpg";
}

// AJOUT avec vérification de doublon
if (isset($_POST['ajouter'])) {
    $ID_EC = $_POST['ID_EC'];
    $id_enseignants = $_POST['id_enseignants'];
    
    // Vérifier si l'EC est déjà attribué
    $check_stmt = $conn->prepare("SELECT id FROM attribution_ec WHERE ID_EC = ?");
    $check_stmt->bind_param("s", $ID_EC);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Cet élément constitutif est déjà attribué à un enseignant.";
    } else {
        $stmt = $conn->prepare("INSERT INTO attribution_ec (ID_EC, id_enseignants) VALUES (?, ?)");
        $stmt->bind_param("si", $ID_EC, $id_enseignants);
        if ($stmt->execute()) {
            $success_message = "Attribution ajoutée avec succès !";
        } else {
            $error_message = "Erreur lors de l'ajout : " . $conn->error;
        }
    }
    header("Location: attribution_ec.php?" . ($error_message ? "error=" . urlencode($error_message) : "success=" . urlencode($success_message)));
    exit;
}

// MODIFIER avec vérification de doublon
if (isset($_POST['modifier'])) {
    $id = $_POST['id'];
    $ID_EC = $_POST['ID_EC'];
    $id_enseignants = $_POST['id_enseignants'];
    
    // Vérifier si l'EC est déjà attribué à un autre enseignant
    $check_stmt = $conn->prepare("SELECT id FROM attribution_ec WHERE ID_EC = ? AND id != ?");
    $check_stmt->bind_param("si", $ID_EC, $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Cet élément constitutif est déjà attribué à un autre enseignant.";
    } else {
        $stmt = $conn->prepare("UPDATE attribution_ec SET ID_EC=?, id_enseignants=? WHERE id=?");
        $stmt->bind_param("sii", $ID_EC, $id_enseignants, $id);
        if ($stmt->execute()) {
            $success_message = "Attribution modifiée avec succès !";
        } else {
            $error_message = "Erreur lors de la modification : " . $conn->error;
        }
    }
    header("Location: attribution_ec.php?" . ($error_message ? "error=" . urlencode($error_message) : "success=" . urlencode($success_message)));
    exit;
}

// SUPPRIMER
if (isset($_GET['supprimer'])) {
    $id = $_GET['supprimer'];
    $stmt = $conn->prepare("DELETE FROM attribution_ec WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success_message = "Attribution supprimée avec succès !";
    } else {
        $error_message = "Erreur lors de la suppression : " . $conn->error;
    }
    header("Location: attribution_ec.php?" . ($error_message ? "error=" . urlencode($error_message) : "success=" . urlencode($success_message)));
    exit;
}

// Afficher les messages
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}

// Pagination
$limit = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Requête principale avec pagination
$attributions_query = "
    SELECT 
        a.id, 
        e.ID_EC, 
        e.Nom_EC, 
        n.nom AS niveau, 
        d.nom AS division, 
        en.id as ens_id, 
        en.nom, 
        en.prenom 
    FROM attribution_ec a 
    JOIN element_constitutif e ON a.ID_EC = e.ID_EC 
    JOIN enseignants en ON a.id_enseignants = en.id
    JOIN niveau n ON e.id_niveau = n.id
    JOIN division d ON n.id_division = d.id
    ORDER BY a.id DESC
    LIMIT $limit OFFSET $offset
";
$attributions = $conn->query($attributions_query);

// Compter le nombre total d'attributions
$count_result = $conn->query("SELECT COUNT(*) as total FROM attribution_ec");
$total_attributions = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_attributions / $limit);

// Récupération divisions et niveaux (d'abord les divisions)
$division_result = $conn->query("SELECT * FROM division ORDER BY nom ASC");
$niveau_result = $conn->query("SELECT * FROM niveau ORDER BY nom ASC");

// Récupération enseignants avec pagination
$enseignants_limit = 1000; // Limite haute pour les enseignants
$enseignants_query = "SELECT id, nom, prenom FROM enseignants ORDER BY nom, prenom LIMIT $enseignants_limit";
$enseignants = $conn->query($enseignants_query);

// Récupération de tous les EC pour filtrage côté client
$ec_result = $conn->query("SELECT ID_EC, Nom_EC, id_niveau, division FROM element_constitutif");
$all_ec = [];
while ($ec = $ec_result->fetch_assoc()) {
    $all_ec[] = $ec;
}

// Créer un mapping division -> niveaux
$division_niveaux = [];
$niveau_result_temp = $conn->query("
    SELECT n.id, n.nom as niveau_nom, d.id as division_id, d.nom as division_nom 
    FROM niveau n 
    JOIN division d ON n.id_division = d.id 
    ORDER BY d.nom, n.nom
");
while ($row = $niveau_result_temp->fetch_assoc()) {
    $division_niveaux[$row['division_nom']][] = [
        'id' => $row['id'],
        'nom' => $row['niveau_nom']
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attribution des Cours | OGISCA</title>
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
            --dark: #1f2937;
            --light: #f9fafb;
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
        
        .chart-container {
            position: relative;
            height: 300px;
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
        
        .progress-bar {
            height: 6px;
            border-radius: 3px;
            background-color: #e5e7eb;
            overflow: hidden;
        }
        
        .progress-value {
            height: 100%;
            border-radius: 3px;
        }
        
        /* Animation pour les cartes de paliers */
        .palier-item {
            border-left: 4px solid var(--primary);
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        
        .palier-item:last-child {
            margin-bottom: 0;
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

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .table-row {
            transition: all 0.2s ease;
        }

        .table-row:hover {
            background: #f8fafc;
        }

        .action-btn {
            transition: all 0.2s ease;
            border-radius: 8px;
            padding: 0.5rem;
        }

        .action-btn:hover {
            transform: scale(1.1);
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

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            transition: all 0.2s ease;
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
    </style>
</head>
<body class="flex bg-gray-100 min-h-screen" x-data="{ mobileMenuOpen: false }">

<!-- Overlay for mobile menu -->
<div class="overlay" id="overlay" x-show="mobileMenuOpen" @click="mobileMenuOpen = false"></div>

<!-- Sidebar -->
<div class="sidebar w-64 h-screen text-white p-6 flex flex-col fixed lg:relative z-50"
     :class="{'open': mobileMenuOpen}"
     x-show="window.innerWidth >= 1024 || mobileMenuOpen">
    <div class="flex items-center gap-3 mb-8">
        <img src="logo simple sans fond.png" class="w-10 h-10 rounded" alt="logo" />
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

    <main class="p-6 space-y-6 overflow-auto">

        <!-- Messages d'alerte -->
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center animate-fadeIn">
                <i class="fas fa-check-circle mr-3 text-green-500"></i>
                <span><?= htmlspecialchars($success_message) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center animate-fadeIn">
                <i class="fas fa-exclamation-triangle mr-3 text-red-500"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-2">
            <h2 class="text-2xl font-bold text-gray-800">Attribution des Cours</h2>
            <div class="text-sm text-gray-500">
                <i class="far fa-calendar-alt mr-2"></i>
                <?php echo date('d/m/Y'); ?>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="card">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center mb-4 lg:mb-0">
                        <i class="fas fa-list text-white text-xl mr-3"></i>
                        <h3 class="text-xl font-semibold text-white">Liste des Attributions</h3>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="search-bar flex items-center px-4 py-2 w-full sm:w-80">
                            <i class="fas fa-search text-gray-400 mr-3"></i>
                            <input type="text" id="searchInput" placeholder="Rechercher..." 
                                   class="bg-transparent border-none focus:outline-none w-full">
                        </div>
                        <span class="bg-white/20 text-white px-3 py-2 rounded-full text-sm font-medium self-center">
                            <?= $total_attributions ?> attribution(s)
                        </span>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-gray-700">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left p-4 font-semibold text-gray-700">Cours (EC)</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Niveau</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Division</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Enseignant</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php while($row = $attributions->fetch_assoc()): ?>
                            <tr class="table-row border-b border-gray-100 last:border-b-0">
                                <td class="p-4 font-medium text-gray-900"><?= htmlspecialchars($row['Nom_EC']) ?></td>
                                <td class="p-4">
                                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium">
                                        <?= htmlspecialchars($row['niveau']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">
                                        <?= htmlspecialchars($row['division']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-purple-600 text-xs"></i>
                                        </div>
                                        <span class="font-medium"><?= htmlspecialchars($row['nom'] . ' ' . $row['prenom']) ?></span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex space-x-2">
                                        <button onclick='ouvrirModifier(<?= json_encode([
                                            "id" => $row['id'],
                                            "ID_EC" => $row['ID_EC'],
                                            "niveau" => $row['niveau'],
                                            "division" => $row['division'],
                                            "id_enseignants" => $row['ens_id']
                                        ]) ?>)'
                                                class="action-btn text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100"
                                                title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?supprimer=<?= $row['id'] ?>" 
                                           onclick="return confirm('Confirmer la suppression ?')"
                                           class="action-btn text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100"
                                           title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center space-x-2 mt-6 pt-6 border-t border-gray-200">
                    <!-- Previous Button -->
                    <a href="?page=<?= $page - 1 ?>" 
                       class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>

                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>" 
                           class="pagination-btn <?= $i == $page ? 'pagination-active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next Button -->
                    <a href="?page=<?= $page + 1 ?>" 
                       class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Button to open form -->
        <div class="flex justify-end mt-6">
            <button onclick="ouvrirPopup('popupForm')" 
                    class="btn-primary px-6 py-3 text-lg">
                <i class="fas fa-plus-circle"></i>
                Attribuer un Cours
            </button>
        </div>

    </main>

    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<!-- POPUP FORMULAIRE -->
<div id="popupForm" class="popup" role="dialog" aria-modal="true" aria-labelledby="formTitle">
    <form id="formAttribution" action="attribution_ec.php" method="POST" class="popup-content space-y-6">
        <button type="button" class="close-btn" onclick="fermerPopup('popupForm')">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="flex items-center space-x-3 mb-2">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-book text-blue-600 text-xl"></i>
            </div>
            <div>
                <h3 id="formTitle" class="text-2xl font-bold text-gray-800">Attribuer un Cours</h3>
                <p class="text-gray-600">Remplissez les informations pour attribuer un EC</p>
            </div>
        </div>

        <input type="hidden" name="id" id="form_id" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Division d'abord -->
            <div>
                <label class="block mb-3 font-semibold text-gray-700" for="division_select">
                    <i class="fas fa-users mr-2 text-blue-500"></i>
                    Division
                </label>
                <select id="division_select" class="form-input" required>
                    <option value="">-- Sélectionner une Division --</option>
                    <?php
                    $division_result->data_seek(0);
                    while($div = $division_result->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($div['nom']) ?>"><?= htmlspecialchars($div['nom']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Niveau ensuite (dépend de la division) -->
            <div>
                <label class="block mb-3 font-semibold text-gray-700" for="niveau_select">
                    <i class="fas fa-graduation-cap mr-2 text-blue-500"></i>
                    Niveau
                </label>
                <select id="niveau_select" class="form-input" required disabled>
                    <option value="">-- Sélectionner d'abord une division --</option>
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block mb-3 font-semibold text-gray-700" for="ID_EC">
                    <i class="fas fa-book-open mr-2 text-blue-500"></i>
                    Cours (EC)
                </label>
                <select name="ID_EC" id="ID_EC" class="form-input" required disabled>
                    <option value="">-- Sélectionner d'abord un niveau --</option>
                </select>
                <p class="text-sm text-gray-500 mt-2">Un EC ne peut être attribué qu'à un seul enseignant</p>
            </div>

            <div class="lg:col-span-2">
                <label class="block mb-3 font-semibold text-gray-700" for="id_enseignants">
                    <i class="fas fa-user-tie mr-2 text-blue-500"></i>
                    Enseignant
                </label>
                <select name="id_enseignants" id="id_enseignants" class="form-input" required>
                    <option value="">-- Sélectionner un Enseignant --</option>
                    <?php foreach ($enseignants as $ens): ?>
                        <option value="<?= $ens['id'] ?>"><?= htmlspecialchars($ens['nom'] . ' ' . $ens['prenom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
            <button type="button" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium"
                    onclick="fermerPopup('popupForm')">
                Annuler
            </button>
            <button type="submit" id="submitBtn" name="ajouter" 
                    class="btn-primary px-6 py-3 text-lg">
                <i class="fas fa-plus-circle"></i>
                <span id="submitText">Ajouter</span>
            </button>
        </div>
    </form>
</div>

<script>
    const popupForm = document.getElementById('popupForm');
    const divisionSelect = document.getElementById('division_select');
    const niveauSelect = document.getElementById('niveau_select');
    const ecSelect = document.getElementById('ID_EC');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');

    // Données côté serveur
    const allEC = <?= json_encode($all_ec); ?>;
    const divisionNiveaux = <?= json_encode($division_niveaux); ?>;

    // Ouvrir popup formulaire
    function ouvrirPopup(id) {
        document.getElementById(id).classList.add('open');
        if(id === 'popupForm') {
            resetForm();
        }
    }

    // Fermer popup formulaire
    function fermerPopup(id) {
        document.getElementById(id).classList.remove('open');
    }

    // Charger les niveaux selon la division sélectionnée
    function chargerNiveaux() {
        const division = divisionSelect.value;
        niveauSelect.innerHTML = '<option value="">-- Sélectionner un Niveau --</option>';
        niveauSelect.disabled = !division;
        ecSelect.disabled = true;
        ecSelect.innerHTML = '<option value="">-- Sélectionner d\'abord un niveau --</option>';

        if (division && divisionNiveaux[division]) {
            divisionNiveaux[division].forEach(niveau => {
                const option = document.createElement('option');
                option.value = niveau.nom;
                option.textContent = niveau.nom;
                option.setAttribute('data-id', niveau.id);
                niveauSelect.appendChild(option);
            });
        }
    }

    // Charger EC selon niveau sélectionné
    function chargerEC() {
        const niveau = niveauSelect.value;
        const niveauId = niveauSelect.options[niveauSelect.selectedIndex]?.getAttribute('data-id');
        ecSelect.innerHTML = '<option value="">-- Sélectionner un EC --</option>';
        ecSelect.disabled = !niveau;

        if (niveau && niveauId) {
            const filtered = allEC.filter(ec => ec.id_niveau == niveauId);
            filtered.forEach(ec => {
                const option = document.createElement('option');
                option.value = ec.ID_EC;
                option.textContent = ec.Nom_EC;
                ecSelect.appendChild(option);
            });
            
            if (filtered.length === 0) {
                ecSelect.innerHTML = '<option value="">Aucun EC trouvé pour ce niveau</option>';
            }
        }
    }

    // Événements
    divisionSelect.addEventListener('change', chargerNiveaux);
    niveauSelect.addEventListener('change', chargerEC);

    // Réinitialiser formulaire
    function resetForm() {
        document.getElementById('formAttribution').reset();
        niveauSelect.innerHTML = '<option value="">-- Sélectionner d\'abord une division --</option>';
        niveauSelect.disabled = true;
        ecSelect.innerHTML = '<option value="">-- Sélectionner d\'abord un niveau --</option>';
        ecSelect.disabled = true;
        submitBtn.name = 'ajouter';
        submitText.textContent = 'Ajouter';
        document.getElementById('form_id').value = '';
    }

    // Ouvrir formulaire en mode modification
    function ouvrirModifier(data) {
        ouvrirPopup('popupForm');

        document.getElementById('form_id').value = data.id;
        divisionSelect.value = data.division;
        
        // Charger les niveaux pour cette division
        chargerNiveaux();
        
        setTimeout(() => {
            niveauSelect.value = data.niveau;
            // Charger les EC pour ce niveau
            chargerEC();
            setTimeout(() => {
                ecSelect.value = data.ID_EC;
            }, 100);
        }, 100);

        document.getElementById('id_enseignants').value = data.id_enseignants;
        submitBtn.name = 'modifier';
        submitText.textContent = 'Modifier';
    }

    // Recherche Dynamique
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                row.style.display = rowText.indexOf(filter) > -1 ? '' : 'none';
            });
        });
    }

    // Fermer popup avec ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fermerPopup('popupForm');
        }
    });
</script>

</body>
</html>