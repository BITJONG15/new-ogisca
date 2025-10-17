<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Vérification de session manuelle
if (!isset($_SESSION['matricule']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

// Vérifier que c'est bien un enseignant OU ADM
$prefix = strtoupper(substr($_SESSION['matricule'], 0, 2));

if ($prefix !== 'EN' && $prefix !== 'ADM') {
    header("Location: ../login.php");
    exit();
}

$matricule = $_SESSION['matricule'];

// Connexion à la base de données
$servername = "localhost";
$username = "root";
$password = "";
$database = "gestion_academique";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
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
$userId = $_SESSION['user_id'];

// Récupérer les EC attribués - REQUÊTE SIMPLIFIÉE POUR DÉBUGUER
$sql = $conn->prepare("
    SELECT 
        ec.ID_EC, 
        ec.Nom_EC, 
        n.nom AS niveau, 
        ec.division, 
        ec.Modalites_Controle,
        prog.date_fin,
        prog.id AS programmation_id
    FROM attribution_ec a
    JOIN element_constitutif ec ON a.ID_EC = ec.ID_EC
    JOIN niveau n ON ec.id_niveau = n.id
    LEFT JOIN programmations prog ON prog.palier_id IN (
        SELECT palier_id FROM palier_ec WHERE ID_EC = ec.ID_EC
    )
    WHERE a.id_enseignants = ?
");
$sql->bind_param("i", $userId);
$sql->execute();
$result = $sql->get_result();
$ecs = $result->fetch_all(MYSQLI_ASSOC);
$sql->close();

$now = new DateTime();
$photo_url = !empty($photo) ? "../uploads/" . $photo : 'https://www.svgrepo.com/show/382106/user-circle.svg';

// DEBUG: Afficher les données pour vérification
echo "<!-- DEBUG: Nombre d'EC trouvés: " . count($ecs) . " -->";
foreach($ecs as $index => $ec) {
    echo "<!-- EC $index: " . htmlspecialchars($ec['Nom_EC']) . " - Programmation ID: " . ($ec['programmation_id'] ?? 'NULL') . " - Date fin: " . ($ec['date_fin'] ?? 'NULL') . " -->";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes EC | OGISCA</title>
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

.status-pending {
    background: #f3f4f6;
    color: #6b7280;
}

/* EC Grid */
.ec-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    padding: 0 2rem;
}

.ec-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary);
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
}

.ec-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.ec-card.pending {
    background: #f9fafb;
    border-left-color: #d1d5db;
    color: #6b7280;
    cursor: not-allowed;
}

.ec-card.pending:hover {
    transform: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.ec-card.expired {
    background: #fef2f2;
    border-left-color: #ef4444;
    color: #991b1b;
    cursor: not-allowed;
}

.ec-card.expired:hover {
    transform: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.ec-card.active {
    border-left-color: #10b981;
}

.ec-card.active:hover {
    border-left-color: #059669;
}

/* Mobile optimizations */
@media (max-width: 640px) {
    .ec-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 0 1rem;
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
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Mes Éléments Constitutifs</h1>
                    <p class="text-blue-100 opacity-90 text-sm md:text-base">Gérez vos EC attribués</p>
                </div>
                <div class="text-white text-sm mt-3 md:mt-0">
                    <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">
                        <?= count($ecs) ?> EC(s) attribué(s)
                    </span>
                </div>
            </div>
        </div>

        <?php if(empty($ecs)): ?>
            <div class="card p-8 text-center mx-4 md:mx-8">
                <i class="fas fa-book text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun EC attribué</h3>
                <p class="text-gray-500">Vous n'avez aucun EC attribué pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="ec-grid">
                <?php 
                $ecActifs = 0;
                $ecEnAttente = 0;
                $ecExpires = 0;
                
                foreach($ecs as $ec): 
                    // LOGIQUE CORRIGÉE POUR LA PROGRAMMATION
                    $estProgramme = !empty($ec['programmation_id']) && !empty($ec['date_fin']);
                    $dateFinCorrection = $estProgramme ? new DateTime($ec['date_fin']) : null;
                    $estDepasse = $estProgramme && $dateFinCorrection && $dateFinCorrection < $now;
                    
                    // Déterminer le statut
                    if (!$estProgramme) {
                        $statut = 'pending';
                        $ecEnAttente++;
                    } elseif ($estDepasse) {
                        $statut = 'expired';
                        $ecExpires++;
                    } else {
                        $statut = 'active';
                        $ecActifs++;
                    }
                ?>
                    <?php if ($statut === 'pending'): ?>
                        <!-- EC non programmé - GRIS (non clicable) -->
                        <div class="ec-card pending">
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="text-lg font-bold text-gray-500"><?= htmlspecialchars($ec['Nom_EC']) ?></h3>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock mr-1"></i> En attente
                                </span>
                            </div>
                            <div class="space-y-2 text-gray-500">
                                <div class="flex items-center">
                                    <i class="fas fa-graduation-cap mr-2 w-4"></i>
                                    <span>Niveau : <?= htmlspecialchars($ec['niveau']) ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-layer-group mr-2 w-4"></i>
                                    <span>Division : <?= htmlspecialchars($ec['division']) ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-tasks mr-2 w-4"></i>
                                    <span class="text-sm"><?= htmlspecialchars($ec['Modalites_Controle']) ?></span>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-200">
                                <p class="text-gray-500 text-sm font-medium">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Palier non programmé
                                </p>
                            </div>
                        </div>
                    <?php elseif ($statut === 'expired'): ?>
                        <!-- EC expiré - ROUGE (non clicable) -->
                        <div class="ec-card expired">
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="text-lg font-bold text-red-600 line-through"><?= htmlspecialchars($ec['Nom_EC']) ?></h3>
                                <span class="status-badge status-expired">
                                    <i class="fas fa-clock mr-1"></i> Expiré
                                </span>
                            </div>
                            <div class="space-y-2 text-red-500">
                                <div class="flex items-center">
                                    <i class="fas fa-graduation-cap mr-2 w-4"></i>
                                    <span>Niveau : <?= htmlspecialchars($ec['niveau']) ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-layer-group mr-2 w-4"></i>
                                    <span>Division : <?= htmlspecialchars($ec['division']) ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-tasks mr-2 w-4"></i>
                                    <span class="text-sm"><?= htmlspecialchars($ec['Modalites_Controle']) ?></span>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-red-200">
                                <p class="text-red-600 text-sm font-medium">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Date de correction dépassée
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- EC actif et programmé - VERT (clicable) -->
                        <a href="fiche_notes.php?ec=<?= urlencode($ec['ID_EC']) ?>" class="ec-card active">
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($ec['Nom_EC']) ?></h3>
                                <span class="status-badge status-active">
                                    <i class="fas fa-check-circle mr-1"></i> Actif
                                </span>
                            </div>
                            <div class="space-y-2 text-gray-600">
                                <div class="flex items-center">
                                    <i class="fas fa-graduation-cap mr-2 w-4 text-green-500"></i>
                                    <span>Niveau : <?= htmlspecialchars($ec['niveau']) ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-layer-group mr-2 w-4 text-green-500"></i>
                                    <span>Division : <?= htmlspecialchars($ec['division']) ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-tasks mr-2 w-4 text-green-500"></i>
                                    <span class="text-sm"><?= htmlspecialchars($ec['Modalites_Controle']) ?></span>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100">
                                <p class="text-green-600 text-sm font-medium flex items-center">
                                    <i class="fas fa-arrow-right mr-2"></i>
                                    Cliquer pour saisir les notes
                                </p>
                            </div>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Statistiques des EC -->
            <div class="stats-grid mt-8">
                <div class="card fade-slide-up delay-1">
                    <div class="p-6 stat-card">
                        
                        <div class="text-gray-500 uppercase text-sm font-semibold mb-2">EC Actifs</div>
                        <div class="text-3xl font-bold text-green-600 mb-2"><?= $ecActifs ?></div>
                        <div class="text-xs text-gray-500">
                            <i class="fas fa-play-circle mr-1"></i> Accessibles
                        </div>
                    </div>
                </div>

                <div class="card fade-slide-up delay-2">
                    <div class="p-6 stat-card">
                        
                        <div class="text-gray-500 uppercase text-sm font-semibold mb-2">EC En Attente</div>
                        <div class="text-3xl font-bold text-gray-600 mb-2"><?= $ecEnAttente ?></div>
                        <div class="text-xs text-gray-500">
                            <i class="fas fa-pause-circle mr-1"></i> Non programmés
                        </div>
                    </div>
                </div>

                <div class="card fade-slide-up delay-3">
                    <div class="p-6 stat-card">
                        
                        <div class="text-gray-500 uppercase text-sm font-semibold mb-2">EC Expirés</div>
                        <div class="text-3xl font-bold text-red-600 mb-2"><?= $ecExpires ?></div>
                        <div class="text-xs text-gray-500">
                            <i class="fas fa-stop-circle mr-1"></i> Dates dépassées
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

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
</script>

</body>
</html>

<?php
// Fermer la connexion
$conn->close();
?>