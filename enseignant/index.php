<?php
session_start();
require_once("../include/db.php");

// 🔐 Protection d'accès
if (!isset($_SESSION['matricule'])) {
    header("Location: ../login.php");
    exit();
}

// 📌 Déterminer si enseignant ou étudiant
$matricule = $_SESSION['matricule'];
$prefix = strtoupper(substr($matricule, 0, 2));

if (!str_starts_with($matricule, "EN") && !str_starts_with($matricule, "ADM")) {
    header("Location: index.php");
    exit;
}

// Récupérer les infos de l'enseignant
$sql = $conn->prepare("SELECT id, nom, prenom, photo FROM enseignants WHERE matricule = ?");
$sql->bind_param("s", $matricule);
$sql->execute();
$sql->bind_result($userId, $nom, $prenom, $photo);
$sql->fetch();
$sql->close();

// Stocker l'id dans la session
$_SESSION['user_id'] = $userId;

// 📊 Récupérer les stats
// EC attribués
$reqEc = $conn->prepare("SELECT COUNT(*) FROM attribution_ec WHERE id_enseignants = ?");
$reqEc->bind_param("i", $userId);
$reqEc->execute();
$reqEc->bind_result($totalEC);
$reqEc->fetch();
$reqEc->close();

// Notes saisies
$reqNotes = $conn->prepare("SELECT COUNT(*) FROM notes WHERE enseignant_id = ?");
$reqNotes->bind_param("i", $userId);
$reqNotes->execute();
$reqNotes->bind_result($totalNotes);
$reqNotes->fetch();
$reqNotes->close();

// Requêtes en attente
$reqRequetes = $conn->prepare("
    SELECT COUNT(r.id)
    FROM requetes r
    JOIN notes n ON r.note_id = n.id
    WHERE n.enseignant_id = ? AND r.statut = 'en attente'
");
$reqRequetes->bind_param("i", $userId);
$reqRequetes->execute();
$reqRequetes->bind_result($totalRequetes);
$reqRequetes->fetch();
$reqRequetes->close();

$photo_url = !empty($photo) ? "../uploads/" . $photo : 'https://www.svgrepo.com/show/382106/user-circle.svg';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Enseignant | OGISCA</title>
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
        
        /* Sidebar Responsive */
.sidebar {
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
    box-shadow: 0 0 25px rgba(219, 219, 230, 0.15);
    color: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-100%);
    width: 280px;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 1000;
    
}

.sidebar.open {
    transform: translateX(0);
}

@media (min-width: 1024px) {
    .sidebar {
        transform: translateX(0);
        position: relative;
        width: 280px;
        height: 100vh;
    }
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

        /* Custom styles for this page */
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

        .quick-actions-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin: 0 2rem;
        }

        @media (min-width: 640px) {
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .quick-actions-grid {
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
    </style>
</head>
<body class="flex bg-gray-100 min-h-screen" x-data="{ mobileMenuOpen: false, userDropdownOpen: false }">

<!-- Overlay for mobile menu -->
<div class="overlay" id="overlay" x-show="mobileMenuOpen" @click="mobileMenuOpen = false"></div>

<!-- Sidebar -->
        <div class="sidebar"
             :class="{ 'open': mobileMenuOpen }"
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
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <nav class="flex flex-col flex-1 p-4 space-y-2 overflow-y-auto">
                <a href="index.php" 
                   class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button nav-item active"
                   @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }">
                    <i class="fas fa-home w-5 mr-3 text-center"></i>
                    <span class="font-medium">DASHBOARD</span>
                </a>
                
                <a href="mes_ec.php" 
                   class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button nav-item"
                   @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }">
                    <i class="fas fa-book w-5 mr-3 text-center"></i>
                    <span class="font-medium">MES EC</span>
                </a>
                
                <a href="requetes_traitees.php" 
                   class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button nav-item"
                   @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }">
                    <i class="fas fa-tasks w-5 mr-3 text-center"></i>
                    <span class="font-medium">REQUÊTES</span>
                </a>
                
                <a href="profil.php" 
                   class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button nav-item"
                   @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }">
                    <i class="fas fa-user w-5 mr-3 text-center"></i>
                    <span class="font-medium">PROFIL</span>
                </a>
                
                <div class="mt-auto pt-4 border-t border-primary-light">
                    <a href="../logout.php" 
                       class="flex items-center px-4 py-4 rounded-lg hover:bg-red-600 transition-colors touch-button"
                       @click="if (window.innerWidth < 1024) { mobileMenuOpen = false; }">
                        <i class="fas fa-sign-out-alt w-5 mr-3 text-center"></i>
                        <span class="font-medium">Déconnexion</span>
                    </a>
                </div>
            </nav>
        </div>

<div class="flex-1 flex flex-col lg:ml-0">

<!-- Topbar -->
<header class="topbar flex items-center justify-between px-6 py-4 sticky top-0 z-40">
  <div class="flex items-center space-x-4">
    <!-- Bouton burger -->
    <button id="btnMenuToggle" class="lg:hidden text-gray-700 focus:outline-none" aria-label="Toggle menu">
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
          Bonjour, <?= htmlspecialchars($prenom) ?> <?= htmlspecialchars($nom) ?>
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
          <div class="font-semibold text-gray-800"><?= htmlspecialchars($prenom) ?> <?= htmlspecialchars($nom) ?></div>
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
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Tableau de Bord Enseignant</h1>
                    <p class="text-blue-100 opacity-90 text-sm md:text-base">Bienvenue, <?= htmlspecialchars($prenom . ' ' . $nom) ?></p>
                </div>
                <div class="text-white text-sm mt-3 md:mt-0">
                    <i class="far fa-calendar-alt mr-2"></i>
                    <?php echo date('d/m/Y'); ?>
                </div>
            </div>
        </div>

        <!-- Cartes de statistiques -->
        <div class="stats-grid">
            <!-- EC attribués -->
            <div class="card fade-slide-up delay-1">
                <div class="p-6 stat-card">
                    
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">EC Attribués</div>
                    <div class="text-3xl font-bold text-blue-700 mb-2"><?= $totalEC ?></div>
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-database mr-1"></i> Total EC assignés
                    </div>
                </div>
            </div>

            <!-- Notes saisies -->
            <div class="card fade-slide-up delay-2">
                <div class="p-6 stat-card">
                   
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">Notes Saisies</div>
                    <div class="text-3xl font-bold text-green-600 mb-2"><?= $totalNotes ?></div>
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-edit mr-1"></i> Notes enregistrées
                    </div>
                </div>
            </div>

            <!-- Requêtes en attente -->
            <div class="card fade-slide-up delay-3">
                <div class="p-6 stat-card">
                    
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">Requêtes en Attente</div>
                    <div class="text-3xl font-bold text-amber-600 mb-2"><?= $totalRequetes ?></div>
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-clock mr-1"></i> En attente de traitement
                    </div>
                </div>
            </div>
        </div>

        <!-- Section actions rapides -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Actions Rapides</h3>
                <div class="text-sm text-blue-500 bg-blue-50 px-3 py-1 rounded-full">
                    Accès direct
                </div>
            </div>
            
            <div class="quick-actions-grid">
                <a href="mes_ec.php" class="p-6 border-2 border-dashed border-gray-200 rounded-xl text-center hover:border-blue-500 hover:bg-blue-50 transition-colors group">
                    <i class="fas fa-book text-blue-600 text-2xl mb-3 group-hover:scale-110 transition-transform"></i>
                    <p class="font-medium text-gray-700">Gérer mes EC</p>
                    <p class="text-sm text-gray-500 mt-1">Voir et gérer mes éléments constitutifs</p>
                </a>
                
                <a href="requetes_traitees.php" class="p-6 border-2 border-dashed border-gray-200 rounded-xl text-center hover:border-green-500 hover:bg-green-50 transition-colors group">
                    <i class="fas fa-tasks text-green-600 text-2xl mb-3 group-hover:scale-110 transition-transform"></i>
                    <p class="font-medium text-gray-700">Voir les requêtes</p>
                    <p class="text-sm text-gray-500 mt-1">Traiter les demandes d'étudiants</p>
                </a>
                
                <a href="profil.php" class="p-6 border-2 border-dashed border-gray-200 rounded-xl text-center hover:border-purple-500 hover:bg-purple-50 transition-colors group">
                    <i class="fas fa-user text-purple-600 text-2xl mb-3 group-hover:scale-110 transition-transform"></i>
                    <p class="font-medium text-gray-700">Mon profil</p>
                    <p class="text-sm text-gray-500 mt-1">Gérer mes informations personnelles</p>
                </a>
            </div>
        </div>

    </main>

    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<script>
    // Menu toggle for mobile
    document.addEventListener('alpine:init', () => {
        Alpine.data('app', () => ({
            mobileMenuOpen: false,
            userDropdownOpen: false,
            
            init() {
                // Close mobile menu when resizing to desktop
                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 1024) {
                        this.mobileMenuOpen = false;
                    }
                });
            }
        }));
    });

    // Initialize animations on load
    document.addEventListener('DOMContentLoaded', function() {
        // Animation des cartes de statistiques
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        // Observer les cartes de statistiques
        document.querySelectorAll('.fade-slide-up').forEach(card => {
            observer.observe(card);
        });
    });
</script>

</body>
</html>