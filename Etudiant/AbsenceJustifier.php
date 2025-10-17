<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Justification d'absence</title>
  <!-- Intégration de Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

  <!-- En-tête avec profil et cloche -->
  <div class="flex items-center justify-between p-4 bg-white shadow">
  <div class="flex items-center gap-2">
      <!-- Avatar rond -->
      <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
        <span class="text-white text-xl">👤</span>
      </div>
      <!-- Nom et identifiant -->
      <div>
        <p class="font-semibold">Prince Fouelefack</p>
        <p class="text-sm text-gray-500">A6GY88</p>
      </div>
    </div>
    <!-- Cloche de notification avec pastille rouge -->
    <div class="relative">
      <span class="text-yellow-500 text-xl">🔔</span>
      <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full"></span>
    </div>
  </div>
  <!-- Menu latéral Overlay -->
<div id="sidebar" class="fixed top-0 left-0 w-full h-full overlay hidden z-50">
  <!-- Conteneur menu -->
  <div class="bg-gray-900 text-white w-64 h-full p-6 space-y-6">
    <!-- Bouton fermeture -->
    <div class="flex justify-end">
      <button onclick="toggleSidebar()">
        <i class="fas fa-times w-6 h-6 text-white"></i>
      </button>
    </div>
    <!-- Icônes de navigation -->
    <div class="space-y-5">
      <a href="#" class="flex items-center gap-3 bg-blue-700 text-white rounded px-3 py-2 hover:opacity-30">
        <i class="fas fa-home text-white w-5 h-5"></i> Accueil
      </a>
      <a href="#" class="flex items-center gap-3 hover:opacity-30">
        <i class="fas fa-envelope text-white w-5 h-5"></i> Requête
      </a>
      <a href="#" class="flex items-center gap-3 hover:opacity-30">
        <i class="fas fa-sticky-note text-white w-5 h-5"></i> Note
      </a>
      <a href="#" class="flex items-center gap-3 hover:opacity-30">
        <i class="fas fa-user text-white w-5 h-5"></i> Profile
      </a>
      <a href="#" class="flex items-center gap-3 hover:opacity-30">
        <i class="fas fa-bell text-white w-5 h-5"></i> Notification
      </a>
    </div>
  </div>
</div>

<!-- Bouton pour ouvrir le menu -->
<button onclick="toggleSidebar()" class="fixed top-4 right-4 z-50">
  <i class="fas fa-bars w-6 h-6 text-gray-800"></i>
</button>

    <!-- Script JS pour activer/désactiver le menu -->
    <script>
      function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('hidden');
      }
    </script>

  <!-- Titre et description -->
  <div class="p-4">
    <h1 class="text-2xl font-bold">Passez un eévaluation en rattrapage.</h1>
    <p class="text-gray-700 mt-2">
        Sélectionnez le type de requete et dévrivez votre situation en détail.
    </p>
  </div>

  <!-- Formulaire -->
  <div class="bg-white rounded-xl shadow mx-4 p-4 mb-24">
    <!-- Champ : Unité d’enseignement -->
    <input type="text" placeholder="Unité d’enseignement"
      class="w-full bg-gray-100 text-sm p-3 mb-3 rounded-md outline-none" />

    <!-- Champ : Note affichée -->
    <input type="text" placeholder="Motif de l'absence"
      class="w-full bg-gray-100 text-sm p-3 mb-3 rounded-md outline-none" />

    <!-- Champ : Note réclamée -->
    <input type="text" placeholder="Détails complémentaires"
      class="w-full bg-gray-100 text-sm p-3 mb-3 rounded-md outline-none" />

    <!-- Champ : Détail des anomalies -->
    <textarea placeholder="Détail des anomalies suspectées (La question 3 n’a pas été corrigée, Le total des points ne correspond pas à la note finale...)"
      class="w-full bg-gray-100 text-sm p-3 mb-4 rounded-md outline-none resize-none h-24"></textarea>

    <!-- Section pièces justificatives -->
    <div class="mb-4">
      <h2 class="font-bold text-lg mb-1">Pièces justificatives</h2>
      <p class="text-sm text-gray-700 mb-2">
        Joignez tout document prouvant votre absence (certificat, convocation, etc.)/ Formats acceptés; PDF,JPG ou PNG.
      </p>

      <!-- Fichier joint (exemple) -->
      <div class="flex items-center gap-2 bg-white p-2 border rounded-md w-full">
        <img src="https://via.placeholder.com/60x80" alt="Document"
             class="w-12 h-16 object-cover rounded" />
        <span class="text-red-500 font-bold text-xl cursor-pointer">✖</span>
      </div>

      <!-- Bouton d’ajout (icône de partage) -->
      <div class="flex justify-end mt-2">
        <button class="text-blue-600 text-xl">
          ⬆️ <!-- Icône upload simplifiée -->
        </button>
      </div>
    </div>

    <!-- Bouton de validation -->
    <button class="w-full bg-blue-700 text-white font-semibold py-2 rounded-md hover:bg-blue-800 transition">
      Valider
    </button>
  </div>

</body>
</html>