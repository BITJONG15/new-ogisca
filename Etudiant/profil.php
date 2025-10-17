<?php
session_start();
require_once("../include/db.php");

// Vérifier session étudiant
if (!isset($_SESSION['matricule']) || !str_starts_with($_SESSION['matricule'], "ETU")) {
    header("Location: ../login.php");
    exit;
}

$matricule = $_SESSION['matricule'];

// Récupérer infos étudiant
$stmt = $conn->prepare("
    SELECT e.nom, e.prenom, e.matricule, e.email, n.nom AS niveau, d.nom AS division, e.date_naissance
    FROM etudiants e
    LEFT JOIN niveau n ON e.niveau_id = n.id
    LEFT JOIN division d ON e.division_id = d.id
    WHERE e.matricule = ?
");
$stmt->bind_param("s", $matricule);
$stmt->execute();
$etudiant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$etudiant) {
    die("Étudiant non trouvé.");
}

$photo_url = 'https://www.svgrepo.com/show/382106/user-circle.svg';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Profil Étudiant | OGISCA</title>
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

/* Layout principal */
.main-container {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
    box-shadow: 0 0 25px rgba(79, 70, 229, 0.15);
    color: white;
    width: 280px;
    flex-shrink: 0;
    transform: translateX(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 50;
}

.sidebar.open {
    transform: translateX(0);
}

@media (min-width: 1024px) {
    .sidebar {
        transform: translateX(0);
        position: relative;
        width: 256px;
    }
    
    .main-content-area {
        margin-left: 0;
    }
}

/* Navigation */
.nav-item {
    border-radius: 12px;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.nav-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
    transform: translateX(8px);
}

.nav-item.active {
    background-color: rgba(255, 255, 255, 0.15);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Contenu principal */
.main-content-area {
    flex: 1;
    transition: margin-left 0.3s ease;
    min-width: 0;
}

.topbar {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
    border-bottom: 1px solid #e5e7eb;
}

.card {
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    background: white;
    transition: all 0.3s ease;
    border: 1px solid #f3f4f6;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
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
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    padding: 1rem;
    margin-top: 0.5rem;
    z-index: 100;
    border: 1px solid #e5e7eb;
}

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

/* Overlay mobile */
.backdrop {
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    background: rgba(0, 0, 0, 0.5);
}

.backdrop.active {
    opacity: 1;
    visibility: visible;
}

/* Boutons tactiles */
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

/* Responsive */
@media (max-width: 640px) {
    .profile-info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .responsive-text {
        font-size: 0.875rem;
        line-height: 1.25rem;
    }
    
    .responsive-heading {
        font-size: 1.5rem;
        line-height: 2rem;
    }
}

@media (min-width: 641px) and (max-width: 768px) {
    .profile-info-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }
}

@media (min-width: 769px) and (max-width: 1023px) {
    .profile-info-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
}

@media (min-width: 1024px) {
    .profile-info-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem;
    }
}

/* Améliorations visuelles */
.sidebar-content {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.sidebar-nav {
    flex: 1;
    overflow-y: auto;
}

.sidebar-footer {
    margin-top: auto;
}
</style>
</head>
<body class="bg-gray-100">

<!-- Overlay pour mobile -->
<div id="backdrop" class="backdrop fixed inset-0 z-40 lg:hidden"></div>

<!-- Bouton menu mobile -->
<button id="menuButton" class="lg:hidden fixed top-4 left-4 z-50 touch-button bg-primary text-white rounded-full p-3 shadow-lg transition-all hover:scale-105">
    <i class="fas fa-bars text-lg"></i>
</button>

<div class="main-container">
    <!-- Sidebar unique -->
    <div id="sidebar" class="sidebar">
        <div class="sidebar-content">
            <!-- En-tête sidebar -->
            <div class="flex items-center justify-between p-6 border-b border-primary-light">
                <div class="flex items-center">
                    <img src="../admin/logo simple sans fond.png" class="w-8 h-8 mr-3 rounded" alt="logo" />
                    <h1 class="text-xl font-bold">OGISCA</h1>
                </div>
                <button id="closeSidebar" class="lg:hidden text-white touch-button hover:bg-white/10 rounded-lg transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Navigation -->
            <nav class="sidebar-nav p-4 space-y-2">
                <a href="AccueilEtudiant.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-primary-light transition-all duration-300 nav-item">
                    <i class="fas fa-home w-6 mr-3 text-center"></i>
                    <span class="font-medium">Accueil</span>
                </a>
                <a href="MesCours.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-primary-light transition-all duration-300 nav-item">
                    <i class="fas fa-book w-6 mr-3 text-center"></i>
                    <span class="font-medium">Mes Cours</span>
                </a>
                <a href="test.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-primary-light transition-all duration-300 nav-item">
                    <i class="fas fa-tasks w-6 mr-3 text-center"></i>
                    <span class="font-medium">Mes Requêtes</span>
                </a>
                <a href="Profil.php" class="flex items-center px-4 py-3 rounded-lg bg-primary-light text-white transition-all duration-300 nav-item active">
                    <i class="fas fa-user w-6 mr-3 text-center"></i>
                    <span class="font-medium">Mon Profil</span>
                </a>
            </nav>
            
            <!-- Déconnexion en bas -->
            <div class="sidebar-footer pt-4 border-t border-primary-light">
                <a href="../logout.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-red-600 transition-all duration-300 nav-item">
                    <i class="fas fa-sign-out-alt w-6 mr-3 text-center"></i>
                    <span class="font-medium">Déconnexion</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="main-content-area flex-1 flex flex-col min-h-screen">
        <!-- Topbar -->
        <header class="topbar flex items-center justify-between px-4 lg:px-8 py-4 sticky top-0 z-30">
            <div class="flex items-center space-x-4">
                <div class="text-gray-700 responsive-text">
                    <span class="font-semibold"><?= htmlspecialchars($etudiant['prenom']) ?> <?= htmlspecialchars($etudiant['nom']) ?></span>
                    <span class="text-gray-500 text-sm hidden xs:inline"> - <?= htmlspecialchars($etudiant['niveau'] ?? 'Non défini') ?></span>
                </div>
            </div>
            
            <div class="user-profile">
                <div class="flex items-center space-x-3 cursor-pointer touch-button">
                    <div class="text-right hidden md:block">
                        <div class="text-gray-700 font-semibold responsive-text">
                            <?= htmlspecialchars($etudiant['prenom']) ?> <?= htmlspecialchars($etudiant['nom']) ?>
                        </div>
                        <div class="text-gray-500 text-sm">
                            Matricule : <?= htmlspecialchars($etudiant['matricule']) ?>
                        </div>
                    </div>
                    <div class="relative">
                        <img src="<?= $photo_url ?>" alt="Profil" class="w-10 h-10 lg:w-12 lg:h-12 rounded-full border-2 border-white shadow-md" />
                        <span class="absolute bottom-0 right-0 w-2 h-2 lg:w-3 lg:h-3 bg-green-500 rounded-full border-2 border-white"></span>
                    </div>
                </div>
                
                <div class="user-dropdown">
                    <div class="flex items-center space-x-3 pb-3 border-b border-gray-100">
                        <img src="<?= $photo_url ?>" alt="Profil" class="w-12 h-12 lg:w-14 lg:h-14 rounded-full border-2 border-gray-200" />
                        <div>
                            <div class="font-semibold text-gray-800"><?= htmlspecialchars($etudiant['prenom']) ?> <?= htmlspecialchars($etudiant['nom']) ?></div>
                            <div class="text-sm text-gray-500"><?= htmlspecialchars($etudiant['matricule']) ?></div>
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

        <!-- Contenu -->
        <main class="main-content flex-1">
            <div class="p-4 lg:p-8 scroll-mobile">
                <!-- En-tête de page -->
                <div class="page-header">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div>
                            <h1 class="text-2xl lg:text-3xl font-bold mb-2 responsive-heading">Mon Profil</h1>
                            <p class="text-blue-100 opacity-90 responsive-text">Consultez et gérez vos informations personnelles</p>
                        </div>
                        <div class="text-white text-sm mt-4 md:mt-0">
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">
                                Étudiant
                            </span>
                        </div>
                    </div>
                </div>

                <div class="max-w-6xl mx-auto">
                    <!-- Carte profil principal -->
                    <div class="card p-6 lg:p-8 mb-6 lg:mb-8">
                        <div class="flex flex-col lg:flex-row items-center lg:items-start space-y-6 lg:space-y-0 lg:space-x-8">
                            <!-- Photo de profil -->
                            <div class="text-center lg:text-left">
                                <div class="relative inline-block">
                                    <img src="<?= $photo_url ?>" 
                                         alt="Photo de profil" 
                                         class="w-32 h-32 lg:w-40 lg:h-40 rounded-full object-cover border-4 border-blue-100 shadow-lg" />
                                    <div class="absolute bottom-2 right-2 w-6 h-6 lg:w-8 lg:h-8 bg-green-500 rounded-full border-2 border-white flex items-center justify-center">
                                        <i class="fas fa-check text-white text-xs"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <h2 class="text-xl lg:text-2xl font-bold text-gray-800"><?= htmlspecialchars($etudiant['prenom']) ?> <?= htmlspecialchars($etudiant['nom']) ?></h2>
                                    <p class="text-gray-600 mt-1"><?= htmlspecialchars($etudiant['niveau'] ?? 'Non renseigné') ?> - <?= htmlspecialchars($etudiant['division'] ?? 'Non renseigné') ?></p>
                                    <div class="mt-3 bg-blue-50 rounded-lg px-4 py-2 inline-block">
                                        <p class="text-blue-700 text-sm font-medium">Matricule: <?= htmlspecialchars($etudiant['matricule']) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Informations détaillées -->
                            <div class="flex-1 w-full">
                                <h3 class="text-lg lg:text-xl font-semibold text-gray-800 mb-6 flex items-center">
                                    <i class="fas fa-info-circle mr-3 text-blue-500"></i>
                                    Informations Personnelles
                                </h3>
                                
                                <div class="profile-info-grid">
                                    <!-- Ligne 1 -->
                                    <div class="card p-4 lg:p-6">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                                <i class="fas fa-id-card text-blue-600"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm text-gray-500">Nom complet</p>
                                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($etudiant['nom']) ?> <?= htmlspecialchars($etudiant['prenom']) ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card p-4 lg:p-6">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                                <i class="fas fa-hashtag text-green-600"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm text-gray-500">Matricule</p>
                                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($etudiant['matricule']) ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ligne 2 -->
                                    <div class="card p-4 lg:p-6">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                                <i class="fas fa-envelope text-purple-600"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm text-gray-500">Email</p>
                                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($etudiant['email'] ?? 'Non renseigné') ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card p-4 lg:p-6">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                                <i class="fas fa-birthday-cake text-yellow-600"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm text-gray-500">Date de naissance</p>
                                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($etudiant['date_naissance'] ?? 'Non renseigné') ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ligne 3 -->
                                    <div class="card p-4 lg:p-6">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                                                <i class="fas fa-graduation-cap text-indigo-600"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm text-gray-500">Niveau</p>
                                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($etudiant['niveau'] ?? 'Non renseigné') ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card p-4 lg:p-6">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                                <i class="fas fa-users text-red-600"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm text-gray-500">Division</p>
                                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($etudiant['division'] ?? 'Non renseigné') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques rapides -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="card p-6 text-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-book text-blue-600 text-xl"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-800">12</p>
                            <p class="text-gray-600 text-sm">Éléments Constitutifs</p>
                        </div>
                        
                        <div class="card p-6 text-center">
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-800">8</p>
                            <p class="text-gray-600 text-sm">Notes disponibles</p>
                        </div>
                        
                        <div class="card p-6 text-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-tasks text-purple-600 text-xl"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-800">3</p>
                            <p class="text-gray-600 text-sm">Requêtes envoyées</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 bg-white">
            <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
        </footer>
    </div>
</div>

<script>
// Gestion de la sidebar
const menuButton = document.getElementById('menuButton');
const closeSidebar = document.getElementById('closeSidebar');
const sidebar = document.getElementById('sidebar');
const backdrop = document.getElementById('backdrop');

function openSidebar() {
    sidebar.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebarFunc() {
    sidebar.classList.remove('open');
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

// Événements
menuButton.addEventListener('click', openSidebar);
closeSidebar.addEventListener('click', closeSidebarFunc);
backdrop.addEventListener('click', closeSidebarFunc);

// Fermer avec la touche Échap
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeSidebarFunc();
    }
});

// Fermer automatiquement quand on redimensionne vers desktop
window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
        closeSidebarFunc();
    }
});

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Fermer la sidebar si on est sur desktop au chargement
    if (window.innerWidth >= 1024) {
        closeSidebarFunc();
    }
});
</script>

</body>
</html>