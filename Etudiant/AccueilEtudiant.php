<?php
session_start();
require_once '../include/db.php';

// Vérification de la session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$idUser = $_SESSION['user_id'];
$etudiant = null;
$id_niveau = null;

$sql = "
    SELECT e.id, e.niveau_id, e.nom, e.prenom, n.nom AS nom_niveau
    FROM etudiants e
    LEFT JOIN niveau n ON e.niveau_id = n.id
    WHERE e.user_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idUser);

if ($stmt->execute()) {
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $etudiant = $row;
        $id_niveau = $row['niveau_id'];
    } else {
        echo "Aucun étudiant lié à cet utilisateur.";
        exit;
    }
} else {
    echo "Erreur lors de la récupération des données.";
    exit;
}
$stmt->close();

// Récupérer les EC avec et sans notes séparément
$ecs_avec_notes = [];
$ecs_sans_notes = [];

if ($id_niveau) {
    $sqlEC = "
        SELECT ec.ID_EC, ec.Nom_EC, ec.Modalites_Controle,
               CASE WHEN n.valeur IS NULL THEN 0 ELSE 1 END AS has_note,
               COUNT(DISTINCT n.id) as note_count
        FROM element_constitutif ec
        LEFT JOIN notes n ON n.ID_EC = ec.ID_EC AND n.etudiant_id = ?
        WHERE ec.id_niveau = ?
        GROUP BY ec.ID_EC, ec.Nom_EC, ec.Modalites_Controle
        ORDER BY has_note DESC, ec.Nom_EC ASC
    ";
    $stmtEC = $conn->prepare($sqlEC);
    $stmtEC->bind_param("ii", $etudiant['id'], $id_niveau);

    if ($stmtEC->execute()) {
        $result = $stmtEC->get_result();
        while ($ec = $result->fetch_assoc()) {
            if ($ec['has_note'] == 1) {
                $ecs_avec_notes[] = $ec;
            } else {
                $ecs_sans_notes[] = $ec;
            }
        }
        $stmtEC->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Accueil Étudiant - OGISCA</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#4f46e5',
            'primary-light': '#6366f1',
            'primary-dark': '#4338ca',
            secondary: '#10b981',
            accent: '#f59e0b',
            danger: '#ef4444',
          },
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif'],
          },
          screens: {
            'xs': '475px',
            '3xl': '1920px',
          }
        }
      }
    }
  </script>
  <style>
    :root {
      --primary: #4f46e5;
      --primary-light: #6366f1;
      --primary-dark: '#4338ca';
      --secondary: '#10b981';
    }

    * {
      -webkit-tap-highlight-color: transparent;
    }

    .fade-in {
      animation: fadeIn 0.6s ease forwards;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .slide-in-left {
      animation: slideInLeft 0.3s ease forwards;
    }

    @keyframes slideInLeft {
      from { transform: translateX(-100%); }
      to { transform: translateX(0); }
    }

    .card-hover {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-hover:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    /* Mobile menu styles */
    .mobile-menu {
      transform: translateX(-100%);
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .mobile-menu.open {
      transform: translateX(0);
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

    /* Enhanced card styles */
    .ec-card {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 1.5rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
      height: 100%;
    }

    .ec-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
    }

    .ec-card.available::before {
      background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
    }

    .ec-card.pending::before {
      background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
    }

    .ec-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
      border-color: var(--primary-light);
    }

    .ec-card.pending {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-color: #dee2e6;
    }

    .ec-card.pending:hover {
      transform: none;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .status-badge {
      padding: 0.5rem 1rem;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .status-available {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }

    .status-pending {
      background: #fef3c7;
      color: #92400e;
      border: 1px solid #fcd34d;
    }

    .ec-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }

    .ec-icon-available {
      background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
      color: white;
    }

    .ec-icon-pending {
      background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
      color: white;
    }

    /* Grid layouts */
    .grid-cards {
      display: grid;
      gap: 1.5rem;
    }

    @media (max-width: 640px) {
      .grid-cards {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      
      .ec-card {
        padding: 1.25rem;
      }
    }

    @media (min-width: 641px) and (max-width: 768px) {
      .grid-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
      }
    }

    @media (min-width: 769px) and (max-width: 1024px) {
      .grid-cards {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
      }
    }

    @media (min-width: 1025px) and (max-width: 1280px) {
      .grid-cards {
        grid-template-columns: repeat(4, 1fr);
        gap: 1.75rem;
      }
    }

    @media (min-width: 1281px) {
      .grid-cards {
        grid-template-columns: repeat(4, 1fr);
        gap: 2rem;
      }
    }

    /* Section headers */
    .section-header {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .section-title {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 1.25rem;
      font-weight: 600;
      color: #1e293b;
    }

    .section-count {
      background: var(--primary);
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 500;
    }

    /* Touch-friendly buttons */
    .touch-button {
      min-height: 44px;
      min-width: 44px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Improved scrolling on mobile */
    .scroll-mobile {
      -webkit-overflow-scrolling: touch;
    }

    /* Text size adjustments for mobile */
    @media (max-width: 640px) {
      .responsive-text {
        font-size: 0.875rem;
        line-height: 1.25rem;
      }
      
      .responsive-heading {
        font-size: 1.5rem;
        line-height: 2rem;
      }
    }

    /* Progress bar styles */
    .progress-bar {
      height: 6px;
      border-radius: 3px;
      background: #e2e8f0;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      border-radius: 3px;
      transition: width 0.3s ease;
    }

    .progress-available {
      background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
    }

    .progress-pending {
      background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
    }

    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 3rem 2rem;
      background: white;
      border: 2px dashed #e2e8f0;
      border-radius: 16px;
    }
  </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col font-sans text-gray-800 antialiased">

  <!-- Mobile Menu Button -->
  <button id="mobileMenuButton" class="lg:hidden fixed top-4 left-4 z-50 touch-button bg-primary text-white rounded-full p-3 shadow-lg">
    <i class="fas fa-bars text-lg"></i>
  </button>

  <!-- Backdrop for mobile menu -->
  <div id="backdrop" class="backdrop fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

  <!-- Sidebar - Mobile -->
  <aside id="mobileSidebar" class="mobile-menu fixed inset-y-0 left-0 w-80 bg-primary text-white flex flex-col shadow-2xl z-50 lg:hidden">
    <div class="flex items-center justify-between p-6 border-b border-primary-light">
      <div class="text-2xl font-bold">OGISCA</div>
      <button id="closeMobileMenu" class="touch-button text-white">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    <nav class="flex flex-col flex-1 p-4 space-y-2 overflow-y-auto scroll-mobile">
      <a href="AccueilEtudiant.php" class="flex items-center px-4 py-4 rounded-lg bg-primary-light text-white transition-colors touch-button">
        <i class="fas fa-home w-6 mr-3 text-center"></i>
        <span class="font-medium">Accueil</span>
      </a>
      <a href="MesCours.php" class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button">
        <i class="fas fa-book w-6 mr-3 text-center"></i>
        <span class="font-medium">Mes Cours</span>
      </a>
      <a href="test.php" class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button">
        <i class="fas fa-tasks w-6 mr-3 text-center"></i>
        <span class="font-medium">Mes Requêtes</span>
      </a>
      <a href="Profil.php" class="flex items-center px-4 py-4 rounded-lg hover:bg-primary-light transition-colors touch-button">
        <i class="fas fa-user w-6 mr-3 text-center"></i>
        <span class="font-medium">Mon Profil</span>
      </a>
      <div class="mt-auto pt-4 border-t border-primary-light">
        <a href="logout.php" class="flex items-center px-4 py-4 rounded-lg hover:bg-red-600 transition-colors touch-button">
          <i class="fas fa-sign-out-alt w-6 mr-3 text-center"></i>
          <span class="font-medium">Déconnexion</span>
        </a>
      </div>
    </nav>
  </aside>

  <!-- Sidebar - Desktop -->
  <aside class="hidden lg:flex fixed inset-y-0 left-0 w-64 bg-primary text-white flex-col shadow-xl">
    <div class="px-6 py-5 text-xl font-bold border-b border-primary-light flex items-center">
      <img src="../admin/logo simple sans fond.png" class="w-8 h-8 mr-3 rounded" alt="logo" />
      OGISCA - INJS
    </div>
    <nav class="flex flex-col flex-1 p-4 space-y-2 mt-4">
      <a href="AccueilEtudiant.php" class="flex items-center px-4 py-3 rounded-lg bg-primary-light text-white transition-colors nav-item">
        <i class="fas fa-home w-6 mr-3 text-center"></i>
        <span class="font-medium">Accueil</span>
      </a>
      <a href="MesCours.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-primary-light transition-colors nav-item">
        <i class="fas fa-book w-6 mr-3 text-center"></i>
        <span class="font-medium">Mes Cours</span>
      </a>
      <a href="test.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-primary-light transition-colors nav-item">
        <i class="fas fa-tasks w-6 mr-3 text-center"></i>
        <span class="font-medium">Mes Requêtes</span>
      </a>
      <a href="Profil.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-primary-light transition-colors nav-item">
        <i class="fas fa-user w-6 mr-3 text-center"></i>
        <span class="font-medium">Mon Profil</span>
      </a>
      <div class="mt-auto pt-4 border-t border-primary-light">
        <a href="logout.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-red-600 transition-colors nav-item">
          <i class="fas fa-sign-out-alt w-6 mr-3 text-center"></i>
          <span class="font-medium">Déconnexion</span>
        </a>
      </div>
    </nav>
  </aside>

  <!-- Main content wrapper -->
  <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">

    <!-- Topbar -->
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 lg:px-8 sticky top-0 z-30 transition-all duration-300" id="topbar">
      <div class="flex items-center space-x-4">
        <div class="text-gray-700 responsive-text">
          <span class="font-semibold"><?= htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) ?></span>
          <span class="text-gray-500 text-sm hidden xs:inline"> - <?= htmlspecialchars($etudiant['nom_niveau'] ?? 'Non défini') ?></span>
        </div>
      </div>
      <div class="flex items-center space-x-3">
        <!-- Mobile profile indicator -->
        <div class="lg:hidden flex items-center space-x-2">
          <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
            <span class="text-white text-sm font-bold"><?= strtoupper(substr($etudiant['prenom'], 0, 1)) ?></span>
          </div>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main class="flex-grow p-4 lg:p-8 scroll-mobile">
      <!-- Welcome Section -->
      <section class="mb-8 fade-in">
        <div class="bg-gradient-to-r from-primary to-primary-light rounded-2xl p-6 lg:p-8 text-white shadow-lg">
          <h1 class="responsive-heading font-bold mb-2">Bienvenue, <?= htmlspecialchars($etudiant['prenom']) ?>! 👋</h1>
          <p class="text-blue-100 opacity-90 responsive-text">
            Niveau : <strong><?= htmlspecialchars($etudiant['nom_niveau'] ?? 'Non défini') ?></strong>
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm">Étudiant</span>
            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm">Connecté</span>
          </div>
        </div>
      </section>

      <!-- EC avec Notes Section -->
      <?php if (!empty($ecs_avec_notes)): ?>
      <section class="mb-12 fade-in">
        <div class="section-header">
          <div class="flex items-center justify-between">
            <div class="section-title">
              <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600"></i>
              </div>
              <div>
                <h2 class="text-xl font-semibold text-gray-800">Éléments Constitutifs avec Notes</h2>
                <p class="text-gray-600 text-sm mt-1">Consultez vos notes disponibles</p>
              </div>
            </div>
            <span class="section-count">
              <?= count($ecs_avec_notes) ?> EC
            </span>
          </div>
        </div>

        <div class="grid-cards">
          <?php foreach($ecs_avec_notes as $ec): ?>
          <div class="ec-card available card-hover group">
            <a href="detail_ec.php?id=<?= $ec['ID_EC'] ?>" class="absolute inset-0 z-10" aria-label="<?= htmlspecialchars($ec['Nom_EC']) ?>"></a>
            
            <!-- Header -->
            <div class="flex items-start justify-between mb-4">
              <div class="flex items-center space-x-3">
                <div class="ec-icon ec-icon-available">
                  <i class="fas fa-book"></i>
                </div>
                <div>
                  <h3 class="font-bold text-gray-800 text-lg leading-tight"><?= htmlspecialchars($ec['Nom_EC']) ?></h3>
                  <p class="text-gray-500 text-sm mt-1">EC-<?= htmlspecialchars($ec['ID_EC']) ?></p>
                </div>
              </div>
            </div>

            <!-- Status Badge -->
            <div class="mb-4">
              <span class="status-badge status-available">
                <i class="fas fa-check-circle"></i>
                Notes disponibles
              </span>
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700">Progression</span>
                <span class="text-sm font-semibold text-green-600">
                  Complet
                </span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill progress-available" style="width: 100%"></div>
              </div>
            </div>

            <!-- Details -->
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 flex items-center">
                  <i class="fas fa-tasks mr-2 text-gray-400"></i>
                  Modalités
                </span>
                <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ec['Modalites_Controle']) ?></span>
              </div>
              
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 flex items-center">
                  <i class="fas fa-sticky-note mr-2 text-gray-400"></i>
                  Notes enregistrées
                </span>
                <span class="text-sm font-semibold text-green-600">
                  <?= $ec['note_count'] ?>
                </span>
              </div>
            </div>

            <!-- Action Button -->
            <div class="mt-6 pt-4 border-t border-gray-200">
              <div class="flex items-center justify-between">
                <span class="text-sm text-green-600 font-medium flex items-center">
                  <i class="fas fa-eye mr-2"></i>
                  Consulter les notes
                </span>
                <i class="fas fa-arrow-right text-green-600 transform group-hover:translate-x-1 transition-transform"></i>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- EC sans Notes Section -->
      <?php if (!empty($ecs_sans_notes)): ?>
      <section class="mb-12 fade-in">
        <div class="section-header">
          <div class="flex items-center justify-between">
            <div class="section-title">
              <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-clock text-yellow-600"></i>
              </div>
              <div>
                <h2 class="text-xl font-semibold text-gray-800">Éléments Constitutifs en Attente</h2>
                <p class="text-gray-600 text-sm mt-1">Notes non encore disponibles</p>
              </div>
            </div>
            <span class="section-count">
              <?= count($ecs_sans_notes) ?> EC
            </span>
          </div>
        </div>

        <div class="grid-cards">
          <?php foreach($ecs_sans_notes as $ec): ?>
          <div class="ec-card pending">
            <!-- Header -->
            <div class="flex items-start justify-between mb-4">
              <div class="flex items-center space-x-3">
                <div class="ec-icon ec-icon-pending">
                  <i class="fas fa-book"></i>
                </div>
                <div>
                  <h3 class="font-bold text-gray-800 text-lg leading-tight"><?= htmlspecialchars($ec['Nom_EC']) ?></h3>
                  <p class="text-gray-500 text-sm mt-1">EC-<?= htmlspecialchars($ec['ID_EC']) ?></p>
                </div>
              </div>
            </div>

            <!-- Status Badge -->
            <div class="mb-4">
              <span class="status-badge status-pending">
                <i class="fas fa-clock"></i>
                En attente de notes
              </span>
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700">Progression</span>
                <span class="text-sm font-semibold text-yellow-600">
                  En cours
                </span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill progress-pending" style="width: 30%"></div>
              </div>
            </div>

            <!-- Details -->
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 flex items-center">
                  <i class="fas fa-tasks mr-2 text-gray-400"></i>
                  Modalités
                </span>
                <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ec['Modalites_Controle']) ?></span>
              </div>
              
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 flex items-center">
                  <i class="fas fa-sticky-note mr-2 text-gray-400"></i>
                  Notes enregistrées
                </span>
                <span class="text-sm font-semibold text-yellow-600">
                  0
                </span>
              </div>
            </div>

            <!-- Action Button -->
            <div class="mt-6 pt-4 border-t border-gray-200">
              <div class="flex items-center justify-between">
                <span class="text-sm text-yellow-600 font-medium flex items-center">
                  <i class="fas fa-clock mr-2"></i>
                  Notes à venir
                </span>
                <i class="fas fa-hourglass-half text-yellow-600"></i>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- Empty State when no ECs -->
      <?php if (empty($ecs_avec_notes) && empty($ecs_sans_notes)): ?>
      <section class="fade-in">
        <div class="empty-state">
          <i class="fas fa-book text-gray-300 text-6xl mb-4"></i>
          <h3 class="text-lg font-semibold text-gray-600 mb-2">Aucun élément constitutif</h3>
          <p class="text-gray-500 max-w-md mx-auto">Aucun élément constitutif trouvé pour votre niveau pour le moment.</p>
        </div>
      </section>
      <?php endif; ?>

      <!-- Quick Stats -->
      <section class="mt-12 hidden md:block fade-in">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="bg-white rounded-xl p-6 shadow-lg border border-gray-200">
            <div class="flex items-center">
              <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-book text-blue-600 text-xl"></i>
              </div>
              <div>
                <p class="text-2xl font-bold text-gray-800"><?= count($ecs_avec_notes) + count($ecs_sans_notes) ?></p>
                <p class="text-gray-600 text-sm">Total EC</p>
              </div>
            </div>
          </div>
          
          <div class="bg-white rounded-xl p-6 shadow-lg border border-gray-200">
            <div class="flex items-center">
              <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
              </div>
              <div>
                <p class="text-2xl font-bold text-gray-800"><?= count($ecs_avec_notes) ?></p>
                <p class="text-gray-600 text-sm">Avec notes</p>
              </div>
            </div>
          </div>
          
          <div class="bg-white rounded-xl p-6 shadow-lg border border-gray-200">
            <div class="flex items-center">
              <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-graduation-cap text-purple-600 text-xl"></i>
              </div>
              <div>
                <p class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($etudiant['nom_niveau'] ?? '-') ?></p>
                <p class="text-gray-600 text-sm">Votre niveau</p>
              </div>
            </div>
          </div>
        </div>
      </section>

    </main>
    
    <!-- Footer -->
    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 bg-white">
      <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
      <p class="text-xs mt-1 text-gray-400">Optimisé pour tous les appareils</p>
    </footer>
  </div>

  <script>
    // Mobile menu functionality
    const mobileMenuButton = document.getElementById('mobileMenuButton');
    const closeMobileMenu = document.getElementById('closeMobileMenu');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const backdrop = document.getElementById('backdrop');

    function openMobileMenu() {
      mobileSidebar.classList.add('open');
      backdrop.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeMobileMenuFunc() {
      mobileSidebar.classList.remove('open');
      backdrop.classList.remove('active');
      document.body.style.overflow = '';
    }

    mobileMenuButton.addEventListener('click', openMobileMenu);
    closeMobileMenu.addEventListener('click', closeMobileMenuFunc);
    backdrop.addEventListener('click', closeMobileMenuFunc);

    // Close menu on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeMobileMenuFunc();
      }
    });

    // Topbar shadow + blur au scroll
    window.addEventListener('scroll', () => {
      const topbar = document.getElementById('topbar');
      if (window.scrollY > 10) {
        topbar.classList.add('shadow-lg', 'bg-white/95', 'backdrop-blur-sm');
      } else {
        topbar.classList.remove('shadow-lg', 'bg-white/95', 'backdrop-blur-sm');
      }
    });

    // Touch device detection
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    if (isTouchDevice) {
      document.body.classList.add('touch-device');
    }

    // Prevent zoom on double tap (iOS)
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function (event) {
      const now = (new Date()).getTime();
      if (now - lastTouchEnd <= 300) {
        event.preventDefault();
      }
      lastTouchEnd = now;
    }, false);

    // Improved responsive behavior
    function handleResize() {
      const width = window.innerWidth;
      if (width >= 1024) {
        closeMobileMenuFunc();
      }
    }

    window.addEventListener('resize', handleResize);

    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
      handleResize();
      
      // Add loading animation to cards
      const cards = document.querySelectorAll('.fade-in');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });

      // Add hover effects for desktop
      if (!isTouchDevice) {
        const ecCards = document.querySelectorAll('.ec-card.available');
        ecCards.forEach(card => {
          card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-6px)';
          });
          
          card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
          });
        });
      }
    });
  </script>

</body>
</html>