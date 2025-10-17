<?php
// requete_traitees.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Vérification session enseignant
if (!isset($_SESSION['matricule']) || !isset($_SESSION['user_id']) || !str_starts_with($_SESSION['matricule'], "EN")) {
    header("Location: ../login.php");
    exit();
}

// Connexion DB
if (file_exists(__DIR__ . '/include/db.php')) {
    require_once __DIR__ . '/include/db.php';
} else {
    $conn = new mysqli("localhost", "root", "", "gestion_academique");
    if ($conn->connect_error) die("Erreur DB : " . $conn->connect_error);
}

// Récupération du nom et prénom enseignant connecté
$enseignant_id = intval($_SESSION['user_id']);
$enseignant_nom = "";
$enseignant_prenom = "";

$stmt = $conn->prepare("SELECT nom, prenom FROM enseignants WHERE id = ?");
$stmt->bind_param("i", $enseignant_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if ($res) {
    $enseignant_nom = $res['nom'];
    $enseignant_prenom = $res['prenom'];
}
$stmt->close();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// ---------------------------
// TRAITEMENT ACTIONS POST
// ---------------------------
$flash = null;
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrf) {
        $flash = "Erreur de sécurité. Veuillez réessayer.";
        $flash_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'rejeter' && isset($_POST['requete_id'])) {
            $requete_id = intval($_POST['requete_id']);
            $upd = $conn->prepare("UPDATE requetes SET statut = 'refusée' WHERE id = ?");
            $upd->bind_param("i", $requete_id);
            if ($upd->execute()) {
                $flash = "Requête refusée avec succès.";
            } else {
                $flash = "Erreur lors du refus de la requête.";
                $flash_type = 'error';
            }
            $upd->close();
        }

        if ($action === 'resoudre' && isset($_POST['requete_id'], $_POST['etudiant_id'], $_POST['ID_EC'])) {
            $requete_id = intval($_POST['requete_id']);
            $etudiant_id = intval($_POST['etudiant_id']);
            $ID_EC = trim($_POST['ID_EC']);
            $commentaire = trim($_POST['commentaire'] ?? '');
            
            // Récupérer les notes pour chaque modalité
            $notes_modalites = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'note_') === 0 && $value !== '') {
                    $modalite = substr($key, 5);
                    $notes_modalites[$modalite] = floatval($value);
                }
            }

            if (empty($ID_EC)) {
                $flash = "Erreur : L'élément constitutif n'est pas spécifié dans la requête.";
                $flash_type = 'error';
            } else {
                $check_ec = $conn->prepare("SELECT Modalites_Controle FROM element_constitutif WHERE ID_EC = ?");
                $check_ec->bind_param("s", $ID_EC);
                $check_ec->execute();
                $ec_data = $check_ec->get_result()->fetch_assoc();
                $check_ec->close();

                if (!$ec_data) {
                    $flash = "Erreur : L'élément constitutif '$ID_EC' n'existe pas dans la base de données.";
                    $flash_type = 'error';
                } else {
                    $modalites_disponibles = explode(',', $ec_data['Modalites_Controle']);
                    $modalites_disponibles = array_map('trim', $modalites_disponibles);
                    
                    $validation_ok = true;
                    foreach ($notes_modalites as $modalite => $note) {
                        if ($note < 0 || $note > 20) {
                            $flash = "La note $modalite doit être entre 0 et 20.";
                            $flash_type = 'error';
                            $validation_ok = false;
                            break;
                        }
                    }

                    if ($validation_ok) {
                        function upsert_note($conn, $etudiant_id, $ID_EC, $valeur, $modalite, $enseignant_id) {
                            if ($valeur === null) return true;
                            
                            $sel = $conn->prepare("SELECT id FROM notes WHERE etudiant_id = ? AND ID_EC = ? AND modalite = ?");
                            $sel->bind_param("iss", $etudiant_id, $ID_EC, $modalite);
                            $sel->execute();
                            $res = $sel->get_result();
                            
                            if ($row = $res->fetch_assoc()) {
                                $id = $row['id'];
                                $upd = $conn->prepare("UPDATE notes SET valeur = ?, enseignant_id = ?, date_ajout = NOW() WHERE id = ?");
                                $upd->bind_param("dii", $valeur, $enseignant_id, $id);
                                $result = $upd->execute();
                                $upd->close();
                            } else {
                                $ins = $conn->prepare("INSERT INTO notes (etudiant_id, ID_EC, enseignant_id, valeur, modalite, date_ajout) VALUES (?, ?, ?, ?, ?, NOW())");
                                $ins->bind_param("isids", $etudiant_id, $ID_EC, $enseignant_id, $valeur, $modalite);
                                $result = $ins->execute();
                                $ins->close();
                            }
                            $sel->close();
                            return $result;
                        }

                        $conn->begin_transaction();
                        try {
                            $success = true;
                            
                            foreach ($notes_modalites as $modalite => $note) {
                                $success = $success && upsert_note($conn, $etudiant_id, $ID_EC, $note, $modalite, $enseignant_id);
                            }
                            
                            $notes_pour_moyenne = [];
                            foreach ($modalites_disponibles as $modalite) {
                                if ($modalite !== 'MOY' && isset($notes_modalites[$modalite])) {
                                    $notes_pour_moyenne[] = $notes_modalites[$modalite];
                                }
                            }
                            
                            if (count($notes_pour_moyenne) > 0) {
                                $moyenne = array_sum($notes_pour_moyenne) / count($notes_pour_moyenne);
                                $success = $success && upsert_note($conn, $etudiant_id, $ID_EC, $moyenne, 'MOY', $enseignant_id);
                            }

                            if ($commentaire && $success) {
                                $updC = $conn->prepare("UPDATE requetes SET contenu = CONCAT(COALESCE(contenu, ''), '\n\n--- COMMENTAIRE ENSEIGNANT ---\n', ?) WHERE id = ?");
                                $updC->bind_param("si", $commentaire, $requete_id);
                                $success = $success && $updC->execute();
                                $updC->close();
                            }

                            if ($success) {
                                $updS = $conn->prepare("UPDATE requetes SET statut = 'validée' WHERE id = ?");
                                $updS->bind_param("i", $requete_id);
                                $success = $success && $updS->execute();
                                $updS->close();
                            }

                            if ($success) {
                                $conn->commit();
                                $flash = "Requête validée et notes mises à jour avec succès.";
                            } else {
                                throw new Exception("Erreur lors de la mise à jour des données");
                            }
                        } catch (Exception $e) {
                            $conn->rollback();
                            $flash = "Erreur : " . $e->getMessage();
                            $flash_type = 'error';
                        }
                    }
                }
            }
        }
    }
}

// ---------------------------
// RÉCUPÉRATION DES REQUÊTES
// ---------------------------
$sql = "
SELECT 
    r.id, r.motif, r.date_envoi, r.statut, 
    COALESCE(r.ID_EC, r.element_constitutif) AS ID_EC, 
    r.contenu, r.piece_jointe,
    e.nom AS etu_nom, e.prenom AS etu_prenom, e.matricule,
    ec.Nom_EC AS matiere, r.etudiant_id,
    r.element_constitutif AS element_constitutif_original,
    ec.Modalites_Controle
FROM requetes r
LEFT JOIN etudiants e ON r.etudiant_id = e.id
LEFT JOIN element_constitutif ec ON COALESCE(r.ID_EC, r.element_constitutif) = ec.ID_EC
WHERE r.statut = 'traitée'
  AND r.professeur_nom = ?
  AND r.professeur_prenom = ?
ORDER BY r.date_envoi DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $enseignant_nom, $enseignant_prenom);
$stmt->execute();
$requetes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fonctions helper
function safe_html($value, $default = '') {
    if ($value === null || $value === '') {
        return htmlspecialchars($default);
    }
    return htmlspecialchars($value);
}

function can_process_request($requete) {
    return !empty($requete['ID_EC']);
}

function get_matiere_display($requete) {
    if (!empty($requete['matiere'])) {
        return $requete['matiere'];
    }
    if (!empty($requete['element_constitutif_original'])) {
        return $requete['element_constitutif_original'];
    }
    if (!empty($requete['ID_EC'])) {
        return $requete['ID_EC'];
    }
    return 'Non spécifié';
}

function get_modalites_display($requete) {
    if (!empty($requete['Modalites_Controle'])) {
        return $requete['Modalites_Controle'];
    }
    return 'Non spécifié';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Requêtes Traitées | OGISCA</title>
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
    padding-left: 1.5rem;
}

@media (min-width: 640px) {
    .stat-card {
        padding-left: 5rem;
    }
}

.stat-icon {
    position: absolute;
    left: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    width: 3rem;
    height: 3rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

@media (min-width: 640px) {
    .stat-icon {
        width: 3.5rem;
        height: 3.5rem;
        font-size: 1.8rem;
    }
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
}

.page-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 16px;
    padding: 1.5rem;
    margin: 1rem;
    margin-bottom: 1.5rem;
    color: white;
    box-shadow: 0 8px 25px rgba(79, 70, 229, 0.2);
}

@media (max-width: 640px) {
    .page-header {
        margin: 0.5rem;
        padding: 1rem;
    }
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1rem;
    margin: 0 1rem 1rem 1rem;
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
    padding: 1rem;
}

.popup.open {
    display: flex;
}

.popup-content {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    width: 100%;
    max-width: 95vw;
    max-height: 90vh;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow-y: auto;
    animation: slideUp 0.3s ease-out;
}

@media (min-width: 640px) {
    .popup-content {
        max-width: 90vw;
        padding: 2rem;
    }
}

@media (min-width: 1024px) {
    .popup-content {
        max-width: 700px;
    }
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
    top: 0.5rem;
    right: 0.5rem;
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
    z-index: 10;
}

.close-btn:hover {
    background: #f3f4f6;
    color: #ef4444;
}

/* Table responsive */
.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.table-responsive {
    min-width: 1000px;
    width: 100%;
}

@media (max-width: 768px) {
    .table-responsive {
        min-width: 800px;
    }
}

/* Preview modal specific styles */
.preview-content {
    max-height: 60vh;
    overflow: auto;
}

@media (max-width: 640px) {
    .preview-content {
        max-height: 50vh;
    }
}

.preview-iframe {
    width: 100%;
    height: 60vh;
    border: none;
    border-radius: 8px;
}

@media (max-width: 640px) {
    .preview-iframe {
        height: 50vh;
    }
}

.preview-image {
    max-width: 100%;
    max-height: 60vh;
    border-radius: 8px;
}

@media (max-width: 640px) {
    .preview-image {
        max-height: 50vh;
    }
}

/* Action buttons responsive */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

@media (max-width: 640px) {
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons button {
        width: 100%;
        justify-content: center;
    }
}

/* Mobile optimizations */
@media (max-width: 640px) {
    .main-content {
        padding: 0.5rem;
    }
    
    .card {
        margin: 0.5rem 0;
    }
    
    .text-sm-mobile {
        font-size: 0.875rem;
    }
    
    .text-xs-mobile {
        font-size: 0.75rem;
    }
}

/* Touch improvements */
@media (hover: none) and (pointer: coarse) {
    .btn-primary:hover {
        transform: none;
        box-shadow: none;
    }
    
    .table-row-hover:hover {
        transform: none;
    }
    
    .card:hover {
        transform: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
}

/* Scrollbar styling */
.table-container::-webkit-scrollbar {
    height: 8px;
}

.table-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
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
            <li class="nav-item">
                <a href="mes_ec.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-book w-6 text-center mr-3"></i>
                    <span>MES EC</span>
                </a>
            </li>
            <li class="nav-item active">
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
<header class="topbar flex items-center justify-between px-4 py-3 lg:px-6 lg:py-4 sticky top-0 z-40">
  <div class="flex items-center space-x-3">
    <button @click="mobileMenuOpen = true" class="lg:hidden text-gray-700 focus:outline-none" aria-label="Toggle menu">
      <i class="fas fa-bars text-xl"></i>
    </button>
   
    <div class="font-semibold text-blue-700 uppercase text-sm lg:text-lg">
      <div class="flex items-center space-x-2 lg:space-x-3">
        <img src="../admin/logoinjs.png" alt="Logo" class="w-8 h-8 lg:w-10 lg:h-10 rounded-full border-2 border-gray-200" />
        <div class="hidden sm:block">INSTITUT NATIONAL DE LA JEUNESSE ET DES SPORTS</div>
        <div class="sm:hidden text-xs">INJS</div>
      </div>
    </div>
  </div>
  
  <div class="user-profile">
    <div class="flex items-center space-x-2 lg:space-x-3 cursor-pointer">
      <div class="text-right hidden sm:block">
        <div class="text-gray-700 font-semibold text-sm lg:text-base">
          <?= safe_html($enseignant_prenom) ?> <?= safe_html($enseignant_nom) ?>
        </div>
        <div class="text-gray-500 text-xs lg:text-sm">
          <?= safe_html($_SESSION['matricule']) ?>
        </div>
      </div>
      <div class="relative">
        <img src="https://www.svgrepo.com/show/382106/user-circle.svg" alt="Profil" class="w-10 h-10 lg:w-12 lg:h-12 rounded-full border-2 border-white shadow-md" />
        <span class="absolute bottom-0 right-0 w-2 h-2 lg:w-3 lg:h-3 bg-green-500 rounded-full border-2 border-white"></span>
      </div>
    </div>
    
    <div class="user-dropdown" x-show="userDropdownOpen" @click.outside="userDropdownOpen = false">
      <div class="flex items-center space-x-3 pb-3 border-b border-gray-100">
        <img src="https://www.svgrepo.com/show/382106/user-circle.svg" alt="Profil" class="w-12 h-12 rounded-full border-2 border-gray-200" />
        <div>
          <div class="font-semibold text-gray-800"><?= safe_html($enseignant_prenom) ?> <?= safe_html($enseignant_nom) ?></div>
          <div class="text-sm text-gray-500"><?= safe_html($_SESSION['matricule']) ?></div>
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

    <main class="p-3 lg:p-6 space-y-4 lg:space-y-6 overflow-auto">

        <!-- En-tête de page -->
        <div class="page-header">
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-bold mb-2">Requêtes Redirigées</h1>
                    <p class="text-blue-100 opacity-90 text-sm md:text-base">Gestion des demandes étudiantes résolues</p>
                </div>
                <div class="text-white text-sm mt-3 md:mt-0">
                    <i class="far fa-calendar-alt mr-2"></i>
                    <?php echo date('d/m/Y'); ?>
                </div>
            </div>
        </div>

        <!-- Flash Message -->
        <?php if ($flash): ?>
            <div class="card p-4 lg:p-6 <?= $flash_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?> mx-4 lg:mx-0">
                <div class="flex items-center gap-3">
                    <i class="fas <?= $flash_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> text-lg"></i>
                    <span class="font-medium"><?= safe_html($flash) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <?php
        $requetes_valides = array_filter($requetes, 'can_process_request');
        $requetes_invalides = array_filter($requetes, function($r) { return !can_process_request($r); });
        ?>
        <div class="stats-grid">
            <div class="card fade-slide-up delay-1">
                <div class="p-4 lg:p-6 stat-card">
                    
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">Total Requêtes</div>
                    <div class="text-2xl lg:text-3xl font-bold text-blue-700 mb-2"><?= count($requetes) ?></div>
                    <div class="text-xs text-gray-500">
                        Total reçues
                    </div>
                </div>
            </div>

            <div class="card fade-slide-up delay-2">
                <div class="p-4 lg:p-6 stat-card">
                    
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">Valides</div>
                    <div class="text-2xl lg:text-3xl font-bold text-green-600 mb-2"><?= count($requetes_valides) ?></div>
                    <div class="text-xs text-gray-500">
                        Peuvent être traitées
                    </div>
                </div>
            </div>

            <div class="card fade-slide-up delay-3">
                <div class="p-4 lg:p-6 stat-card">
                    
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">Invalides</div>
                    <div class="text-2xl lg:text-3xl font-bold text-red-600 mb-2"><?= count($requetes_invalides) ?></div>
                    <div class="text-xs text-gray-500">
                        ID_EC manquant
                    </div>
                </div>
            </div>

            <div class="card fade-slide-up delay-4">
                <div class="p-4 lg:p-6 stat-card">
                    
                    <div class="text-gray-500 uppercase text-sm font-semibold mb-2">En attente</div>
                    <div class="text-2xl lg:text-3xl font-bold text-purple-600 mb-2"><?= count($requetes) ?></div>
                    <div class="text-xs text-gray-500">
                        À traiter
                    </div>
                </div>
            </div>
        </div>

        <!-- Requêtes List -->
        <div class="card p-4 lg:p-6 mx-4 lg:mx-0">
            <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-3 mb-4 lg:mb-6">
                <h3 class="text-lg lg:text-xl font-semibold text-gray-800">Liste des Requêtes Traitées</h3>
                <?php if (count($requetes_invalides) > 0): ?>
                    <div class="text-sm text-red-600 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><?= count($requetes_invalides) ?> requête(s) invalide(s)</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($requetes)): ?>
                <div class="text-center py-8 lg:py-12">
                    <i class="fas fa-inbox text-4xl lg:text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg lg:text-xl font-semibold text-gray-600 mb-2">Aucune requête traitée</h3>
                    <p class="text-gray-500 text-sm lg:text-base">Les requêtes qui vous sont assignées apparaîtront ici.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table-responsive text-sm text-gray-700">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50">
                                <th class="text-left p-4 font-semibold text-gray-700">Étudiant</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Matière</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Modalités</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Motif</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Pièce jointe</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Date</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Statut</th>
                                <th class="text-left p-4 font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requetes as $r): ?>
                                <tr class="table-row-hover border-b border-gray-100 last:border-b-0 <?= !can_process_request($r) ? 'requete-invalide' : '' ?>">
                                    <td class="p-4">
                                        <div class="flex items-center min-w-[150px]">
                                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user-graduate text-blue-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900 text-sm-mobile"><?= safe_html($r['etu_nom'] . ' ' . $r['etu_prenom']) ?></div>
                                                <div class="text-gray-500 text-xs-mobile"><?= safe_html($r['matricule'] ?? 'N/A') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 min-w-[200px]">
                                        <div class="font-medium text-gray-900 text-sm-mobile">
                                            <?= safe_html(get_matiere_display($r)) ?>
                                            <?php if (!can_process_request($r)): ?>
                                                <span class="inline-block ml-2 text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>ID manquant
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-gray-500 text-xs-mobile mt-1">
                                            ID: <?= safe_html($r['ID_EC'] ?? $r['element_constitutif_original'] ?? 'Non spécifié') ?>
                                        </div>
                                    </td>
                                    <td class="p-4 min-w-[150px]">
                                        <div class="text-gray-700 text-sm-mobile"><?= safe_html(get_modalites_display($r)) ?></div>
                                    </td>
                                    <td class="p-4 min-w-[200px]">
                                        <div class="text-gray-700 text-sm-mobile"><?= safe_html($r['motif']) ?></div>
                                        <?php if (!empty($r['contenu'])): ?>
                                            <div class="text-gray-500 text-xs-mobile mt-1"><?= nl2br(safe_html(substr($r['contenu'], 0, 100) . (strlen($r['contenu']) > 100 ? '...' : ''))) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 min-w-[120px]">
                                        <?php if (!empty($r['piece_jointe'])): ?>
                                            <?php
                                            $file_extension = strtolower(pathinfo($r['piece_jointe'], PATHINFO_EXTENSION));
                                            $file_path = 'view_file.php?requete_id=' . intval($r['id']);
                                            ?>
                                            <button onclick="openPreview('<?= safe_html($file_path) ?>', '<?= safe_html($file_extension) ?>')" 
                                                    class="flex items-center gap-2 px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg transition-colors text-sm w-full lg:w-auto justify-center">
                                                <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                    <i class="fas fa-image text-blue-500"></i>
                                                    <span class="hidden sm:inline">Voir image</span>
                                                <?php elseif ($file_extension === 'pdf'): ?>
                                                    <i class="fas fa-file-pdf text-red-500"></i>
                                                    <span class="hidden sm:inline">Voir PDF</span>
                                                <?php else: ?>
                                                    <i class="fas fa-file text-gray-500"></i>
                                                    <span class="hidden sm:inline">Voir fichier</span>
                                                <?php endif; ?>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">Aucune</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-gray-600 text-sm-mobile min-w-[120px]">
                                        <?= date('d/m/Y H:i', strtotime($r['date_envoi'])) ?>
                                    </td>
                                    <td class="p-4 min-w-[120px]">
                                        <span class="status-badge status-info text-xs-mobile">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?= safe_html($r['statut']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 min-w-[200px]">
                                        <div class="action-buttons">
                                            <?php if (can_process_request($r)): ?>
                                                <button 
                                                    onclick="openModalWithRequete(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)"
                                                    class="btn-primary px-3 py-2 text-sm"
                                                >
                                                    <i class="fas fa-check"></i>
                                                    <span class="hidden sm:inline">Résoudre</span>
                                                </button>
                                            <?php else: ?>
                                                <button 
                                                    disabled
                                                    class="px-3 py-2 text-sm bg-gray-400 text-white rounded-lg flex items-center gap-2 cursor-not-allowed w-full lg:w-auto justify-center"
                                                    title="Requête invalide - ID_EC manquant"
                                                >
                                                    <i class="fas fa-ban"></i>
                                                    <span class="hidden sm:inline">Indisponible</span>
                                                </button>
                                            <?php endif; ?>
                                            <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir rejeter cette requête ?');" class="inline w-full lg:w-auto">
                                                <input type="hidden" name="csrf" value="<?= safe_html($csrf) ?>">
                                                <input type="hidden" name="action" value="rejeter">
                                                <input type="hidden" name="requete_id" value="<?= intval($r['id']) ?>">
                                                <button type="submit" class="px-3 py-2 text-sm bg-red-500 hover:bg-red-600 text-white rounded-lg transition-all duration-300 flex items-center gap-2 w-full lg:w-auto justify-center">
                                                    <i class="fas fa-times"></i>
                                                    <span class="hidden sm:inline">Rejeter</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <footer class="py-3 px-4 lg:py-4 lg:px-6 text-center text-sm text-gray-500 border-t border-gray-200 mt-auto">
        <p>© <?= date('Y') ?> OGISCA - INJS. Tous droits réservés.</p>
    </footer>
</div>

<!-- Modal Résolution -->
<div id="resolutionModal" class="popup" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <form method="post" class="popup-content space-y-4 lg:space-y-6">
        <button type="button" class="close-btn" onclick="closeModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="flex items-center space-x-3 mb-2">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600 text-lg lg:text-xl"></i>
            </div>
            <div>
                <h3 id="modalTitle" class="text-xl lg:text-2xl font-bold text-gray-800">Résoudre la Requête</h3>
                <p class="text-gray-600 text-sm lg:text-base">Traiter la demande étudiante</p>
            </div>
        </div>

        <input type="hidden" name="csrf" value="<?= safe_html($csrf) ?>">
        <input type="hidden" name="action" value="resoudre">
        <input type="hidden" id="modal_requete_id" name="requete_id">
        <input type="hidden" id="modal_etudiant_id" name="etudiant_id">
        <input type="hidden" id="modal_ID_EC" name="ID_EC">

        <!-- Informations requête -->
        <div class="grid grid-cols-1 gap-4 lg:gap-6 p-3 lg:p-4 bg-gray-50 rounded-xl">
            <div>
                <label class="block mb-2 lg:mb-3 font-semibold text-gray-700 text-sm lg:text-base">Étudiant</label>
                <p class="font-semibold text-gray-800 text-sm lg:text-base" id="modal_etudiant_nom"></p>
            </div>
            <div>
                <label class="block mb-2 lg:mb-3 font-semibold text-gray-700 text-sm lg:text-base">Matière</label>
                <p class="font-semibold text-gray-800 text-sm lg:text-base" id="modal_matiere"></p>
            </div>
            <div>
                <label class="block mb-2 lg:mb-3 font-semibold text-gray-700 text-sm lg:text-base">ID EC</label>
                <p class="text-gray-800 font-mono text-sm lg:text-base" id="modal_id_ec"></p>
            </div>
            <div>
                <label class="block mb-2 lg:mb-3 font-semibold text-gray-700 text-sm lg:text-base">Modalités disponibles</label>
                <p class="text-gray-800 font-medium text-sm lg:text-base" id="modal_modalites"></p>
            </div>
        </div>

        <!-- Saisie des notes -->
        <div class="space-y-3 lg:space-y-4">
            <h4 class="text-lg font-semibold text-gray-800 border-b pb-2">Modification des notes</h4>
            <p class="text-sm text-gray-600 mb-3 lg:mb-4">Vous pouvez modifier une ou plusieurs notes. Laissez vide pour conserver la note actuelle.</p>
            
            <div id="notes-container">
                <!-- Les champs de notes seront générés ici par JavaScript -->
            </div>
        </div>

        <!-- Commentaire -->
        <div>
            <label class="block mb-2 lg:mb-3 font-semibold text-gray-700 text-sm lg:text-base">Commentaire enseignant</label>
            <textarea name="commentaire" rows="3"
                   class="form-input text-sm lg:text-base"
                   placeholder="Remarque optionnelle..."></textarea>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row justify-end gap-3 pt-3 lg:pt-4 border-t border-gray-200">
            <button type="button" 
                    class="px-4 py-2 lg:px-6 lg:py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium text-sm lg:text-base order-2 sm:order-1"
                    onclick="closeModal()">
                Annuler
            </button>
            <button type="submit" 
                    class="btn-primary px-4 py-2 lg:px-6 lg:py-3 text-sm lg:text-lg order-1 sm:order-2">
                <i class="fas fa-check"></i>
                Valider la Résolution
            </button>
        </div>
    </form>
</div>

<!-- Modal de prévisualisation des fichiers -->
<div id="previewModal" class="popup" role="dialog" aria-modal="true" aria-labelledby="previewModalTitle">
    <div class="popup-content max-w-4xl">
        <button type="button" class="close-btn" onclick="closePreviewModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="flex items-center space-x-3 mb-3 lg:mb-4">
            <div class="w-8 h-8 lg:w-10 lg:h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-file text-blue-600"></i>
            </div>
            <div>
                <h3 id="previewModalTitle" class="text-lg lg:text-xl font-bold text-gray-800">Prévisualisation</h3>
                <p class="text-gray-600 text-sm lg:text-base" id="previewFileInfo"></p>
            </div>
        </div>

        <div class="preview-content bg-gray-50 rounded-xl p-3 lg:p-4">
            <div id="imagePreview" class="hidden text-center">
                <img id="previewImage" src="" alt="Prévisualisation" class="preview-image mx-auto rounded-lg shadow-md">
            </div>
            
            <div id="pdfPreview" class="hidden">
                <iframe id="previewPdf" src="" class="preview-iframe rounded-lg border border-gray-300" frameborder="0"></iframe>
            </div>
            
            <div id="unsupportedPreview" class="hidden text-center py-8 lg:py-12">
                <i class="fas fa-exclamation-triangle text-4xl lg:text-5xl text-yellow-500 mb-3 lg:mb-4"></i>
                <h4 class="text-base lg:text-lg font-semibold text-gray-700 mb-2">Prévisualisation non disponible</h4>
                <p class="text-gray-600 text-sm lg:text-base mb-3 lg:mb-4">Ce type de fichier ne peut pas être prévisualisé directement.</p>
                <a id="downloadFileLink" href="#" class="btn-primary inline-flex items-center gap-2 text-sm lg:text-base">
                    <i class="fas fa-download"></i>
                    Télécharger le fichier
                </a>
            </div>
        </div>
        
        <div class="flex flex-col sm:flex-row justify-end gap-3 pt-3 lg:pt-4 border-t border-gray-200 mt-3 lg:mt-4">
            <a id="directDownloadLink" href="#" class="flex items-center gap-2 px-3 py-2 lg:px-4 lg:py-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors text-sm lg:text-base order-2 sm:order-1 justify-center">
                <i class="fas fa-download"></i>
                Télécharger
            </a>
            <button type="button" 
                    class="px-4 py-2 lg:px-6 lg:py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium text-sm lg:text-base order-1 sm:order-2"
                    onclick="closePreviewModal()">
                Fermer
            </button>
        </div>
    </div>
</div>

<script>
// Variables globales pour stocker les données de la requête
let currentRequete = {};

// Fonction pour ouvrir la modale
function openModalWithRequete(requete) {
    currentRequete = requete;
    
    // Remplir les informations de base
    document.getElementById('modal_requete_id').value = requete.id;
    document.getElementById('modal_etudiant_id').value = requete.etudiant_id;
    document.getElementById('modal_ID_EC').value = requete.ID_EC;
    document.getElementById('modal_etudiant_nom').textContent = (requete.etu_nom || '') + ' ' + (requete.etu_prenom || '');
    document.getElementById('modal_matiere').textContent = requete.matiere || requete.element_constitutif_original || requete.ID_EC || 'Non spécifié';
    document.getElementById('modal_id_ec').textContent = requete.ID_EC || requete.element_constitutif_original || 'Non spécifié';
    
    // Afficher un indicateur de chargement
    document.getElementById('modal_modalites').textContent = 'Chargement...';
    document.getElementById('notes-container').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-xl"></i><p class="text-gray-600 mt-2">Chargement des données...</p></div>';
    
    // Afficher la modale
    document.getElementById('resolutionModal').classList.add('open');
    
    // Charger les modalités et notes existantes
    fetchModalitesAndNotes(requete.ID_EC, requete.etudiant_id);
}

// Fonction pour fermer la modale
function closeModal() {
    document.getElementById('resolutionModal').classList.remove('open');
    currentRequete = {};
}

// Fonction pour récupérer les modalités disponibles et les notes existantes
async function fetchModalitesAndNotes(ID_EC, etudiant_id) {
    try {
        const response = await fetch(`get_modalites_notes.php?ID_EC=${encodeURIComponent(ID_EC)}&etudiant_id=${etudiant_id}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('modal_modalites').textContent = data.modalites.join(', ');
            generateNoteFields(data.modalites, data.notes_existantes || {});
        } else {
            document.getElementById('modal_modalites').textContent = 'Erreur de chargement';
            document.getElementById('notes-container').innerHTML = '<div class="text-center py-4 text-red-600"><i class="fas fa-exclamation-triangle"></i><p class="mt-2">Erreur: ' + (data.error || 'Chargement impossible') + '</p></div>';
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des données:', error);
        document.getElementById('modal_modalites').textContent = 'Erreur de connexion';
        document.getElementById('notes-container').innerHTML = '<div class="text-center py-4 text-red-600"><i class="fas fa-exclamation-triangle"></i><p class="mt-2">Erreur de connexion au serveur</p></div>';
    }
}

// Fonction pour générer les champs de notes dynamiquement
function generateNoteFields(modalites, notesExistantes) {
    const container = document.getElementById('notes-container');
    container.innerHTML = '';
    
    modalites.forEach(modalite => {
        if (modalite !== 'MOY') {
            const noteActuelle = notesExistantes[modalite] !== undefined ? notesExistantes[modalite] : '';
            const fieldHTML = `
                <div class="grid grid-cols-1 gap-3 lg:gap-6 items-center p-3 lg:p-4 bg-white border border-gray-200 rounded-xl">
                    <div>
                        <label class="block mb-2 lg:mb-3 font-semibold text-gray-700 text-sm lg:text-base">
                            Note ${modalite}
                            ${noteActuelle !== '' ? `<span class="text-xs lg:text-sm text-gray-500 font-normal">(Actuelle: ${noteActuelle})</span>` : ''}
                        </label>
                        <input type="number" name="note_${modalite}" step="0.1" min="0" max="20" 
                               class="form-input text-sm lg:text-base"
                               placeholder="0-20 (optionnel)"
                               value="${noteActuelle}">
                    </div>
                    <div>
                        <p class="text-xs lg:text-sm text-gray-500">
                            Laissez vide si vous ne souhaitez pas modifier cette note.
                        </p>
                    </div>
                </div>
            `;
            container.innerHTML += fieldHTML;
        }
    });
    
    if (container.innerHTML === '') {
        container.innerHTML = '<div class="text-center py-4 text-gray-500"><p>Aucune modalité modifiable disponible</p></div>';
    }
}

// Fermer la modale en cliquant à l'extérieur
document.getElementById('resolutionModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Empêcher la fermeture en cliquant à l'intérieur du contenu
document.querySelector('#resolutionModal .popup-content').addEventListener('click', function(e) {
    e.stopPropagation();
});

// Fonctions pour la prévisualisation des fichiers
function openPreview(filePath, fileExtension) {
    const modal = document.getElementById('previewModal');
    const fileInfo = document.getElementById('previewFileInfo');
    const imagePreview = document.getElementById('imagePreview');
    const pdfPreview = document.getElementById('pdfPreview');
    const unsupportedPreview = document.getElementById('unsupportedPreview');
    const previewImage = document.getElementById('previewImage');
    const previewPdf = document.getElementById('previewPdf');
    const downloadLink = document.getElementById('directDownloadLink');
    const downloadFileLink = document.getElementById('downloadFileLink');
    
    // Masquer tous les conteneurs de prévisualisation
    imagePreview.classList.add('hidden');
    pdfPreview.classList.add('hidden');
    unsupportedPreview.classList.add('hidden');
    
    // Mettre à jour le lien de téléchargement
    downloadLink.href = filePath;
    downloadFileLink.href = filePath;
    
    // Afficher le type de fichier
    fileInfo.textContent = `Type: ${fileExtension.toUpperCase()}`;
    
    // Afficher la prévisualisation appropriée
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
        previewImage.src = filePath;
        imagePreview.classList.remove('hidden');
    } else if (fileExtension === 'pdf') {
        previewPdf.src = filePath;
        pdfPreview.classList.remove('hidden');
    } else {
        unsupportedPreview.classList.remove('hidden');
    }
    
    // Afficher la modale
    modal.classList.add('open');
}

function closePreviewModal() {
    document.getElementById('previewModal').classList.remove('open');
    document.getElementById('previewImage').src = '';
    document.getElementById('previewPdf').src = '';
}

// Fermer la modale de prévisualisation en cliquant à l'extérieur
document.getElementById('previewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePreviewModal();
    }
});

// Empêcher la fermeture en cliquant à l'intérieur du contenu
document.querySelector('#previewModal .popup-content').addEventListener('click', function(e) {
    e.stopPropagation();
});

// Gestion du redimensionnement de la fenêtre
window.addEventListener('resize', function() {
    // Réajuster la hauteur des iframes si nécessaire
    const previewIframe = document.getElementById('previewPdf');
    if (previewIframe && !previewIframe.classList.contains('hidden')) {
        previewIframe.style.height = window.innerHeight * 0.6 + 'px';
    }
});
</script>

</body>
</html>