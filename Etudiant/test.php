<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) die("Erreur de connexion : " . $conn->connect_error);

// Vérification session
if (!isset($_SESSION['matricule'])) {
    header("Location: ../login.php");
    exit;
}

// Récupérer infos étudiant
$matricule = $_SESSION['matricule'];
$stmt = $conn->prepare("SELECT e.id, e.nom, e.prenom, e.niveau_id, e.division_id, 
                               n.nom AS niveau_nom, d.nom AS division_nom
                        FROM etudiants e
                        JOIN niveau n ON e.niveau_id = n.id
                        JOIN division d ON e.division_id = d.id
                        WHERE e.matricule=?");
$stmt->bind_param("s", $matricule);
$stmt->execute();
$etudiant = $stmt->get_result()->fetch_assoc();
if (!$etudiant) die("Étudiant introuvable.");

$etudiant_id = $etudiant['id'];
$niveau_id = $etudiant['niveau_id'];
$division_id = $etudiant['division_id'];
$niveau_nom = $etudiant['niveau_nom'];
$division_nom = $etudiant['division_nom'];
$photo_url = 'https://www.svgrepo.com/show/382106/user-circle.svg';

// Récupérer EC pour le niveau et division de l'étudiant
$ec_stmt = $conn->prepare("SELECT * FROM element_constitutif WHERE id_niveau=? AND division=?");
$ec_stmt->bind_param("is", $niveau_id, $division_nom);
$ec_stmt->execute();
$ec_result = $ec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Préparer les données des professeurs pour chaque EC
$ec_professeurs = [];
foreach($ec_result as $ec) {
    $prof_stmt = $conn->prepare("SELECT ens.nom, ens.prenom 
                                 FROM attribution_ec a
                                 JOIN enseignants ens ON a.id_enseignants = ens.id
                                 WHERE a.ID_EC = ?");
    $prof_stmt->bind_param("s", $ec['ID_EC']);
    $prof_stmt->execute();
    $prof = $prof_stmt->get_result()->fetch_assoc();
    $ec_professeurs[$ec['ID_EC']] = $prof ? $prof : ['nom' => 'Non attribué', 'prenom' => ''];
}

// Traitement du formulaire
$success_message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $motif = $_POST['motif'];
    $element_constitutif = $_POST['element_constitutif'];
    $contenu = $_POST['contenu'];
    $prof_nom = $_POST['prof_nom'];
    $prof_prenom = $_POST['prof_prenom'];

    // Upload PDF
    $piece_jointe = null;
    if (!empty($_FILES['piece_jointe']['name'])) {
        $uploadDir = __DIR__ . "/uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . "_" . basename($_FILES["piece_jointe"]["name"]);
        $targetPath = $uploadDir . $fileName;

        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES["piece_jointe"]["tmp_name"]);
        finfo_close($finfo);

        if ($fileExtension === "pdf" && $mimeType === "application/pdf") {
            move_uploaded_file($_FILES["piece_jointe"]["tmp_name"], $targetPath);
            $piece_jointe = $fileName;
        }
    }

    // Insertion dans requetes
    $insert = $conn->prepare("INSERT INTO requetes 
        (etudiant_id, motif, element_constitutif, professeur_nom, professeur_prenom, niveau_nom, division_nom, contenu, piece_jointe, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'en attente')");
    $insert->bind_param("issssssss", $etudiant_id, $motif, $element_constitutif, $prof_nom, $prof_prenom, $niveau_nom, $division_nom, $contenu, $piece_jointe);
    
    if ($insert->execute()) {
        $success_message = "Requête envoyée avec succès !";
    }
}

// Récupérer les requêtes de l'étudiant
$requetes_stmt = $conn->prepare("SELECT * FROM requetes WHERE etudiant_id=? ORDER BY date_envoi DESC");
$requetes_stmt->bind_param("i", $etudiant_id);
$requetes_stmt->execute();
$requetes = $requetes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Mes Requêtes | OGISCA</title>
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

.sidebar {
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
    box-shadow: 0 0 25px rgba(79, 70, 229, 0.15);
    transition: all 0.3s ease;
    transform: translateX(-100%);
}

.sidebar.open {
    transform: translateX(0);
}

@media (min-width: 1024px) {
    .sidebar {
        transform: translateX(0);
        position: relative !important;
    }
}

.nav-item {
    border-radius: 12px;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
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
    background: white;
    color: #374151;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
    border-bottom: 1px solid #e5e7eb;
    z-index: 30;
}

.card {
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    background: white;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
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
    width: 280px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    padding: 1rem;
    margin-top: 0.5rem;
    z-index: 100;
    transition: all 0.3s ease;
}

.main-content {
    background: linear-gradient(135deg, #f5f7fd 0%, #f0f4ff 100%);
    min-height: 100vh;
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

.scrollable-content {
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    padding-right: 0.5rem;
}

.form-input {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.3s ease;
    width: 100%;
}

.form-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    outline: none;
}

.btn-primary {
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
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
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-processed {
    background: #dbeafe;
    color: #1e40af;
}

.status-validated {
    background: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.file-input {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.file-input:hover {
    border-color: var(--primary);
    background-color: #f8faff;
}

.table-hover tbody tr {
    transition: all 0.2s ease;
}

.table-hover tbody tr:hover {
    background-color: #f9fafb;
}

.backdrop {
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.backdrop.active {
    opacity: 1;
    visibility: visible;
}

.touch-button {
    min-height: 44px;
    min-width: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.scroll-mobile {
    -webkit-overflow-scrolling: touch;
}

@media (max-width: 640px) {
    .responsive-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .form-grid-mobile {
        grid-template-columns: 1fr;
    }
    
    .table-responsive-mobile {
        font-size: 0.875rem;
    }
    
    .cards-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

@media (min-width: 641px) and (max-width: 768px) {
    .responsive-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }
    
    .form-grid-tablet {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .cards-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    .responsive-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
    
    .form-grid-desktop {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .cards-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
}

@media (min-width: 1025px) {
    .responsive-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
    }
    
    .form-grid-desktop {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .cards-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
    }
}

@media (max-width: 640px) {
    .responsive-text {
        font-size: 0.875rem;
        line-height: 1.25rem;
    }
    
    .responsive-heading {
        font-size: 1.5rem;
        line-height: 2rem;
    }
    
    .table-cell {
        padding: 0.5rem 0.25rem;
    }
}

.search-bar {
    display: flex;
    align-items: center;
    background: #f8f9fa;
    border-radius: 50px;
    padding: 8px 15px;
    width: 300px;
    border: 1px solid #e9ecef;
}

.search-bar input {
    background: transparent;
    border: none;
    color: #374151;
    width: 100%;
    margin-left: 10px;
    outline: none;
}

.search-bar input::placeholder {
    color: #6c757d;
}

.menu-toggle {
    background: none;
    border: none;
    color: #374151;
    font-size: 1.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.menu-toggle:hover {
    background: #f8f9fa;
}

.card-item {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.card-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.card-content {
    margin-bottom: 1rem;
    color: #495057;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease forwards;
}

.burger-line {
    transition: all 0.3s ease;
}

.burger-active .burger-line:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
}

.burger-active .burger-line:nth-child(2) {
    opacity: 0;
}

.burger-active .burger-line:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -6px);
}

.mobile-menu-button {
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 50;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    padding: 0.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

@media (min-width: 1024px) {
    .mobile-menu-button {
        display: none;
    }
}

.sidebar-mobile {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 40;
}

@media (min-width: 1024px) {
    .sidebar-mobile {
        position: relative;
        height: auto;
    }
}
</style>
</head>
<body class="flex bg-gray-100 min-h-screen" x-data="app()">

<!-- Overlay for mobile menu -->
<div class="backdrop fixed inset-0 bg-black bg-opacity-50 z-40" 
     x-show="mobileMenuOpen && window.innerWidth < 1024" 
     @click="mobileMenuOpen = false"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
</div>

<!-- Mobile Menu Button -->
<button class="mobile-menu-button lg:hidden touch-button" 
        @click="mobileMenuOpen = !mobileMenuOpen"
        :class="{ 'burger-active': mobileMenuOpen }">
    <div class="w-6 h-6 flex flex-col justify-between">
        <div class="burger-line w-full h-0.5 bg-white rounded"></div>
        <div class="burger-line w-full h-0.5 bg-white rounded"></div>
        <div class="burger-line w-full h-0.5 bg-white rounded"></div>
    </div>
</button>

<!-- Sidebar Unique -->
<div class="sidebar sidebar-mobile w-80 text-white flex flex-col shadow-2xl z-40 lg:relative lg:z-auto"
     :class="{ 'open': mobileMenuOpen || window.innerWidth >= 1024 }"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="-translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="-translate-x-full">
    
    <div class="flex items-center justify-between p-6 border-b border-primary-light">
        <div class="flex items-center">
            <img src="../admin/logo simple sans fond.png" class="w-8 h-8 mr-3 rounded" alt="logo" />
            <h1 class="text-xl font-bold">OGISCA - INJS</h1>
        </div>
        <button class="touch-button text-white lg:hidden" @click="mobileMenuOpen = false">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    
    <nav class="flex flex-col flex-1 p-4 space-y-2 overflow-y-auto scroll-mobile">
        <a href="AccueilEtudiant.php" 
           class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button animate-fadeIn"
           @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }"
           style="animation-delay: 0.1s">
            <i class="fas fa-home w-6 mr-3 text-center"></i>
            <span class="font-medium">Accueil</span>
        </a>
        
        <a href="MesCours.php" 
           class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button animate-fadeIn"
           @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }"
           style="animation-delay: 0.2s">
            <i class="fas fa-book w-6 mr-3 text-center"></i>
            <span class="font-medium">Mes Cours</span>
        </a>
        
        <a href="test.php" 
           class="flex items-center px-4 py-4 rounded-lg bg-primary-light text-white transition-colors touch-button animate-fadeIn"
           @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }"
           style="animation-delay: 0.3s">
            <i class="fas fa-tasks w-6 mr-3 text-center"></i>
            <span class="font-medium">Mes Requêtes</span>
        </a>
        
        <a href="Profil.php" 
           class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button animate-fadeIn"
           @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }"
           style="animation-delay: 0.4s">
            <i class="fas fa-user w-6 mr-3 text-center"></i>
            <span class="font-medium">Mon Profil</span>
        </a>
        
        <div class="mt-auto pt-4 border-t border-primary-light">
            <a href="../logout.php" 
               class="flex items-center px-4 py-4 rounded-lg hover:bg-red-600 transition-colors touch-button animate-fadeIn"
               @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }"
               style="animation-delay: 0.5s">
                <i class="fas fa-sign-out-alt w-6 mr-3 text-center"></i>
                <span class="font-medium">Déconnexion</span>
            </a>
        </div>
    </nav>
</div>

<div class="flex-1 flex flex-col min-h-screen" :class="{ 'lg:ml-0': !mobileMenuOpen && window.innerWidth >= 1024 }">
    <!-- Topbar - Version Blanche -->
    <header class="topbar">
        <div class="flex items-center space-x-4">
            <button class="menu-toggle lg:hidden" @click="mobileMenuOpen = !mobileMenuOpen">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search text-gray-400"></i>
                <input type="text" placeholder="Rechercher...">
            </div>
        </div>
        
        <div class="user-profile">
            <div class="flex items-center space-x-3 cursor-pointer touch-button" @click="userDropdownOpen = !userDropdownOpen">
                <div class="text-right hidden md:block">
                    <div class="font-semibold responsive-text text-gray-800">
                        <?= htmlspecialchars($etudiant['prenom']) ?> <?= htmlspecialchars($etudiant['nom']) ?>
                    </div>
                    <div class="text-gray-500 text-sm">
                        Matricule : <?= htmlspecialchars($matricule) ?>
                    </div>
                </div>
                <div class="relative">
                    <img src="<?= $photo_url ?>" alt="Profil" class="w-10 h-10 lg:w-12 lg:h-12 rounded-full border-2 border-white shadow-md transition-transform hover:scale-105" />
                    <span class="absolute bottom-0 right-0 w-2 h-2 lg:w-3 lg:h-3 bg-green-500 rounded-full border-2 border-white"></span>
                </div>
            </div>
            
            <div class="user-dropdown" x-show="userDropdownOpen" @click.outside="userDropdownOpen = false" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95">
                <div class="flex items-center space-x-3 pb-3 border-b border-gray-100">
                    <img src="<?= $photo_url ?>" alt="Profil" class="w-12 h-12 lg:w-14 lg:h-14 rounded-full border-2 border-gray-200" />
                    <div>
                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($etudiant['prenom']) ?> <?= htmlspecialchars($etudiant['nom']) ?></div>
                        <div class="text-sm text-gray-500"><?= htmlspecialchars($matricule) ?></div>
                    </div>
                </div>
                <div class="py-3 space-y-2">
                    <a href="Profil.php" class="flex items-center py-2 px-3 text-gray-700 hover:bg-blue-50 rounded-lg touch-button transition-colors">
                        <i class="fas fa-user-circle mr-3 text-blue-500"></i>
                        <span>Mon profil</span>
                    </a>
                    <a href="#" class="flex items-center py-2 px-3 text-gray-700 hover:bg-blue-50 rounded-lg touch-button transition-colors">
                        <i class="fas fa-cog mr-3 text-blue-500"></i>
                        <span>Paramètres</span>
                    </a>
                </div>
                <div class="pt-3 border-t border-gray-100">
                    <a href="../logout.php" class="flex items-center py-2 px-3 text-red-600 hover:bg-red-50 rounded-lg touch-button transition-colors">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="scrollable-content p-4 lg:p-8 scroll-mobile">
            <!-- En-tête de page -->
            <div class="page-header">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold mb-2 responsive-heading">Mes Requêtes</h1>
                        <p class="text-blue-100 opacity-90 responsive-text">Gérez vos demandes et consultations de notes</p>
                    </div>
                    <div class="text-white text-sm mt-4 md:mt-0">
                        <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full transition-colors hover:bg-opacity-30">
                            <?= count($requetes) ?> requête(s)
                        </span>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center animate-fadeIn">
                    <i class="fas fa-check-circle mr-3"></i>
                    <span><?= htmlspecialchars($success_message) ?></span>
                </div>
            <?php endif; ?>

            <!-- Formulaire de nouvelle requête -->
            <div class="card p-4 lg:p-8 mb-6 lg:mb-8">
                <div class="flex items-center mb-6">
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4 transition-transform hover:scale-105">
                        <i class="fas fa-plus-circle text-blue-600 text-lg lg:text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl lg:text-2xl font-semibold text-gray-800 responsive-heading">Nouvelle Requête</h2>
                        <p class="text-gray-600 responsive-text">Remplissez le formulaire pour soumettre une nouvelle demande</p>
                    </div>
                </div>

                <form method="post" enctype="multipart/form-data" class="space-y-4 lg:space-y-6">
                    <!-- Motif -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-flag mr-2 text-blue-500"></i>
                            Motif de la requête
                        </label>
                        <select name="motif" class="form-input" required>
                            <option value="">-- Sélectionner un motif --</option>
                            <option value="Erreur de saisie">Erreur de saisie</option>
                            <option value="Absence de note">Absence de note</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>

                    <!-- Informations académiques -->
                    <div class="grid form-grid-mobile form-grid-tablet form-grid-desktop gap-4 lg:gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-users mr-2 text-blue-500"></i>
                                Division
                            </label>
                            <input type="text" value="<?= htmlspecialchars($division_nom) ?>" class="form-input bg-gray-50" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-graduation-cap mr-2 text-blue-500"></i>
                                Niveau
                            </label>
                            <input type="text" value="<?= htmlspecialchars($niveau_nom) ?>" class="form-input bg-gray-50" disabled>
                        </div>
                        <div class="md:col-span-2 lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-book mr-2 text-blue-500"></i>
                                Élément Constitutif
                            </label>
                            <select name="element_constitutif" class="form-input" required x-on:change="updateProfessor($event.target.value)">
                                <option value="">-- Sélectionner EC --</option>
                                <?php foreach($ec_result as $ec): ?>
                                    <option value="<?= htmlspecialchars($ec['ID_EC']) ?>"><?= htmlspecialchars($ec['Nom_EC']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Informations professeur -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-tie mr-2 text-blue-500"></i>
                                Nom Professeur
                            </label>
                            <input type="text" name="prof_nom" class="form-input bg-gray-50" value="" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-tie mr-2 text-blue-500"></i>
                                Prénom Professeur
                            </label>
                            <input type="text" name="prof_prenom" class="form-input bg-gray-50" value="" readonly>
                        </div>
                    </div>

                    <!-- Contenu -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-2 text-blue-500"></i>
                            Description détaillée
                        </label>
                        <textarea name="contenu" class="form-input" rows="4" placeholder="Décrivez votre demande en détail..."></textarea>
                    </div>

                    <!-- Pièce jointe -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-file-pdf mr-2 text-blue-500"></i>
                            Pièce jointe (PDF uniquement)
                        </label>
                        <div class="file-input">
                            <i class="fas fa-cloud-upload-alt text-2xl lg:text-3xl text-gray-400 mb-3 transition-transform hover:scale-110"></i>
                            <p class="text-gray-600 mb-2 responsive-text">Glissez-déposez votre fichier PDF ou cliquez pour parcourir</p>
                            <p class="text-sm text-gray-500 mb-4">Taille maximale : 5MB - Format accepté : PDF</p>
                            <input type="file" name="piece_jointe" accept="application/pdf" 
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors">
                        </div>
                    </div>

                    <!-- Bouton d'envoi -->
                    <div class="flex justify-end pt-4">
                        <button type="submit" class="btn-primary px-6 lg:px-8 py-3 text-base lg:text-lg w-full lg:w-auto justify-center">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Envoyer la requête
                        </button>
                    </div>
                </form>
            </div>

            <!-- Historique des Requêtes -->
            <div class="card overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 lg:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-history text-white text-lg lg:text-xl mr-3"></i>
                            <h2 class="text-lg lg:text-xl font-semibold text-white responsive-heading">Historique des Requêtes</h2>
                        </div>
                        <span class="bg-white bg-opacity-20 text-white px-3 py-1 rounded-full text-sm font-medium transition-colors hover:bg-opacity-30">
                            <?= count($requetes) ?> requête(s)
                        </span>
                    </div>
                </div>

                <div class="p-4 lg:p-6">
                    <?php if($requetes): ?>
                        <!-- Desktop Table View -->
                        <div class="table-responsive-mobile overflow-x-auto hidden md:block">
                            <table class="w-full text-sm table-hover">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200">
                                        <th class="px-3 lg:px-4 py-2 lg:py-3 text-left text-sm font-semibold text-gray-700 table-cell">Motif</th>
                                        <th class="px-3 lg:px-4 py-2 lg:py-3 text-left text-sm font-semibold text-gray-700 table-cell">Statut</th>
                                        <th class="px-3 lg:px-4 py-2 lg:py-3 text-left text-sm font-semibold text-gray-700 table-cell hidden md:table-cell">EC</th>
                                        <th class="px-3 lg:px-4 py-2 lg:py-3 text-left text-sm font-semibold text-gray-700 table-cell hidden lg:table-cell">Professeur</th>
                                        <th class="px-3 lg:px-4 py-2 lg:py-3 text-left text-sm font-semibold text-gray-700 table-cell">Fichier</th>
                                        <th class="px-3 lg:px-4 py-2 lg:py-3 text-left text-sm font-semibold text-gray-700 table-cell">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($requetes as $r): ?>
                                    <tr class="table-hover">
                                        <td class="px-3 lg:px-4 py-2 lg:py-3 table-cell">
                                            <div class="flex items-center">
                                                <i class="fas fa-flag text-blue-500 mr-2 text-sm"></i>
                                                <span class="font-medium text-gray-900 responsive-text"><?= htmlspecialchars($r['motif']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-3 lg:px-4 py-2 lg:py-3 table-cell">
                                            <?php
                                            $status_class = match($r['statut']) {
                                                'en attente' => 'status-pending',
                                                'traitée' => 'status-processed',
                                                'validée' => 'status-validated',
                                                default => 'status-rejected'
                                            };
                                            $status_icon = match($r['statut']) {
                                                'en attente' => 'fa-clock',
                                                'traitée' => 'fa-cog',
                                                'validée' => 'fa-check',
                                                default => 'fa-times'
                                            };
                                            ?>
                                            <span class="status-badge <?= $status_class ?> flex items-center w-fit transition-colors hover:opacity-90">
                                                <i class="fas <?= $status_icon ?> mr-1 text-xs"></i>
                                                <span class="responsive-text"><?= htmlspecialchars($r['statut']) ?></span>
                                            </span>
                                        </td>
                                        <td class="px-3 lg:px-4 py-2 lg:py-3 text-gray-900 table-cell hidden md:table-cell">
                                            <div class="flex items-center">
                                                <i class="fas fa-book text-gray-400 mr-2 text-sm"></i>
                                                <span class="responsive-text"><?= htmlspecialchars($r['element_constitutif']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-3 lg:px-4 py-2 lg:py-3 text-gray-900 table-cell hidden lg:table-cell">
                                            <div class="flex items-center">
                                                <i class="fas fa-user-tie text-gray-400 mr-2 text-sm"></i>
                                                <span class="responsive-text"><?= htmlspecialchars($r['professeur_nom'] . " " . $r['professeur_prenom']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-3 lg:px-4 py-2 lg:py-3 table-cell">
                                            <?php if($r['piece_jointe']): ?>
                                                <a href="uploads/<?= htmlspecialchars($r['piece_jointe']) ?>" target="_blank" 
                                                   class="inline-flex items-center text-blue-600 hover:text-blue-800 transition-colors font-medium responsive-text">
                                                    <i class="fas fa-file-pdf mr-2"></i>
                                                    PDF
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400 flex items-center responsive-text">
                                                    <i class="fas fa-times mr-2"></i>
                                                    Aucun
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 lg:px-4 py-2 lg:py-3 text-gray-500 table-cell">
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar mr-2 text-gray-400 text-sm"></i>
                                                <span class="responsive-text"><?= date('d/m/Y', strtotime($r['date_envoi'])) ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Mobile Cards View -->
                        <div class="cards-grid md:hidden">
                            <?php foreach($requetes as $r): ?>
                            <div class="card-item">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title"><?= htmlspecialchars($r['motif']) ?></div>
                                        <div class="card-subject text-blue-600 font-medium"><?= htmlspecialchars($r['element_constitutif']) ?></div>
                                    </div>
                                    <?php
                                    $status_class = match($r['statut']) {
                                        'en attente' => 'status-pending',
                                        'traitée' => 'status-processed',
                                        'validée' => 'status-validated',
                                        default => 'status-rejected'
                                    };
                                    $status_icon = match($r['statut']) {
                                        'en attente' => 'fa-clock',
                                        'traitée' => 'fa-cog',
                                        'validée' => 'fa-check',
                                        default => 'fa-times'
                                    };
                                    ?>
                                    <span class="status-badge <?= $status_class ?> flex items-center">
                                        <i class="fas <?= $status_icon ?> mr-1 text-xs"></i>
                                        <span class="responsive-text"><?= htmlspecialchars($r['statut']) ?></span>
                                    </span>
                                </div>
                                <div class="card-content">
                                    <p class="text-gray-600 mb-2"><i class="fas fa-user-tie text-gray-400 mr-2"></i><?= htmlspecialchars($r['professeur_nom'] . " " . $r['professeur_prenom']) ?></p>
                                    <p class="text-gray-500 text-sm"><i class="fas fa-calendar text-gray-400 mr-2"></i><?= date('d/m/Y', strtotime($r['date_envoi'])) ?></p>
                                </div>
                                <div class="card-footer">
                                    <?php if($r['piece_jointe']): ?>
                                        <a href="uploads/<?= htmlspecialchars($r['piece_jointe']) ?>" target="_blank" 
                                           class="inline-flex items-center text-blue-600 hover:text-blue-800 transition-colors font-medium responsive-text">
                                            <i class="fas fa-file-pdf mr-2"></i>
                                            PDF
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400 flex items-center responsive-text">
                                            <i class="fas fa-times mr-2"></i>
                                            Aucun fichier
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 lg:py-12">
                            <i class="fas fa-inbox text-gray-300 text-4xl lg:text-6xl mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-600 mb-2">Aucune requête</h3>
                            <p class="text-gray-500 responsive-text">Vous n'avez pas encore envoyé de requête.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto bg-white">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<script>
// Alpine.js data and functions
function app() {
    return {
        mobileMenuOpen: false,
        userDropdownOpen: false,
        
        // Données des professeurs
        professors: {
            <?php foreach($ec_professeurs as $ec_id => $prof): ?>
            "<?= $ec_id ?>": {
                nom: "<?= $prof['nom'] ?>",
                prenom: "<?= $prof['prenom'] ?>"
            },
            <?php endforeach; ?>
        },
        
        // Méthode pour mettre à jour le professeur
        updateProfessor(ecId) {
            console.log('EC sélectionné:', ecId);
            console.log('Professeurs disponibles:', this.professors);
            
            const profNomInput = document.querySelector('input[name="prof_nom"]');
            const profPrenomInput = document.querySelector('input[name="prof_prenom"]');
            
            if (this.professors[ecId]) {
                profNomInput.value = this.professors[ecId].nom;
                profPrenomInput.value = this.professors[ecId].prenom;
                console.log('Professeur mis à jour:', this.professors[ecId]);
            } else {
                profNomInput.value = 'Non attribué';
                profPrenomInput.value = '';
                console.log('Aucun professeur trouvé pour cet EC');
            }
        },
        
        init() {
            console.log('Application initialisée');
            console.log('Données des professeurs:', this.professors);
            
            // Fermer le menu au resize seulement si on passe en desktop
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) {
                    this.mobileMenuOpen = false;
                }
            });
        }
    }
}

// Initialiser après le chargement du DOM
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du drag & drop pour les fichiers
    const fileInput = document.querySelector('input[name="piece_jointe"]');
    const fileInputContainer = fileInput?.parentElement;

    if (fileInputContainer) {
        fileInputContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('border-blue-500', 'bg-blue-50');
        });

        fileInputContainer.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');
        });

        fileInputContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
            }
        });
    }
    
    // Debug: Vérifier que les éléments existent
    console.log('Éléments du formulaire:', {
        ecSelect: document.querySelector('select[name="element_constitutif"]'),
        profNom: document.querySelector('input[name="prof_nom"]'),
        profPrenom: document.querySelector('input[name="prof_prenom"]')
    });
});
</script>

</body>
</html>

<?php
// Fermer la connexion
$conn->close();
?>