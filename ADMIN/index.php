<?php
session_start();
// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_academique");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

if (!isset($_SESSION['matricule'])) {
    header('Location: ../login.php');
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
$photo_url = 'https://www.svgrepo.com/show/382106/user-circle.svg'; // image par défaut

// Récupérer le matricule en session
$matricule = $_SESSION['matricule'];
$nom = $matricule;
$photo = 'avatar.png'; // valeur par défaut

// Déterminer la table source selon préfixe matricule
$prefix = strtoupper(substr($matricule, 0, 3));
if ($prefix === 'EN') {
    $stmt = $conn->prepare("SELECT nom, photo FROM enseignants WHERE matricule = ?");
} elseif ($prefix === 'ET') {
    $stmt = $conn->prepare("SELECT nom, photo FROM etudiants WHERE matricule = ?");
} else {
    $stmt = null; // On n'a pas de source pour ce matricule
}

if ($stmt) {
    $stmt->bind_param("s", $matricule);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $nom = $row['nom'] ?? $matricule;
        $photo = $row['photo'] ?? 'avatar.png';
    }
    $stmt->close();
}

// Stats simples
$nbEtudiants = $conn->query("SELECT COUNT(*) as c FROM etudiants")->fetch_assoc()['c'];
$nbRequetesEnAttente = $conn->query("SELECT COUNT(*) as c FROM requetes WHERE statut = 'en attente'")->fetch_assoc()['c'];
$nbEnseignants = $conn->query("SELECT COUNT(*) as c FROM enseignants")->fetch_assoc()['c'];

// paliers en cours
$now = date('Y-m-d');
$respaliers = $conn->query("SELECT id, palier_id, date_fin FROM programmations WHERE date_fin > '$now' ORDER BY date_fin ASC");
$paliersEnCours = [];
if ($respaliers) {
    while ($row = $respaliers->fetch_assoc()) {
        $paliersEnCours[] = $row;
    }
}

// Données graphique 1 : Requêtes par mois année en cours
$year = date('Y');
$sqlReqParMois = "
    SELECT MONTH(date_envoi) as mois, COUNT(*) as total 
    FROM requetes 
    WHERE YEAR(date_envoi) = $year 
    GROUP BY mois
    ORDER BY mois
";
$resReqParMois = $conn->query($sqlReqParMois);
$reqParMois = array_fill(1, 12, 0);
while ($row = $resReqParMois->fetch_assoc()) {
    $reqParMois[intval($row['mois'])] = intval($row['total']);
}

// Données graphique 2 : Étudiants inscrits par année
$sqlEtudiantsParAn = "
    SELECT YEAR(created_at) as annee, COUNT(*) as total 
    FROM etudiants 
    GROUP BY annee
    ORDER BY annee
";
$resEtudiantsParAn = $conn->query($sqlEtudiantsParAn);
$etudiantsParAn = [];
while ($row = $resEtudiantsParAn->fetch_assoc()) {
    $etudiantsParAn[$row['annee']] = intval($row['total']);
}

if (count($etudiantsParAn) > 0) {
    $years = array_keys($etudiantsParAn);
    $minYear = min($years);
    $maxYear = max($years);
    $allYears = range($minYear, $maxYear);
    $etudiantsParAnComplet = [];
    foreach ($allYears as $y) {
        $etudiantsParAnComplet[] = $etudiantsParAn[$y] ?? 0;
    }
} else {
    $allYears = [];
    $etudiantsParAnComplet = [];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Dashboard - Admin</title>
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
</style>
</head>
<body class="flex bg-gray-100 min-h-screen">

<!-- Overlay for mobile menu -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
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
            <li class="nav-item active">
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
                <a href="notes_globales.php" class="flex items-center px-4 py-3">
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

    <main class="p-6 space-y-6 overflow-auto">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-2">
            <h2 class="text-2xl font-bold text-gray-800">Tableau de bord administratif</h2>
            <div class="text-sm text-gray-500">
                <i class="far fa-calendar-alt mr-2"></i>
                <?php echo date('d/m/Y'); ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

            <!-- Carte Étudiants -->
            <div class="card fade-slide-up delay-1">
                <div class="p-6 stat-card">
                    <div class="stat-icon bg-blue-500">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">Étudiants</div>
                    <div class="text-3xl font-bold text-blue-700 mb-2"><?= $nbEtudiants ?></div>
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-database mr-1"></i> Total inscrits
                    </div>
                </div>
            </div>

            <!-- Carte Requêtes en attente -->
            <div class="card fade-slide-up delay-2">
                <div class="p-6 stat-card">
                    <div class="stat-icon bg-red-500">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">Requêtes en attente</div>
                    <div class="text-3xl font-bold text-red-600 mb-2"><?= $nbRequetesEnAttente ?></div>
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-clock mr-1"></i> En attente de traitement
                    </div>
                </div>
            </div>

            <!-- Carte Enseignants -->
            <div class="card fade-slide-up delay-3">
                <div class="p-6 stat-card">
                    <div class="stat-icon bg-green-500">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">Enseignants</div>
                    <div class="text-3xl font-bold text-green-600 mb-2"><?= $nbEnseignants ?></div>
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-users mr-1"></i> Personnel académique
                    </div>
                </div>
            </div>

            <!-- Carte Paliers en cours -->
            <div class="card fade-slide-up delay-4">
                <div class="p-6 stat-card">
                    <div class="stat-icon bg-purple-500">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">Paliers en cours</div>
                    <div class="text-3xl font-bold text-purple-700 mb-2"><?= count($paliersEnCours) ?></div>
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-spinner mr-1"></i> En progression
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">

            <!-- Graphique 1 -->
            <div class="card p-6 fade-slide-up delay-4">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Requêtes par mois (<?= $year ?>)</h3>
                    <div class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                        <i class="far fa-chart-bar mr-1"></i> Statistiques
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="chartRequetes"></canvas>
                </div>
            </div>

            <!-- Graphique 2 -->
            <div class="card p-6 fade-slide-up delay-4">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Étudiants inscrits par année</h3>
                    <div class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                        <i class="fas fa-user-graduate mr-1"></i> Évolution
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="chartEtudiants"></canvas>
                </div>
            </div>

        </div>

        <!-- Section Paliers en cours -->
        <div class="card p-6 mt-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Détails des paliers en cours</h3>
                <div class="text-sm text-blue-500 bg-blue-50 px-3 py-1 rounded-full">
                    <?= count($paliersEnCours) ?> palier(s) actif(s)
                </div>
            </div>
            
            <?php if (count($paliersEnCours) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($paliersEnCours as $palier): 
                    $endDate = new DateTime($palier['date_fin']);
                    $now = new DateTime();
                    $interval = $now->diff($endDate);
                    $daysLeft = $interval->format('%a');
                    $percentage = min(100, max(5, 100 - ($daysLeft * 100 / 30))); // Approximation
                ?>
                <div class="palier-item">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($palier['id'] ?? 'N/A') ?></h4>
                        <span class="text-sm <?= $daysLeft < 7 ? 'text-red-500' : 'text-gray-500' ?>">
                            <i class="far fa-clock mr-1"></i> <?= $daysLeft ?> jour(s) restant(s)
                        </span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-value bg-blue-500" style="width: <?= $percentage ?>%"></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-2">
                        Date de fin: <?= htmlspecialchars($palier['date_fin'] ?? 'N/A') ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-inbox fa-3x mb-4 opacity-30"></i>
                <p>Aucun palier en cours actuellement</p>
            </div>
            <?php endif; ?>
        </div>

    </main>

    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<script>
// Menu toggle for mobile
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

// Charts data
const moisLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
const reqParMoisData = <?= json_encode(array_values($reqParMois)) ?>;

const etudiantsLabels = <?= json_encode(array_map('strval', $allYears)) ?>;
const etudiantsData = <?= json_encode($etudiantsParAnComplet) ?>;

// Initialize charts
const initCharts = () => {
    const ctxReq = document.getElementById('chartRequetes').getContext('2d');
    const chartRequetes = new Chart(ctxReq, {
        type: 'bar',
        data: {
            labels: moisLabels,
            datasets: [{
                label: 'Nombre de requêtes',
                data: reqParMoisData,
                backgroundColor: 'rgba(79, 70, 229, 0.7)',
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 1,
                borderRadius: 6,
                hoverBackgroundColor: 'rgba(79, 70, 229, 0.9)',
            }]
        },
        options: {
            animation: { duration: 1000, easing: 'easeOutQuart' },
            scales: {
                y: { 
                    beginAtZero: true, 
                    precision: 0,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    const ctxEtudiants = document.getElementById('chartEtudiants').getContext('2d');
    const chartEtudiants = new Chart(ctxEtudiants, {
        type: 'line',
        data: {
            labels: etudiantsLabels,
            datasets: [{
                label: "Nombre d'étudiants",
                data: etudiantsData,
                fill: true,
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 3,
                tension: 0.3,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                pointHoverBackgroundColor: 'rgba(5, 150, 105, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            animation: { duration: 1200, easing: 'easeOutQuart' },
            scales: {
                y: { 
                    beginAtZero: true, 
                    precision: 0,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        }
    });
};

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', initCharts);
</script>

</body>
</html>