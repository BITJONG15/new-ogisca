<?php
session_start();
require_once("../include/db.php");

// 🔐 Protection d'accès
if (!isset($_SESSION['matricule'])) {
    header("Location: ../login.php");
    exit();
}

$matricule = $_SESSION['matricule'];

// Vérifier si l'utilisateur est déjà identifié 
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    $stmt = $conn->prepare("SELECT id FROM enseignants WHERE matricule = ?");
    $stmt->bind_param("s", $matricule);
    $stmt->execute();
    $stmt->bind_result($userId);
    $stmt->fetch();
    $stmt->close();
    $_SESSION['user_id'] = $userId;
}

// Récupérer données enseignant
$stmt = $conn->prepare("SELECT nom, prenom, email, telephone, specialite, domaine_expertise, photo FROM enseignants WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($nom, $prenom, $email, $telephone, $specialite, $domaine, $photo);
$stmt->fetch();
$stmt->close();

// Traitement upload photo
$erreur = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $erreur = "Format non autorisé (jpg, png, gif seulement).";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $erreur = "Fichier trop volumineux (max 2MB).";
        } else {
            $newName = "photo_enseignant_{$userId}." . $ext;
            $uploadDir = "../uploads/photos/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $destination = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Mettre à jour la BD
                $stmt = $conn->prepare("UPDATE enseignants SET photo = ? WHERE id = ?");
                $stmt->bind_param("si", $newName, $userId);
                if ($stmt->execute()) {
                    $success = "Photo mise à jour avec succès.";
                    $photo = $newName;
                } else {
                    $erreur = "Erreur lors de la mise à jour en base.";
                }
                $stmt->close();
            } else {
                $erreur = "Erreur lors de l'upload.";
            }
        }
    } else {
        $erreur = "Erreur fichier uploadé.";
    }
}

// S'assurer que les variables sont définies pour éviter les warnings
$nom = $nom ?? '';
$prenom = $prenom ?? '';
$email = $email ?? '';
$telephone = $telephone ?? '';
$specialite = $specialite ?? '';
$domaine = $domaine ?? '';
$photo = $photo ?? '';

$photo_url = !empty($photo) ? "../uploads/photos/" . $photo : 'https://www.svgrepo.com/show/382106/user-circle.svg';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil | OGISCA</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
        grid-template-columns: repeat(4, 1fr);
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

.status-info {
    background: #dbeafe;
    color: #1e40af;
}

.status-pending {
    background: #f3f4f6;
    color: #6b7280;
}

.requete-invalide {
    background-color: #fef2f2;
    opacity: 0.7;
}

.requete-invalide:hover {
    background-color: #fee2e2;
}

.table-row-hover:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    transform: translateY(-1px);
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

/* Styles spécifiques pour la page profil */
.profile-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin: 0 2rem 2rem 2rem;
}

@media (min-width: 1024px) {
    .profile-grid {
        grid-template-columns: 1fr 2fr;
    }
    
    .profile-grid-full {
        grid-column: 1 / -1;
    }
}

.profile-table {
    width: 100%;
    border-collapse: collapse;
}

.profile-table th {
    text-align: left;
    padding: 1rem;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    width: 200px;
}

.profile-table td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    color: #4b5563;
}

.file-input {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 2rem;
    transition: all 0.3s ease;
    text-align: center;
}

.file-input:hover {
    border-color: var(--primary);
    background-color: #f8faff;
}

.profile-photo {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #e5e7eb;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Animation pour les cartes de profil */
.fade-slide-up {
    opacity: 0;
    transform: translateY(10px);
    animation: fadeSlideUp 0.6s forwards;
}

.fade-slide-up.delay-1 { animation-delay: 0.1s; }
.fade-slide-up.delay-2 { animation-delay: 0.2s; }
.fade-slide-up.delay-3 { animation-delay: 0.3s; }
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
                    <span>DASHBOARD</span>
                </a>
            </li>
            <li class="nav-item">
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
            <li class="nav-item active">
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
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Mon Profil</h1>
                    <p class="text-blue-100 opacity-90 text-sm md:text-base">Gérez vos informations personnelles</p>
                </div>
                <div class="text-white text-sm mt-3 md:mt-0">
                    <i class="far fa-calendar-alt mr-2"></i>
                    <?php echo date('d/m/Y'); ?>
                </div>
            </div>
        </div>

        <!-- Flash Message -->
        <?php if ($erreur): ?>
            <div class="card p-6 bg-red-100 border border-red-400 text-red-700">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($erreur) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="card p-6 bg-green-100 border border-green-400 text-green-700">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($success) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Profile Grid -->
        <div class="profile-grid">
            <!-- Photo de profil -->
            <div class="card fade-slide-up delay-1">
                <div class="p-6 text-center">
                    <div class="mb-6">
                        <img src="<?= $photo_url ?>" 
                             alt="Photo de profil" 
                             class="profile-photo mx-auto" />
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2"><?= htmlspecialchars($prenom) ?> <?= htmlspecialchars($nom) ?></h3>
                    <p class="text-gray-600 mb-4"><?= htmlspecialchars($specialite) ?></p>
                    <div class="bg-blue-50 rounded-lg p-3 inline-block">
                        <p class="text-blue-700 text-sm font-medium">Matricule: <?= htmlspecialchars($matricule) ?></p>
                    </div>
                </div>
            </div>

            <!-- Informations personnelles -->
            <div class="card fade-slide-up delay-2">
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-user-circle mr-3 text-blue-500"></i>
                        Informations Personnelles
                    </h3>
                    
                    <table class="profile-table">
                        <tr>
                            <th>Nom :</th>
                            <td class="font-medium"><?= htmlspecialchars($nom) ?></td>
                        </tr>
                        <tr>
                            <th>Prénom :</th>
                            <td class="font-medium"><?= htmlspecialchars($prenom) ?></td>
                        </tr>
                        <tr>
                            <th>Email :</th>
                            <td class="flex items-center">
                                <i class="fas fa-envelope mr-2 text-gray-400"></i>
                                <?= htmlspecialchars($email) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Téléphone :</th>
                            <td class="flex items-center">
                                <i class="fas fa-phone mr-2 text-gray-400"></i>
                                <?= htmlspecialchars($telephone) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Spécialité :</th>
                            <td class="flex items-center">
                                <i class="fas fa-graduation-cap mr-2 text-gray-400"></i>
                                <?= htmlspecialchars($specialite) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Domaine expertise :</th>
                            <td class="flex items-center">
                                <i class="fas fa-briefcase mr-2 text-gray-400"></i>
                                <?= htmlspecialchars($domaine) ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Modification photo -->
            <div class="card profile-grid-full fade-slide-up delay-3">
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-camera mr-3 text-blue-500"></i>
                        Photo de Profil
                    </h3>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div class="file-input">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                            <p class="text-gray-600 mb-2">Glissez-déposez votre photo ou cliquez pour parcourir</p>
                            <p class="text-sm text-gray-500 mb-4">Formats acceptés: JPG, PNG, GIF (max 2MB)</p>
                            <input type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png,.gif" required 
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                        </div>
                        <button type="submit" class="btn-primary flex items-center justify-center w-full">
                            <i class="fas fa-upload mr-2"></i>
                            Mettre à jour la photo
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </main>

    <footer class="py-4 px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<script>
// Gestion du drag and drop pour l'upload
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('photo');
    const fileInputContainer = fileInput?.parentElement;

    if (fileInput && fileInputContainer) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileInputContainer.classList.add('border-blue-500', 'bg-blue-50');
            }
        });

        // Drag and drop functionality
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
});
</script>

</body>
</html>