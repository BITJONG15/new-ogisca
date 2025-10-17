<?php
session_start();
$conn = new mysqli("localhost", "root", "", "gestion_academique");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}
;

$erreur = "";

// Vérification de la soumission du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matricule = trim($_POST["matricule"]);
    $mot_de_passe = trim($_POST["mot_de_passe"]);

    // Requête sécurisée pour chercher l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM users WHERE matricule = ?");
    $stmt->bind_param("s", $matricule);
    $stmt->execute();
    $result = $stmt->get_result();

    // Si l'utilisateur existe
    if ($user = $result->fetch_assoc()) {
        // Vérification du mot de passe
        if (password_verify($mot_de_passe, $user['mot_de_passe'])) {
            // Initialisation de la session
            $_SESSION["user_id"] = $user['id'];
            $_SESSION["matricule"] = $user['matricule'];
            $_SESSION["role"] = $user['role']; // Peut être utile

            // Redirection selon le type de matricule
            // Redirection selon le type de matricule
if (substr($matricule, 0, 3) === "ADM") {
    header("Location: ADMIN/index.php");
    exit;
} elseif (substr($matricule, 0, 2) === "EN") {
    header("Location: enseignant/dashboard.php");
    exit;
} elseif (substr($matricule, 0, 2) === "ET") {
    header("Location: AccueilEtudiant.php");
    exit;
} else {
    $erreur = "Le matricule est inconnu.";
}

        } else {
            $erreur = "Mot de passe incorrect.";
        }
    } else {
        $erreur = "Aucun utilisateur trouvé avec ce matricule.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Connexion | OGISCA</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-indigo-600 to-blue-500 min-h-screen flex items-center justify-center">
  <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-sm">
    <h2 class="text-2xl font-bold text-center text-indigo-700 mb-6">Connexion à OGISCA</h2>

    <?php if (!empty($erreur)): ?>
      <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm text-center">
        <?= htmlspecialchars($erreur) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Matricule</label>
        <input type="text" name="matricule" placeholder="Ex: EN00123"
               class="mt-1 w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500"
               required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Mot de passe</label>
        <input type="password" name="mot_de_passe" placeholder="********"
               class="mt-1 w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500"
               required>
      </div>

      <button type="submit"
              class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded transition duration-200">
        Se connecter
      </button>
    </form>
  </div>
</body>
</html>
