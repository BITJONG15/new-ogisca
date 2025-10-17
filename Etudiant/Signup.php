<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inscription</title>
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="bg-white min-h-screen flex items-center justify-center">
    <!-- ✅ Conteneur principal centré -->
    <div class="w-full max-w-sm p-6 bg-white rounded-xl shadow-md text-center">
      <!-- Logo -->
      <img src="C:\xampp64\htdocs\ogiscabeta2025\images\logo-removebg-preview.png"  alt="Logo OGISCA" class="w-16 mb-6" />

      <!-- Titre -->
      <h1 class="text-2xl font-bold text-center">Rejoignez nous!</h1>
      <p class="text-center text-gray-600 mb-6">
        Créez votre compte en 2 minutes pour accéder à vos notes, emploi du temps et bien plus.
      </p>

      <!-- Formulaire -->
      <form id="form" class="w-full max-w-md space-y-4">
        <input type="text" placeholder="Nom" class="w-full bg-gray-100 p-3 rounded outline-none" required />
        <input type="text" placeholder="Prenom" class="w-full bg-gray-100 p-3 rounded outline-none" required />
        <input type="text" placeholder="Lieu de naissance" class="w-full bg-gray-100 p-3 rounded outline-none" required />

        <!-- Champ Date avec icône -->
        <div class="relative">
          <input type="date" placeholder="Date de naissance" class="w-full bg-gray-100 p-3 rounded outline-none" required />
          <img src="https://cdn-icons-png.flaticon.com/512/747/747310.png" class="w-5 h-5 absolute top-3 right-4" alt="calendar" />
        </div>

        <input type="tel" placeholder="Numero de telephone" class="w-full bg-gray-100 p-3 rounded outline-none" required />
        <input type="email" placeholder="Email" class="w-full bg-gray-100 p-3 rounded outline-none" required />

        <!-- Select Sexe -->
        <select class="w-full bg-gray-100 p-3 rounded outline-none" required>
          <option value="">Sexe</option>
          <option value="Homme">Homme</option>
          <option value="Femme">Femme</option>
        </select>

        <!-- Select statut -->
        <select class="w-full bg-gray-100 p-3 rounded outline-none" required>
          <option value="">Statut</option>
          <option value="celib">Célibataire</option>
          <option value="mariesansenfts">Marié(e) sans enfant</option>
          <option value="celibavecenfts">Célibataire avec enfants</option>
          <option value="marieavecenfts">Marié(e) avec enfants</option>
        </select>

        <!-- Select nationalité -->
        <select id="nationalite" class="w-full bg-gray-100 p-3 rounded outline-none" required>
          <option value="">Nationalité</option>
          <option value="camerounais">Camerounais(e)</option>
          <option value="gabonais">Gabonais(e)</option>
          <option value="ivoirien">Ivoirienne(e)</option>
          <option value="tchadien">Tchadien(ne)</option>
          <option value="guineen">Guinéen(ne)</option>
          <option value="congolais">Congolais(e)</option>
          <option value="kinois">Kinois(e)</option>
          <option value="centrafricain">Centrafricain(e)</option>
        </select>

         <!-- Select Cycle -->
         <select class="w-full bg-gray-100 p-3 rounded outline-none" required>
          <option value="">Cycle</option>
          <option value="bts">BTS</option>
          <option value="licence">Licence</option>
          <option value="master">Master</option>
        </select>

         <!-- Select Niveau -->
         <select class="w-full bg-gray-100 p-3 rounded outline-none" required>
          <option value="">Niveau</option>
          <option value="un">1</option>
          <option value="deux">2</option>
          <option value="trois">3</option>
        </select>

        <!-- Select Spécialité -->
        <select class="w-full bg-gray-100 p-3 rounded outline-none" required>
          <option value="">Spécialité</option>
          <option value="Homme">Homme</option>
          <option value="Femme">Femme</option>
        </select>

        <!-- Bouton inscription -->
        <button type="submit" class="w-full bg-blue-700 text-white p-3 rounded hover:bg-blue-800">S'inscrire</button>

        <!-- Lien connexion -->
        <p class="text-center text-sm mt-4">
          Déjà inscrit ? <a href="#" class="text-blue-600 hover:underline">Se connecter</a>
        </p>
      </form>

      <!-- JS simple pour validation -->
      <script>
        document.getElementById("form").addEventListener("submit", function (e) {
          e.preventDefault();
          alert("Inscription soumise avec succès !");
          // Vous pouvez ensuite envoyer les données avec fetch ou AJAX ici
        });

        // Récupère la liste déroulante par son id
          const select = document.getElementById("nationalite");

        // Récupère toutes les options sauf la première (placeholder)
        const options = Array.from(select.options).slice(1);

        // Trie les options par ordre alphabétique (en se basant sur le texte affiché)
        options.sort((a, b) => a.text.localeCompare(b.text, 'fr', { sensitivity: 'base' }));

        // Supprime toutes les options sauf la première
        select.length = 1;

        // Ajoute les options triées
        options.forEach(option => select.add(option));
      </script>
    </div>
  </body>
</html>