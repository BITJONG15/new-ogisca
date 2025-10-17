<?php
// sidebar_enseignant.php
?>
<!-- Overlay for mobile menu -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar EXACTEMENT comme fourni -->
<div class="sidebar w-64 h-screen text-white p-6 flex flex-col fixed lg:relative z-50">
    <div class="flex items-center gap-3 mb-8">
        <img src="../admin/logo simple sans fond.png" class="w-10 h-10 rounded" alt="logo" />
        <h1 class="text-xl font-bold">OGISCA - INJS</h1>
        <button class="lg:hidden ml-auto text-2xl" id="closeSidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <nav class="flex-grow">
        <ul class="space-y-2 mt-8 text-base font-medium">
            <li class="nav-item active">
                <a href="index.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-home w-6 text-center mr-3"></i>
                    <span>DASHBOARD</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="mes_ec.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-book w-6 text-center mr-3"></i>
                    <span>MES EC</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="requetes_traitees.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-tasks w-6 text-center mr-3"></i>
                    <span>REQUÊTES</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="profil.php" class="flex items-center px-4 py-3">
                    <i class="fas fa-user w-6 text-center mr-3"></i>
                    <span>PROFIL</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="pt-6 border-t border-white border-opacity-20 mt-auto">
        <a href="../logout.php" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-500 hover:bg-opacity-20 rounded-lg">
            <i class="fas fa-sign-out-alt w-6 text-center mr-3"></i>
            <span>Déconnexion</span>
        </a>
    </div>
</div>