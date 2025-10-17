<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Détail de la Note</title>
  <!-- Importation de Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 font-sans">

  <!-- En-tête utilisateur avec menu -->
  <div class="flex items-center justify-between p-4 bg-white shadow">
  <div class="flex items-center gap-2">
      <!-- Avatar -->
      <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
        <span class="text-white text-xl">👤</span>
      </div>
      <!-- Nom et matricule -->
      <div>
        <p class="font-semibold">Prince Fouelefack</p>
        <p class="text-sm text-gray-500">A6GY88</p>
      </div>
    </div>
    <!-- Icône de menu -->
    <div class="text-2xl">☰</div>
  </div>

  <!-- Informations sur la matière -->
  <div class="p-4 border-b border-blue-200">
    <h2 class="text-lg font-bold">📘 Mathématiques Appliquées</h2>
    <div class="grid grid-cols-2 gap-2 mt-2 text-sm text-gray-700">
      <p>👨‍🏫 Prof. Alain Mbarga</p>
      <p>🧪 MATH-301</p>
      <p>🏫 Classe : CJA1</p>
      <p>🗓️ Semestre : S1</p>
      <p>🏆 Statut : <span class="text-green-600 font-semibold">Validé</span></p>
    </div>
  </div>

  <!-- Carte de note finale -->
  <div class="bg-blue-50 rounded-xl mx-4 my-4 p-4 shadow">
    <h3 class="text-center text-xl font-bold">🎯 Note Finale</h3>
    <p class="text-center text-green-600 text-2xl font-bold mt-2">14.5/20</p>
    <p class="text-center text-sm mt-1">Moyenne de la promotion : 12.8/20</p>
  </div>

  <!-- Tableau des évaluations -->
  <div class="px-4">
    <h3 class="text-lg font-bold mb-2">Détail des Évaluations</h3>
    <table class="w-full text-sm border border-gray-300">
      <thead class="bg-gray-200 text-left">
        <tr>
          <th class="p-2 border">Type</th>
          <th class="p-2 border">Date</th>
          <th class="p-2 border">Note (/20)</th>
          <th class="p-2 border">Poids</th>
          <th class="p-2 border">Commentaire</th>
        </tr>
      </thead>
      <tbody>
        <tr class="bg-white">
          <td class="p-2 border">CC</td>
          <td class="p-2 border">15/05/2024</td>
          <td class="p-2 border">15.0</td>
          <td class="p-2 border">60%</td>
          <td class="p-2 border">"Très bon travail"</td>
        </tr>
        <tr class="bg-gray-50">
          <td class="p-2 border">TP</td>
          <td class="p-2 border">10/04/2024</td>
          <td class="p-2 border">13.5</td>
          <td class="p-2 border">30%</td>
          <td class="p-2 border">"Peut mieux faire"</td>
        </tr>
        <tr class="bg-white">
          <td class="p-2 border">TPE</td>
          <td class="p-2 border">11/04/2024</td>
          <td class="p-2 border">16.0</td>
          <td class="p-2 border">10%</td>
          <td class="p-2 border">"Assiduité parfaite"</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Boutons d'action -->
  <div class="px-4 mt-6 flex flex-col gap-3">
    <!-- Bouton télécharger -->
    <button class="bg-green-600 text-white py-2 rounded-md font-semibold hover:bg-green-700 transition">
      Télécharger la copie
    </button>
    <!-- Bouton réclamation -->
    <button class="bg-blue-100 text-blue-800 py-2 rounded-md font-semibold hover:bg-blue-200 transition">
      Demander une révision
    </button>
  </div>

  <!-- Pied de page -->
  <footer class="bg-blue-900 text-white p-4 mt-6 text-sm">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Suivez-nous -->
      <div>
        <p class="font-bold mb-2">SUIVEZ-NOUS</p>
        <div class="flex gap-4 text-xl">
          <span>📘</span>
          <span>📸</span>
        </div>
      </div>
      <!-- Contact -->
      <div>
        <p class="font-bold mb-2">NOUS CONTACTER</p>
        <p>Bâtiment Administratif, Campus, YAOUNDE, P.O BOX….</p>
        <p>Téléphone : +237 000 00 00 00</p>
        <p>Email : exempl@gmail.com</p>
      </div>
      <!-- Liens rapides -->
      <div>
        <p class="font-bold mb-2">LIENS RAPIDES</p>
        <ul class="space-y-1">
          <li>Tableau de bord</li>
          <li>Notes académiques</li>
          <li>Inscriptions</li>
          <li>Requêtes</li>
        </ul>
      </div>
    </div>
  </footer>

</body>
</html>