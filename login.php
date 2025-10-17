<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$database = "gestion_academique";

// Connexion
$conn = new mysqli($servername, $username, $password, $database);

// Vérification
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

$erreur = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricule = trim($_POST['matricule']);
    $mot_de_passe = trim($_POST['mot_de_passe']);

    // Validation basique
    if (empty($matricule) || empty($mot_de_passe)) {
        $erreur = "Veuillez remplir tous les champs.";
    } else {
        // Vérifier d'abord dans la table users
        $stmt = $conn->prepare("SELECT id, matricule, mot_de_passe, role FROM users WHERE matricule = ?");
        if (!$stmt) {
            $erreur = "Erreur de préparation de la requête : " . $conn->error;
        } else {
            $stmt->bind_param("s", $matricule);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (password_verify($mot_de_passe, $user['mot_de_passe'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['matricule'] = $user['matricule'];
                    $_SESSION['role'] = $user['role'];

                    // Utiliser le préfixe du matricule pour la redirection
                    $prefix = strtoupper(substr($user['matricule'], 0, 2));
                    
                    // Debug: Afficher le préfixe pour vérification
                    error_log("Connexion réussie - Matricule: " . $user['matricule'] . ", Prefix: " . $prefix);
                    
                    // Redirection basée sur le préfixe du matricule
                    switch($prefix) {
                        case 'AD':
                            header('Location: ADMIN/index.php');
                            exit;
                        case 'EN':
                            header('Location: enseignant/index.php');
                            exit;
                        case 'ET':
                            header('Location: etudiant/AccueilEtudiant.php');
                            exit;
                        default:
                            $erreur = "Préfixe de matricule inconnu: '" . htmlspecialchars($prefix) . "'. Contactez l'administrateur.";
                            error_log("Préfixe inconnu détecté: " . $prefix);
                            break;
                    }
                } else {
                    $erreur = "Mot de passe incorrect.";
                }
            } else {
                // Si l'utilisateur n'est pas trouvé dans users, vérifier dans les tables spécifiques
                $erreur = vérifierUtilisateurTablesSpécifiques($conn, $matricule, $mot_de_passe);
                if (empty($erreur)) {
                    // La fonction a géré la connexion et la redirection
                    exit;
                }
            }
            $stmt->close();
        }
    }
}
$conn->close();

/**
 * Vérifie l'utilisateur dans les tables spécifiques (enseignants, administrateurs, étudiants)
 */
function vérifierUtilisateurTablesSpécifiques($conn, $matricule, $mot_de_passe) {
    $prefix = strtoupper(substr($matricule, 0, 2));
    
    switch($prefix) {
        case 'EN':
            $table = "enseignants";
            $redirect = "enseignant/index.php";
            break;
        case 'AD':
            $table = "administrateurs";
            $redirect = "ADMIN/index.php";
            break;
        case 'ET':
            $table = "etudiants";
            $redirect = "etudiant/AccueilEtudiant.php";
            break;
        default:
            return "Utilisateur non trouvé.";
    }
    
    // Préparer la requête pour la table spécifique
    $stmt = $conn->prepare("SELECT id, matricule, nom, prenom, mot_de_passe FROM $table WHERE matricule = ?");
    if (!$stmt) {
        return "Erreur de préparation de la requête : " . $conn->error;
    }
    
    $stmt->bind_param("s", $matricule);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Vérifier le mot de passe (supposer que c'est en texte brut pour le moment)
        if ($mot_de_passe === $user['mot_de_passe']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['matricule'] = $user['matricule'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['role'] = $prefix;
            
            error_log("Connexion réussie via table $table - Matricule: " . $user['matricule']);
            
            header('Location: ' . $redirect);
            exit;
        } else {
            return "Mot de passe incorrect.";
        }
    }
    
    $stmt->close();
    return "Utilisateur non trouvé.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion | OGISCA</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .error-message {
      animation: fadeIn 0.5s ease-in;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body class="bg-gradient-to-r from-indigo-600 to-blue-500 min-h-screen flex items-center justify-center p-4">
  <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md">
    <!-- En-tête -->
    <div class="text-center mb-8">
      <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-graduation-cap text-indigo-600 text-2xl"></i>
      </div>
      <h2 class="text-3xl font-bold text-indigo-700 mb-2">OGISCA</h2>
      <p class="text-gray-600">Plateforme de Gestion Académique</p>
    </div>

    <!-- Messages d'erreur -->
    <?php if (!empty($erreur)): ?>
      <div class="error-message bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
        <i class="fas fa-exclamation-triangle mr-3"></i>
        <span><?= htmlspecialchars($erreur) ?></span>
      </div>
    <?php endif; ?>

    <!-- Formulaire -->
    <form method="post" class="space-y-6">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-id-card mr-2 text-indigo-600"></i>Matricule
        </label>
        <input type="text" name="matricule" placeholder="Ex: ADM001, EN001, ETU001"
               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-200"
               value="<?= isset($_POST['matricule']) ? htmlspecialchars($_POST['matricule']) : '' ?>"
               required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-lock mr-2 text-indigo-600"></i>Mot de passe
        </label>
        <input type="password" name="mot_de_passe" placeholder="Votre mot de passe"
               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-200"
               required>
      </div>

      <button type="submit"
              class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
        <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
      </button>
    </form>

   
    <div class="text-center text-xs text-gray-400 mt-6">
      &copy; 2024 OGISCA. Tous droits réservés.

    
  </div>

  <script>
    // Focus sur le premier champ
    document.querySelector('input[name="matricule"]').focus();
    
    // Effacer l'erreur lors de la frappe
    document.querySelectorAll('input').forEach(input => {
      input.addEventListener('input', function() {
        const errorDiv = document.querySelector('.error-message');
        if (errorDiv) {
          errorDiv.remove();
        }
      });
    });
  </script>
</body>
</html>