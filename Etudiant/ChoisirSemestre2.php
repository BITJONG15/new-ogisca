<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mes Notes - OGISCA</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
  />
  <style>
    /* Animation ouverture sidebar */
    @keyframes slideFadeIn {
      0% {
        transform: translateX(-100%);
        opacity: 0;
      }
      100% {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .slide-fade-in {
      animation: slideFadeIn 0.3s ease forwards;
    }

    /* Sidebar gradient & shadow */
    #sidebar {
      background: linear-gradient(135deg, #283593, #3949ab);
      box-shadow: 2px 0 10px rgba(0,0,0,0.3);
      color: white;
    }

    /* Lien actif */
    .active-link {
      background-color: rgba(255 255 255 / 0.15);
      border-left: 4px solid #fbbf24; /* jaune/or */
      font-weight: 600;
      color: #fbbf24 !important;
    }

    /* Lien sidebar */
    nav a {
      transition: all 0.3s ease;
      outline-offset: 2px;
      display: flex;
      align-items: center;
      gap: 0.75rem; /* gap-3 */
      padding: 0.5rem 0.75rem; /* py-2 px-3 */
      border-radius: 0.375rem; /* rounded-md */
    }
    nav a:hover,
    nav a:focus {
      background-color: rgba(255 255 255 / 0.1);
      transform: translateX(5px);
      color: #fbbf24 !important;
      outline: none;
    }

    nav a:focus-visible {
      outline: 2px solid #fbbf24;
      box-shadow: 0 0 8px #fbbf24;
    }

    /* Overlay */
    #sidebar-overlay {
      background: rgba(0, 0, 0, 0.3);
    }

    /* Burger animation */
    button#burger-btn.open span:nth-child(1) {
      transform: rotate(45deg) translate(5px, 5px);
    }
    button#burger-btn.open span:nth-child(2) {
      opacity: 0;
    }
    button#burger-btn.open span:nth-child(3) {
      transform: rotate(-45deg) translate(5px, -5px);
    }

    /* Header sidebar title */
    #sidebar-header {
      height: 4rem; /* 16 */
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.125rem; /* text-lg */
      border-bottom: 1px solid #1e40af; /* blue-800 */
      user-select: none;
      padding-left: 1.5rem; /* px-6 */
      padding-right: 1.5rem;
    }
  </style>
</head>
<body class="bg-gray-100 font-sans">

  <!-- Overlay mobile sidebar -->
  <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-30 z-40 hidden md:hidden" tabindex="-1" aria-hidden="true"></div>

  <!-- Sidebar -->
  <div
    id="sidebar"
    class="fixed top-0 left-0 h-full w-64 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50"
    aria-label="Sidebar menu"
  >
    <div id="sidebar-header"><h1>OGISCA</h1></div>
    <nav class="flex flex-col mt-4 space-y-2 px-4" role="menu">
      <a
        href="AccueilEtudiant.php"
        class="active-link"
        role="menuitem"
        tabindex="0"
        aria-current="page"
      ><i class="fas fa-home"></i> Accueil</a>
      <a
        href="gestionRequetes.php"
        role="menuitem"
        tabindex="-1"
      ><i class="fas fa-envelope"></i> Requête</a>
      <a
        href="ChoisierSemestre1.php"
        role="menuitem"
        tabindex="-1"
      ><i class="fas fa-sticky-note"></i> Note</a>
      <a
        href="ProfilEtudiant.php"
        role="menuitem"
        tabindex="-1"
      ><i class="fas fa-user"></i> Profil</a>
    </nav>
  </div>

  <!-- CONTENU PRINCIPAL -->
  <div class="md:ml-64 pt-16">

    <!-- HEADER -->
    <header class="flex items-center justify-between p-4 bg-white shadow-md relative z-20">
      <div class="flex items-center gap-2">
        <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
          <span class="text-white text-xl">👤</span>
        </div>
        <div>
          <p class="font-semibold">Prince Fouelefack</p>
          <p class="text-sm text-gray-500">A6GY88</p>
        </div>
      </div>

      <!-- BOUTON BURGER -->
      <button id="burger-btn" class="flex flex-col justify-between w-6 h-6 md:hidden z-50 focus:outline-none group" aria-label="Ouvrir menu">
        <span class="block w-full h-0.5 bg-black transition-transform duration-300 ease-in-out"></span>
        <span class="block w-full h-0.5 bg-black transition-opacity duration-300 ease-in-out"></span>
        <span class="block w-full h-0.5 bg-black transition-transform duration-300 ease-in-out"></span>
      </button>
    </header>

    <!-- CONTENU -->
    <main class="p-4">
      <h1 class="text-2xl font-bold mb-2">Mes Notes</h1>
      <p class="text-gray-600 mb-4">Sélectionnez une matière pour voir vos notes détaillées.</p>

      <!-- Boutons semestre -->
      <div class="flex justify-center gap-4 mb-6">
        <a href="ChoisierSemestre1.php" class="bg-blue-100  text-blue-700 font-semibold px-4 py-2 rounded-md">Semestre 1</a>
        <a href="ChoisirSemestre2.php" class="bg-blue-700 text-white font-semibold px-4 py-2 rounded-md">Semestre 2</a>
      </div>

      <!-- Cartes -->
      <div class="space-y-4">
        <!-- Carte 1 -->
        <div class="bg-blue-100 rounded-xl shadow p-4 transition-transform transform hover:scale-105 duration-300">
          <h2 class="font-semibold text-lg">Mathématiques Appliquées</h2>
          <p class="text-sm text-gray-600 mt-1">MATH-301</p>
          <div class="mt-4">
            <a href="ConsulterLesNotesGlobales.php" class="block bg-green-600 text-white text-center font-semibold py-2 rounded-md hover:bg-green-700 transition">Voir ma Note →</a>
          </div>
        </div>

        <!-- Carte 2 -->
        <div class="bg-blue-100 rounded-xl shadow p-4 transition-transform transform hover:scale-105 duration-300">
          <h2 class="font-semibold text-lg">Algorithmique Avancée</h2>
          <p class="text-sm text-gray-600 mt-1">ALGO-M410</p>
          <div class="mt-4">
            <div class="bg-gray-500 text-white text-center font-semibold py-2 rounded-md">Notes non disponibles</div>
          </div>
        </div>

        <!-- Carte 3 -->
        <div class="bg-blue-100 rounded-xl shadow p-4 transition-transform transform hover:scale-105 duration-300">
          <h2 class="font-semibold text-lg">Marketing Digital</h2>
          <p class="text-sm text-gray-600 mt-1">MKD-M412</p>
          <div class="mt-4">
            <div class="bg-gray-500 text-white text-center font-semibold py-2 rounded-md">Notes non disponibles</div>
          </div>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="bg-blue-900 text-white p-4 mt-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div>
          <p class="font-bold mb-2">SUIVEZ-NOUS</p>
          <div class="flex space-x-4 text-xl">
            <span>📘</span>
            <span>📸</span>
          </div>
        </div>
        <div>
          <p class="font-bold mb-2">NOUS CONTACTER</p>
          <p>Bâtiment Administratif,</p>
          <p>Campus, YAOUNDE, P.O BOX….</p>
          <p>Téléphone: +237 000 00 00 00</p>
          <p>Email: exempl@gmail.com</p>
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
  </div>

  <!-- JavaScript -->
  <script>
    const sidebar = document.getElementById("sidebar");
    const burgerBtn = document.getElementById("burger-btn");
    const overlay = document.getElementById("sidebar-overlay");

    function openSidebar() {
      sidebar.classList.remove("-translate-x-full");
      sidebar.classList.add("slide-fade-in");
      overlay.classList.remove("hidden");
      overlay.setAttribute("aria-hidden", "false");
    }

    function closeSidebar() {
      sidebar.classList.add("-translate-x-full");
      sidebar.classList.remove("slide-fade-in");
      overlay.classList.add("hidden");
      overlay.setAttribute("aria-hidden", "true");
    }

    burgerBtn.addEventListener("click", () => {
      if (sidebar.classList.contains("-translate-x-full")) {
        openSidebar();
      } else {
        closeSidebar();
      }
      burgerBtn.classList.toggle("open");
    });

    overlay.addEventListener("click", closeSidebar);

    window.addEventListener("resize", () => {
      if (window.innerWidth >= 768) {
        sidebar.classList.remove("-translate-x-full");
        sidebar.classList.remove("slide-fade-in");
        overlay.classList.add("hidden");
        overlay.setAttribute("aria-hidden", "true");
        burgerBtn.classList.remove("open");
      } else {
        sidebar.classList.add("-translate-x-full");
      }
    });

    // Navigation clavier sidebar
    const links = document.querySelectorAll("#sidebar nav a");
    let currentIndex = 0;
    links.forEach((link, index) => {
  link.addEventListener("keydown", (e) => {
    if (e.key === "ArrowDown") {
      e.preventDefault();
      currentIndex = (index + 1) % links.length;
      links[currentIndex].focus();
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      currentIndex = (index - 1 + links.length) % links.length;
      links[currentIndex].focus();
    }
  });
});
       </script>
     </body> 
  </html>
