<?php
// topbar_enseignant.php
$photo_url = !empty($photo) ? "../uploads/" . $photo : 'https://www.svgrepo.com/show/382106/user-circle.svg';
?>
<div class="flex-1 flex flex-col lg:ml-0">
    <!-- Topbar EXACTEMENT comme fourni -->
    <header class="topbar flex items-center justify-between px-6 py-4 sticky top-0 z-40">
        <div class="flex items-center space-x-4">
            <button id="btnMenuToggle" class="lg:hidden text-gray-700 focus:outline-none" aria-label="Toggle menu">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="font-semibold text-blue-700 uppercase flex-1 text-center text-lg">
                INSTITUT NATIONAL DE LA JEUNESSE ET DES SPORTS
            </div>
        </div>
        
        <div class="user-profile">
            <div class="flex items-center space-x-3 cursor-pointer">
                <div class="text-right hidden md:block">
                    <div class="text-gray-700 font-semibold">
                        Bonjour, <?= htmlspecialchars($prenom) ?> <?= htmlspecialchars($nom) ?>
                    </div>
                    <div class="text-gray-500 text-sm">
                        Matricule : <?= htmlspecialchars($matricule) ?>
                    </div>
                </div>
                <div class="relative">
                    <img src="<?= $photo_url ?>" alt="Profil" class="w-12 h-12 rounded-full border-2 border-white shadow-md" />
                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></span>
                </div>
            </div>
            
            <div class="user-dropdown">
                <div class="flex items-center space-x-3 pb-3 border-b border-gray-100">
                    <img src="<?= $photo_url ?>" alt="Profil" class="w-14 h-14 rounded-full border-2 border-gray-200" />
                    <div>
                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($prenom) ?> <?= htmlspecialchars($nom) ?></div>
                        <div class="text-sm text-gray-500"><?= htmlspecialchars($matricule) ?></div>
                    </div>
                </div>
                <div class="py-3 space-y-2">
                    <a href="profil.php" class="flex items-center py-2 px-3 text-gray-700 hover:bg-blue-50 rounded-lg">
                        <i class="fas fa-user-circle mr-3 text-blue-500"></i>
                        <span>Mon profil</span>
                    </a>
                    <a href="#" class="flex items-center py-2 px-3 text-gray-700 hover:bg-blue-50 rounded-lg">
                        <i class="fas fa-cog mr-3 text-blue-500"></i>
                        <span>Paramètres</span>
                    </a>
                </div>
                <div class="pt-3 border-t border-gray-100">
                    <a href="../logout.php" class="flex items-center py-2 px-3 text-red-600 hover:bg-red-50 rounded-lg">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
            </div>
        </div>
    </header>