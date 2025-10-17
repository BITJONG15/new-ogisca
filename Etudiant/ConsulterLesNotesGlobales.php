<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Application Notes - Responsive Sidebar</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans bg-gray-100">

  <!-- Overlay mobile -->
  <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40 md:hidden"></div>

  <!-- SIDEBAR RESPONSIVE -->
  <aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-[#1a237e] text-white z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="h-16 flex items-center justify-center font-bold text-lg border-b border-blue-800">
      Menu
    </div>
    <nav class="flex flex-col p-4 space-y-3">
      <a href="AccueilEtudiant.php" class="hover:bg-blue-800 py-2 px-3 rounded transition">Accueil</a>
      <a href="gestionrequetes.php" class="hover:bg-blue-800 py-2 px-3 rounded transition">Requête</a>
      <a href="ChoisierSemestre1.php" class="hover:bg-blue-800 py-2 px-3 rounded transition">Notes</a>
      <a href="ProfilEtudiant.php" class="hover:bg-blue-800 py-2 px-3 rounded transition">Profil</a>
      
    </nav>
  </aside>

  <!-- BARRE SUPÉRIEURE FIXE -->
  <header class="fixed top-0 left-0 right-0 h-16 bg-white shadow-md flex items-center justify-between px-4 z-30 md:ml-64">
    <!-- Burger menu (mobile) -->
    <button id="burger-btn" class="md:hidden">
      <img src="https://cdn-icons-png.flaticon.com/512/2311/2311524.png" alt="Menu" class="w-6 h-6" />
    </button>
    <div class="flex items-center gap-3">
      <img src="logo-universite.png" alt="Logo" class="h-6 w-auto" />
      <span class="font-semibold text-gray-700">Université XYZ</span>
    </div>
    <div class="text-sm text-gray-600">
      Année académique : <strong>2024 - 2025</strong>
    </div>
  </header>

  <!-- CONTENU PRINCIPAL -->
  <main class="pt-20 md:ml-64 px-4 pb-10">
    <!-- Infos utilisateur -->
    <div class="flex gap-3 items-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="User Icon" class="w-10 h-10 rounded-full" />
      <div>
        <p class="font-bold">Prince Fouelefack</p>
        <p class="text-sm text-gray-500">A6GY88</p>
      </div>
    </div>

    <!-- Informations cours -->
    <h2 class="text-xl font-semibold mb-1">Mathématiques Appliquées</h2>
    <div class="flex flex-wrap justify-between text-sm text-gray-600 mb-4">
      <span>👨‍🏫 Prof. Alain Mbarga</span>
      <span>🧪 MATH-301</span>
      <span>🏫 Classe : CJA1</span>
    </div>

    <!-- Tableau des notes -->
    <div class="overflow-x-auto bg-white shadow rounded-lg">
      <table class="min-w-full text-sm text-left border">
        <thead class="bg-gray-200 text-gray-700">
          <tr>
            <th class="p-2 border">Matricule</th>
            <th class="p-2 border">Noms & Prénoms</th>
            <th class="p-2 border">Re(s)</th>
            <th class="p-2 border">Sexe</th>
            <th class="p-2 border">Statut</th>
            <th class="p-2 border">CC/20</th>
            <th class="p-2 border">TPE</th>
          </tr>
        </thead>
        <tbody id="table-body" class="text-gray-900"></tbody>
      </table>
    </div>
  </main>

  <!-- Script : injection des données -->
  <script>
    const data = [
      ["4CJ002", "AKELE NDONG Ludivine Chams", "Non", "F", "AL", 14, 13],
      ["4CJ003", "YOULOUKA OLINGA Stephane", "Non", "H", "Ex", 15, 13],
      ["4CJ004", "BASEMBAKA Samuel DJANYI", "Non", "H", "AL", 17, 14],
      ["4CJ005", "DOME Lionel Blondin", "Non", "H", "AL", 13, 14],
      ["4CJ006", "DOURMANI MBITI José", "Non", "H", "AL", 16, 13],
      ["4CJ007", "EDZIMBI M'WOGO Catherine", "Non", "F", "AL", 15, 12],
      ["4CJ008", "EGUIDI BONTSEBE Yann Béranger", "Non", "H", "Ex", 13, 12],
      ["4CJ009", "GUEDIA TONFACK Marcelle", "Non", "F", "AL", 15, 13],
    ];

    const tableBody = document.getElementById("table-body");
    data.forEach(row => {
      const tr = document.createElement("tr");
      row.forEach(cell => {
        const td = document.createElement("td");
        td.className = "border p-2";
        td.textContent = cell;
        tr.appendChild(td);
      });
      tableBody.appendChild(tr);
    });
  </script>

  <!-- Script : gestion sidebar -->
  <script>
    const sidebar = document.getElementById("sidebar");
    const burgerBtn = document.getElementById("burger-btn");
    const overlay = document.getElementById("sidebar-overlay");

    burgerBtn.addEventListener("click", () => {
      sidebar.classList.toggle("-translate-x-full");
      overlay.classList.toggle("hidden");
    });

    overlay.addEventListener("click", () => {
      sidebar.classList.add("-translate-x-full");
      overlay.classList.add("hidden");
    });

    window.addEventListener("resize", () => {
      if (window.innerWidth >= 768) {
        sidebar.classList.remove("-translate-x-full");
        overlay.classList.add("hidden");
      } else {
        sidebar.classList.add("-translate-x-full");
      }
    });
  </script>
</body>
</html>
