<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Requêtes Étudiant</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

  <!-- FORMULAIRE DE REQUÊTE -->
  <div class="bg-white shadow-md rounded-lg p-6 mb-8 max-w-3xl mx-auto">
    <h2 class="text-xl font-bold mb-4 text-blue-800">📝 Formuler une Requête</h2>

    <form class="space-y-4">
      <!-- Motif -->
      <div>
        <label class="block font-semibold mb-1">Motif</label>
        <select class="w-full border rounded p-2" required>
          <option value="">-- Sélectionnez un motif --</option>
          <option value="note">Erreur de note</option>
          <option value="absence">Absence justifiée</option>
          <option value="inscription">Problème d’inscription</option>
          <option value="autre">Autre</option>
        </select>
      </div>

      <!-- Description -->
      <div>
        <label class="block font-semibold mb-1">Description</label>
        <textarea rows="3" class="w-full border rounded p-2" placeholder="Expliquez brièvement votre situation..." required></textarea>
      </div>

      <!-- Pièce jointe -->
      <div>
        <label class="block font-semibold mb-1">Joindre un justificatif (PDF, JPG...)</label>
        <input type="file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-blue-700 file:text-white hover:file:bg-blue-800"/>
      </div>

      <!-- Bouton -->
      <div>
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded">
          Envoyer la requête
        </button>
      </div>
    </form>
  </div>

  <!-- TABLEAU DES REQUÊTES -->
  <div class="bg-white shadow-md rounded-lg p-6 max-w-6xl mx-auto">
    <h2 class="text-xl font-bold mb-4 text-blue-800">📄 Requêtes Envoyées</h2>

    <table class="w-full text-sm text-left border">
      <thead class="bg-gray-200 text-gray-600">
        <tr>
          <th class="p-2 border">Date</th>
          <th class="p-2 border">Motif</th>
          <th class="p-2 border">Description</th>
          <th class="p-2 border">Statut</th>
          <th class="p-2 border">Catégorie</th>
        </tr>
      </thead>
      <tbody class="text-gray-800">
        <tr>
          <td class="p-2 border">16/07/2025</td>
          <td class="p-2 border">Erreur de note</td>
          <td class="p-2 border">Ma note de CC est incorrecte...</td>
          <td class="p-2 border text-yellow-600 font-bold">⏳ En attente</td>
          <td class="p-2 border">Académique</td>
        </tr>
        <tr>
          <td class="p-2 border">12/07/2025</td>
          <td class="p-2 border">Absence justifiée</td>
          <td class="p-2 border">J'étais malade avec un certificat</td>
          <td class="p-2 border text-green-600 font-bold">✅ Validée</td>
          <td class="p-2 border">Présence</td>
        </tr>
        <tr>
          <td class="p-2 border">08/07/2025</td>
          <td class="p-2 border">Inscription</td>
          <td class="p-2 border">Je n'apparais pas dans la liste</td>
          <td class="p-2 border text-red-600 font-bold">❌ Refusée</td>
          <td class="p-2 border">Administratif</td>
        </tr>
      </tbody>
    </table>
  </div>

</body>
</html>
