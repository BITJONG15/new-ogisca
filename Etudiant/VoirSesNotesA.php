<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Détail Note Interactive</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

  <!-- En-tête -->
  <div class="flex items-center justify-between p-4 bg-white shadow">
    <div class="flex items-center gap-2">
      <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
        <span class="text-white text-xl">👤</span>
      </div>
      <div>
        <p class="font-semibold">Prince Fouelefack</p>
        <p class="text-sm text-gray-500">A6GY88</p>
      </div>
    </div>
    <div class="text-2xl">☰</div>
  </div>

  <!-- Informations sur la matière -->
  <div class="p-4 border-b border-blue-200">
    <h2 class="text-lg font-bold">📘 Mathématiques Appliquées</h2>
    <div class="grid grid-cols-2 gap-2 mt-2 text-sm text-gray-700">
      <p>👨‍🏫 Prof. Alain Mbarga</p>
      <p>📘 Code : MATH-301</p>
      <p>🏫 Classe : CJA1</p>
      <p>🗓️ Semestre : S1</p>
      <p>🏆 Statut : <span class="text-green-600 font-semibold">Validé</span></p>
    </div>
  </div>

  <!-- Note finale (calculée automatiquement) -->
  <div class="bg-blue-50 rounded-xl mx-4 my-4 p-4 shadow text-center">
    <h3 class="text-xl font-bold">🎯 Note Finale</h3>
    <p id="finalGrade" class="text-green-600 text-2xl font-bold mt-2">--</p>
    <p class="text-sm mt-1">Moyenne de la promotion : 12.8/20</p>
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
      <tbody id="evalTableBody">
        <!-- Les données seront injectées ici -->
      </tbody>
    </table>
  </div>

  <!-- Boutons -->
  <div class="px-4 mt-6 flex flex-col gap-3">
    <button id="downloadBtn" class="bg-green-600 text-white py-2 rounded-md font-semibold hover:bg-green-700 transition">
      Télécharger la copie
    </button>
    <button id="revisionBtn" class="bg-blue-100 text-blue-800 py-2 rounded-md font-semibold hover:bg-blue-200 transition">
      Demander une révision
    </button>
  </div>

  <!-- Modal de révision -->
  <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center z-50">
    <div class="bg-white rounded-lg p-6 w-80 text-center">
      <h2 class="text-lg font-bold mb-4">Demander une révision ?</h2>
      <p class="mb-4 text-sm">Votre demande sera transmise à l’enseignant. Souhaitez-vous continuer ?</p>
      <button onclick="closeModal()" class="bg-gray-300 px-4 py-2 rounded-md mr-2">Annuler</button>
      <button onclick="alert('Révision envoyée !'); closeModal();" class="bg-blue-600 text-white px-4 py-2 rounded-md">Confirmer</button>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-blue-900 text-white p-4 mt-6 text-sm">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <p class="font-bold mb-2">SUIVEZ-NOUS</p>
        <div class="flex gap-4 text-xl">
          <span>📘</span>
          <span>📸</span>
        </div>
      </div>
      <div>
        <p class="font-bold mb-2">NOUS CONTACTER</p>
        <p>Bâtiment Administratif, Campus, YAOUNDE, P.O BOX….</p>
        <p>Tél. : +237 000 00 00 00</p>
        <p>Email : exempl@gmail.com</p>
      </div>
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
  <!-- Script JS -->
  <script>
    // Données des évaluations
    const evaluations = [
      { type: 'CC', date: '15/05/2024', note: 15, poids: 0.6, commentaire: 'Très bon travail' },
      { type: 'TP', date: '10/04/2024', note: 13.5, poids: 0.3, commentaire: 'Peut mieux faire' },
      { type: 'TPE', date: '11/04/2024', note: 16, poids: 0.1, commentaire: 'Assiduité parfaite' },
    ];

    // Injecter les lignes dans le tableau
    const tbody = document.getElementById("evalTableBody");
    let total = 0;

    evaluations.forEach(ev => {
      total += ev.note * ev.poids;
      const row = 
        <tr class="bg-white">
          <td class="p-2 border">${ev.type}</td>
          <td class="p-2 border">${ev.date}</td>
          <td class="p-2 border">${ev.note}</td>
          <td class="p-2 border">${ev.poids * 100}%</td>
          <td class="p-2 border">"${ev.commentaire}"</td>
        </tr>
      ;
      tbody.innerHTML += row;
    });

    // Affichage de la note finale
    document.getElementById("finalGrade").textContent = ${total.toFixed(1)}/20;

    // Bouton "Télécharger"
    document.getElementById("downloadBtn").addEventListener("click", () => {
      alert("Téléchargement de la copie...");
    });

    // Ouvrir / Fermer la modale
    document.getElementById("revisionBtn").addEventListener("click", () => {
      document.getElementById("modal").classList.remove("hidden");
      document.getElementById("modal").classList.add("flex");
    });

    function closeModal() {
      document.getElementById("modal").classList.add("hidden");
      document.getElementById("modal").classList.remove("flex");
    }
  </script>

</body>
</html>