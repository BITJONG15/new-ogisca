<?php
session_start();
require_once("../include/db.php");

// Vérification session étudiant
if (!isset($_SESSION['matricule']) || !str_starts_with($_SESSION['matricule'], "ETU")) {
    header("Location: ../index.php");
    exit;
}

$matricule = $_SESSION['matricule'];
$user_id = $_SESSION['user_id'] ?? null;

// Récupérer l'étudiant_id lié au user
$stmt = $conn->prepare("SELECT id FROM etudiants WHERE matricule = ?");
$stmt->bind_param("s", $matricule);
$stmt->execute();
$res = $stmt->get_result();
$etudiant = $res->fetch_assoc();
$stmt->close();

if (!$etudiant) {
    die("Étudiant non trouvé.");
}
$etudiant_id = $etudiant['id'];

// Récupérer l'ID EC depuis l'URL
$ec_id = $_GET['id'] ?? $_GET['ec'] ?? '';
if (!$ec_id) {
    die("Cours non spécifié.");
}

// Récupérer les infos de l'EC
$stmt = $conn->prepare("
    SELECT Nom_EC, Modalites_Controle, id_niveau, division
    FROM element_constitutif
    WHERE ID_EC = ?
");
$stmt->bind_param("s", $ec_id);
$stmt->execute();
$ec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ec) {
    die("Cours introuvable.");
}

// Modalités
$modalites = array_map('trim', explode(',', $ec['Modalites_Controle']));

// Récupérer notes de l'étudiant connecté pour cet EC
$stmt = $conn->prepare("
    SELECT modalite, valeur
    FROM notes
    WHERE etudiant_id = ? AND ID_EC = ?
");
$stmt->bind_param("is", $etudiant_id, $ec_id);
$stmt->execute();
$notes_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Organiser notes par modalité
$notes = [];
foreach ($notes_result as $n) {
    $notes[strtoupper($n['modalite'])] = $n['valeur'];
}

// Fonction d'appréciation selon note
function appreciation($note) {
    if ($note >= 16) return "Excellent";
    if ($note >= 14) return "Très bien";
    if ($note >= 12) return "Bien";
    if ($note >= 10) return "Passable";
    return "Insuffisant";
}

// Calcul note finale
$note_finale = null;
$notes_valides = [];
foreach ($modalites as $mod) {
    $mod_up = strtoupper($mod);
    if (isset($notes[$mod_up]) && is_numeric($notes[$mod_up])) {
        $notes_valides[] = floatval($notes[$mod_up]);
    }
}
if (count($notes_valides) > 0) {
    $note_finale = array_sum($notes_valides) / count($notes_valides);
}

function badgeColor($note) {
    if ($note >= 16) return "from-emerald-500 to-green-600";
    if ($note >= 14) return "from-green-400 to-emerald-500";
    if ($note >= 12) return "from-amber-400 to-yellow-500";
    if ($note >= 10) return "from-orange-400 to-amber-500";
    return "from-red-500 to-rose-600";
}

function progressColor($note) {
    if ($note >= 16) return "bg-gradient-to-r from-emerald-500 to-green-600";
    if ($note >= 14) return "bg-gradient-to-r from-green-400 to-emerald-500";
    if ($note >= 12) return "bg-gradient-to-r from-amber-400 to-yellow-500";
    if ($note >= 10) return "bg-gradient-to-r from-orange-400 to-amber-500";
    return "bg-gradient-to-r from-red-500 to-rose-600";
}
?>

<!DOCTYPE html>
<html lang="fr" x-data="{ sidebarOpen: false }">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Détails EC - <?=htmlspecialchars($ec['Nom_EC'])?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root {
    --primary: #4f46e5;
    --primary-dark: #4338ca;
    --primary-light: #6366f1;
    --secondary: #10b981;
    --accent: #f59e0b;
    --danger: #ef4444;
  }
  
  .glass-effect {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }
  
  .sidebar {
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
    box-shadow: 0 0 25px rgba(79, 70, 229, 0.15);
    transition: transform 0.3s ease;
  }
  
  .sidebar-overlay {
    background: rgba(0, 0, 0, 0.5);
    z-index: 40;
    backdrop-filter: blur(5px);
  }
  
  .card {
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    background: white;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
  }
  
  .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
  }
  
  .floating-shape {
    animation: float 6s ease-in-out infinite;
  }
  
  @keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-15px) rotate(3deg); }
  }
  
  .progress-ring {
    transition: stroke-dashoffset 0.35s;
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
  }
  
  .gradient-text {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  
  .table-row-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  }
  
  .pulse-glow {
    animation: pulse-glow 2s ease-in-out infinite alternate;
  }
  
  @keyframes pulse-glow {
    from { box-shadow: 0 0 15px rgba(79, 70, 229, 0.3); }
    to { box-shadow: 0 0 25px rgba(79, 70, 229, 0.5); }
  }

  /* Sidebar responsive */
  @media (min-width: 768px) {
    .sidebar {
      transform: translateX(0) !important;
    }
    .sidebar-overlay {
      display: none !important;
    }
  }
</style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

<!-- Background Elements -->
<div class="fixed inset-0 overflow-hidden pointer-events-none">
  <div class="absolute -top-20 -right-20 w-40 h-40 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl opacity-20 floating-shape"></div>
  <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-cyan-200 rounded-full mix-blend-multiply filter blur-3xl opacity-20 floating-shape" style="animation-delay: -2s;"></div>
</div>

<!-- Sidebar Overlay (mobile) -->
<div 
  x-show="sidebarOpen" 
  @click="sidebarOpen = false"
  class="sidebar-overlay fixed inset-0 md:hidden z-40"
  x-transition:enter="transition-opacity ease-out duration-300"
  x-transition:enter-start="opacity-0"
  x-transition:enter-end="opacity-100"
  x-transition:leave="transition-opacity ease-in duration-200"
  x-transition:leave-start="opacity-100"
  x-transition:leave-end="opacity-0"
></div>

<!-- Sidebar -->
<div 
  class="sidebar fixed left-0 top-0 h-full w-64 z-50 text-white"
  :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
  x-transition:enter="transition-transform ease-out duration-300"
  x-transition:enter-start="-translate-x-full"
  x-transition:enter-end="translate-x-0"
  x-transition:leave="transition-transform ease-in duration-200"
  x-transition:leave-start="translate-x-0"
  x-transition:leave-end="-translate-x-full"
>
  <div class="p-6 border-b border-blue-700/50">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
        <i class="fas fa-graduation-cap text-white"></i>
      </div>
      <div>
        <h2 class="text-xl font-bold">OGISCA</h2>
        <p class="text-blue-200 text-sm">INJS</p>
      </div>
    </div>
  </div>
  
  <nav class="mt-6 space-y-1 px-3">
    <a href="AccueilEtudiant.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/10 transition-all duration-200 group">
      <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center group-hover:bg-white/20 transition-colors">
        <i class="fas fa-home text-blue-200 text-sm"></i>
      </div>
      <span class="font-medium text-sm">Accueil</span>
    </a>
    
    <a href="mes_ec.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/10 transition-all duration-200 group">
      <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center group-hover:bg-white/20 transition-colors">
        <i class="fas fa-book text-blue-200 text-sm"></i>
      </div>
      <span class="font-medium text-sm">Mes EC</span>
    </a>
    
    <a href="test.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/10 transition-all duration-200 group">
      <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center group-hover:bg-white/20 transition-colors">
        <i class="fas fa-tasks text-blue-200 text-sm"></i>
      </div>
      <span class="font-medium text-sm">Mes Requêtes</span>
    </a>
    
    <a href="Profil.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/10 transition-all duration-200 group">
      <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center group-hover:bg-white/20 transition-colors">
        <i class="fas fa-user text-blue-200 text-sm"></i>
      </div>
      <span class="font-medium text-sm">Mon Profil</span>
    </a>
    
    <a href="../logout.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-500/20 transition-all duration-200 group mt-4">
      <div class="w-8 h-8 bg-red-500/20 rounded-lg flex items-center justify-center group-hover:bg-red-500/30 transition-colors">
        <i class="fas fa-sign-out-alt text-red-200 text-sm"></i>
      </div>
      <span class="font-medium text-sm">Déconnexion</span>
    </a>
  </nav>
</div>

<!-- Main content -->
<div class="min-h-screen flex-1 md:ml-64 transition-all duration-300">

  <!-- Topbar -->
  <header class="bg-white/80 backdrop-blur-lg border-b border-gray-200/50 sticky top-0 z-30">
    <div class="flex items-center justify-between h-16 px-4 lg:px-6">
      <!-- Menu Burger -->
      <button 
        @click="sidebarOpen = true"
        class="md:hidden p-2 rounded-xl hover:bg-gray-100 transition-all duration-300"
      >
        <i class="fas fa-bars text-gray-700"></i>
      </button>
      
      <!-- Titre -->
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
          <i class="fas fa-book-open text-white"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-gray-800">Détails du Cours</h1>
          <p class="text-gray-600 text-xs hidden sm:block">Consultation des notes et évaluations</p>
        </div>
      </div>
      
      <!-- User Info -->
      <div class="flex items-center gap-3">
        <div class="text-right hidden sm:block">
          <div class="font-semibold text-gray-800 text-sm"><?=htmlspecialchars($matricule)?></div>
          <div class="text-gray-500 text-xs">Étudiant</div>
        </div>
        <div class="relative">
          <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
            <i class="fas fa-user text-white text-sm"></i>
          </div>
          <div class="absolute -bottom-1 -right-1 w-2 h-2 bg-green-500 rounded-full border border-white"></div>
        </div>
      </div>
    </div>
  </header>

  <!-- Content -->
  <main class="p-4 lg:p-6 max-w-7xl mx-auto">

    <!-- En-tête de page -->
    <div class="card bg-gradient-to-br from-blue-600 to-purple-700 text-white p-6 mb-6 relative overflow-hidden">
      <div class="absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>
      
      <div class="relative z-10">
        <h1 class="text-2xl lg:text-3xl font-bold mb-3"><?=htmlspecialchars($ec['Nom_EC'])?></h1>
        <div class="flex items-center gap-3 flex-wrap">
          <div class="flex items-center gap-2 bg-white/20 px-3 py-1 rounded-xl text-sm">
            <i class="fas fa-tasks text-blue-200"></i>
            <span><?= count($modalites) ?> modalité(s)</span>
          </div>
          <div class="flex items-center gap-2 bg-white/20 px-3 py-1 rounded-xl text-sm">
            <i class="fas fa-chart-bar text-blue-200"></i>
            <span><?= count($notes_valides) ?> note(s)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Cartes de notes compactes -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
      <?php foreach ($modalites as $index => $mod): 
        $mod_up = strtoupper($mod);
        $note = $notes[$mod_up] ?? null;
        $hasNote = $note !== null;
        $percentage = $hasNote ? ($note / 20) * 100 : 0;
      ?>
      <div class="card p-4 hover:scale-105 transform transition-all duration-300">
        <div class="flex justify-between items-start mb-3">
          <div class="flex-1 min-w-0">
            <h3 class="font-bold text-gray-800 text-sm truncate" title="<?=htmlspecialchars($mod_up)?>"><?=htmlspecialchars($mod_up)?></h3>
          </div>
          <?php if ($hasNote): ?>
            <span class="px-2 py-1 rounded-lg text-xs font-bold bg-gradient-to-r <?= badgeColor($note) ?> text-white shadow ml-2 flex-shrink-0">
              <?= substr(appreciation($note), 0, 3) ?>
            </span>
          <?php else: ?>
            <span class="px-2 py-1 rounded-lg text-xs font-bold bg-gray-200 text-gray-700 ml-2 flex-shrink-0">
              N/D
            </span>
          <?php endif; ?>
        </div>
        
        <div class="text-center">
          <?php if ($hasNote): ?>
            <div class="relative inline-block mb-2">
              <svg class="w-16 h-16 transform -rotate-90" viewBox="0 0 36 36">
                <path class="text-gray-200 stroke-current"
                      stroke-width="3"
                      fill="none"
                      d="M18 2.0845
                        a 15.9155 15.9155 0 0 1 0 31.831
                        a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <path class="stroke-current <?= progressColor($note) ?>"
                      stroke-width="3"
                      stroke-dasharray="<?= $percentage ?>, 100"
                      fill="none"
                      d="M18 2.0845
                        a 15.9155 15.9155 0 0 1 0 31.831
                        a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <text x="18" y="22" class="text-sm font-bold fill-current text-gray-800" text-anchor="middle" dy=".3em"><?=htmlspecialchars($note)?></text>
              </svg>
            </div>
            <div class="text-lg font-bold text-gray-800"><?=htmlspecialchars($note)?>/20</div>
          <?php else: ?>
            <div class="w-16 h-16 mx-auto mb-2 bg-gray-100 rounded-full flex items-center justify-center">
              <i class="fas fa-clock text-gray-400"></i>
            </div>
            <div class="text-sm text-gray-400 italic">En attente</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Tableau détaillé -->
    <div class="card bg-white mb-6 overflow-hidden">
      <div class="px-4 lg:px-6 py-4 border-b border-gray-200/50">
        <h3 class="text-lg font-bold gradient-text flex items-center gap-2">
          <i class="fas fa-table"></i>
          Détail des évaluations
        </h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-full">
          <thead class="bg-gradient-to-r from-gray-50 to-gray-100/50">
            <tr>
              <th class="px-4 lg:px-6 py-3 text-left font-semibold text-gray-700 text-sm">Modalité</th>
              <th class="px-4 lg:px-6 py-3 text-center font-semibold text-gray-700 text-sm">Note</th>
              <th class="px-4 lg:px-6 py-3 text-center font-semibold text-gray-700 text-sm">Appréciation</th>
              <th class="px-4 lg:px-6 py-3 text-center font-semibold text-gray-700 text-sm">Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($modalites as $mod): 
              $mod_up = strtoupper($mod);
              $note = $notes[$mod_up] ?? null;
              $hasNote = $note !== null;
            ?>
            <tr class="border-b border-gray-200/50 last:border-b-0 table-row-hover transition-all duration-300">
              <td class="px-4 lg:px-6 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-clipboard-check text-blue-600 text-xs"></i>
                  </div>
                  <div class="min-w-0">
                    <div class="font-semibold text-gray-800 text-sm truncate"><?=htmlspecialchars($mod_up)?></div>
                  </div>
                </div>
              </td>
              <td class="px-4 lg:px-6 py-3 text-center">
                <div class="font-mono text-base font-bold <?= $hasNote ? 'text-gray-800' : 'text-gray-400 italic' ?>">
                  <?= $hasNote ? htmlspecialchars($note) : 'N/D' ?>
                </div>
              </td>
              <td class="px-4 lg:px-6 py-3 text-center">
                <?php if ($hasNote): ?>
                  <span class="px-3 py-1 rounded-lg text-xs font-bold bg-gradient-to-r <?= badgeColor($note) ?> text-white shadow inline-block min-w-[80px]">
                    <?= appreciation($note) ?>
                  </span>
                <?php else: ?>
                  <span class="text-gray-400 italic text-sm">-</span>
                <?php endif; ?>
              </td>
              <td class="px-4 lg:px-6 py-3 text-center">
                <?php if ($hasNote): ?>
                  <span class="px-2 py-1 rounded-lg text-xs font-bold bg-green-100 text-green-800 border border-green-200">
                    <i class="fas fa-check mr-1"></i>Validé
                  </span>
                <?php else: ?>
                  <span class="px-2 py-1 rounded-lg text-xs font-bold bg-yellow-100 text-yellow-800 border border-yellow-200">
                    <i class="fas fa-clock mr-1"></i>En attente
                  </span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Note finale -->
    <?php if ($note_finale !== null): ?>
      <div 
        x-data="{ show: false }" 
        x-init="setTimeout(() => show = true, 300)" 
        x-show="show" 
        x-transition:enter="transition-all ease-out duration-500"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        class="card p-6 text-center max-w-md mx-auto bg-gradient-to-br <?= badgeColor($note_finale) ?> text-white hover:scale-105 transform transition duration-300 cursor-pointer"
      >
        <div class="text-xl font-bold mb-3 opacity-90">Note Finale</div>
        <div class="text-5xl font-black mb-3"><?= number_format($note_finale, 2) ?></div>
        <div class="text-lg font-bold mb-2 opacity-90">/ 20</div>
        <div class="text-base font-semibold opacity-90"><?= appreciation($note_finale) ?></div>
      </div>
    <?php else: ?>
      <div class="card p-6 text-center max-w-md mx-auto bg-gradient-to-br from-gray-500 to-gray-600 text-white">
        <i class="fas fa-chart-line text-3xl mb-4 opacity-50"></i>
        <div class="text-lg font-bold mb-2">Note finale non disponible</div>
        <p class="text-gray-200 opacity-80 text-sm">Calculée lorsque toutes les évaluations seront saisies.</p>
      </div>
    <?php endif; ?>

    <!-- Message si aucune note -->
    <?php if (empty($notes)): ?>
      <div class="card bg-white p-6 text-center max-w-md mx-auto">
        <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-clipboard-list text-2xl text-gray-400"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Aucune note enregistrée</h3>
        <p class="text-gray-500 text-sm">Les évaluations n'ont pas encore été saisies.</p>
      </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>