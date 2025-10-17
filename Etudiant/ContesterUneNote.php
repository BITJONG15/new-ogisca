<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Réclamation de note</title>
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

  <!-- Titre et description -->
  <div class="p-4">
    <h1 class="text-2xl font-bold">Contester une note ? Expliquez votre situation.</h1>
    <p class="text-gray-700 mt-2">
      Vous pensez qu’une erreur de correction a été commise ?
      Décrivez précisément le problème pour permettre une révision juste et rapide.
    </p>
  </div>

  <!-- Formulaire -->
  <div class="bg-white rounded-xl shadow mx-4 p-4 mb-24">
    <!-- Champ : Unité d’enseignement -->
    <input type="text" placeholder="Unité d’enseignement"
      class="w-full bg-gray-100 text-sm p-3 mb-3 rounded-md outline-none" />

    <!-- Champ : Note affichée -->
    <input type="text" placeholder="Note Afficher"
      class="w-full bg-gray-100 text-sm p-3 mb-3 rounded-md outline-none" />

    <!-- Champ : Note réclamée -->
    <input type="text" placeholder="Note Reclamer"
      class="w-full bg-gray-100 text-sm p-3 mb-3 rounded-md outline-none" />

    <!-- Champ : Détail des anomalies -->
    <textarea placeholder="Détail des anomalies suspectées (La question 3 n’a pas été corrigée, Le total des points ne correspond pas à la note finale...)"
      class="w-full bg-gray-100 text-sm p-3 mb-4 rounded-md outline-none resize-none h-24"></textarea>

    <!-- Section pièces justificatives -->
    <div class="mb-4">
      <h2 class="font-bold text-lg mb-1">Pièces justificatives</h2>
      <p class="text-sm text-gray-700 mb-2">
        Joignez : Copie de votre copie, corrigé officiel, échanges avec l’enseignant, etc..
        Formats acceptés : PDF, JPG ou PNG.
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

  <!-- Barre de navigation inférieure -->
  <div class="fixed bottom-0 left-0 right-0 bg-blue-100 p-2 flex justify-around items-center shadow-inner">
    <div class="flex flex-col items-center text-black">
      <span class="text-xl">🏠</span>
      <span class="text-sm">Accueil</span>
    </div>
    <div class="flex flex-col items-center text-blue-700 font-semibold">
      <span class="text-xl">📄</span>
      <span class="text-sm">Requete</span>
    </div>
    <div class="flex flex-col items-center text-black">
      <span class="text-xl">✅</span>
      <span class="text-sm">Note</span>
    </div>
    <div class="flex flex-col items-center text-black">
      <span class="text-xl">👤</span>
      <span class="text-sm">Profile</span>
    </div>
  </div>

</body>
</html>