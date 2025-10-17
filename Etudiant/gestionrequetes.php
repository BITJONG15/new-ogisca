<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Requêtes Étudiant - OGISCA</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
  />
</head>

<body class="bg-gray-100 font-sans">
  <div class="flex min-h-screen flex-col md:flex-row">
    <!-- Sidebar -->
<aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-[#283593] text-white p-6 space-y-6 transform -translate-x-full transition-transform duration-300 ease-in-out md:translate-x-0 md:static md:flex md:flex-col z-50">
    <h1 class="text-2xl font-bold mb-6">OGISCA</h1>
      <nav class="flex flex-col space-y-4 text-sm">
        <a href="AccueilEtudiant.php" class="flex items-center gap-3 hover:opacity-80"><i class="fas fa-home"></i> Accueil</a>
        <a href="gestionrequetes.php" class="flex items-center gap-3 hover:opacity-80"><i class="fas fa-envelope"></i> Requête</a>
        <a href="ChoisierSemestre1.php" class="flex items-center gap-3 hover:opacity-80"><i class="fas fa-sticky-note"></i> Note</a>
        <a href="ProfilEtudiant.php" class="flex items-center gap-3 hover:opacity-80"><i class="fas fa-user"></i> Profil</a>
        
      </nav>
    </aside>

    <!-- Conteneur principal -->
    <div class="flex-1 flex flex-col">
      <!-- Topbar -->
      <header class="bg-white shadow px-4 py-3 flex justify-between items-center sticky top-0 z-30">
        <button id="burger-btn" class="md:hidden text-[#283593] text-2xl"><i class="fas fa-bars"></i></button>
        <div class="flex items-center gap-4">
          <img src="https://www.svgrepo.com/show/384674/account-avatar-profile-user-11.svg" class="w-10 h-10 rounded-full object-cover" alt="Avatar" />
          <div class="text-sm">
            <p class="text-gray-500 font-medium">Prince Fouelefack</p>
            <p class="text-xs font-semibold text-gray-700">ADG1TIS</p>
          </div>
        </div>
        <i class="fa-solid fa-bell text-gray-600 text-xl hover:text-[#283593] cursor-pointer"></i>
      </header>

      <!-- Contenu principal -->
      <main class="p-6 space-y-6">
        <!-- Titre -->
        <h1 class="text-xl font-semibold text-gray-800">Requêtes Étudiant</h1>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div class="bg-white shadow rounded-xl p-6 flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Requêtes en cours</p>
              <h2 class="text-3xl font-bold text-[#283593]">3</h2>
            </div>
            <i class="fas fa-hourglass-half text-4xl text-[#283593] opacity-80"></i>
          </div>

          <div class="bg-white shadow rounded-xl p-6 flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Requêtes traitées</p>
              <h2 class="text-3xl font-bold text-[#283593]">12</h2>
            </div>
            <i class="fas fa-check-circle text-4xl text-[#283593] opacity-80"></i>
          </div>
        </div>

        <!-- Bouton Ajouter une requête -->
        <div class="flex justify-end">
          <a href="AjouterRequetes.php"
            class="bg-[#283593] hover:bg-[#1a237e] text-white px-6 py-3 rounded-lg shadow-md transition-all duration-300 flex items-center gap-2">
            <i class="fas fa-plus"></i> Ajouter une requête
          </a>
        </div>
      </main>
    </div>
  </div>
</body>
<script>
  const burgerBtn = document.getElementById("burger-btn");
  const sidebar = document.getElementById("sidebar");

  burgerBtn.addEventListener("click", () => {
    sidebar.classList.toggle("-translate-x-full");
  });

  // Bonus : clique en dehors pour fermer (optionnel)
  window.addEventListener("click", (e) => {
    if (
      !sidebar.contains(e.target) &&
      !burgerBtn.contains(e.target) &&
      window.innerWidth < 768
    ) {
      sidebar.classList.add("-translate-x-full");
    }
  });
</script>

</html>
