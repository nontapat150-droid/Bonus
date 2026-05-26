<?php
// views/layouts/sidebar.php
?>
<aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col h-full sticky top-0" style="height: 100vh;">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-indigo-600">SmartSuite</h2>
        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></p>
        <span class="inline-block mt-2 px-2 py-1 bg-indigo-100 text-indigo-800 text-xs font-semibold rounded-full">
            <?php echo htmlspecialchars(ucfirst($user['role'] ?? 'Guest')); ?>
        </span>
    </div>
    
    <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
        <a href="index.php?page=home" class="flex items-center px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 rounded-lg transition-colors <?php echo ($page === 'home') ? 'bg-indigo-50 text-indigo-600 font-semibold' : ''; ?>">
            <span class="mr-3 text-xl">🏠</span> Home
        </a>

        <!-- Module: Oil & Vehicles -->
        <a href="index.php?page=oil" class="flex items-center px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition-colors <?php echo ($page === 'oil') ? 'bg-blue-50 text-blue-600 font-semibold' : ''; ?>">
            <span class="mr-3 text-xl">⛽</span> Oil & Vehicles
        </a>

        <!-- Module: Smart Dispatch -->
        <a href="index.php?page=dispatch" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-600 rounded-lg transition-colors <?php echo ($page === 'dispatch') ? 'bg-green-50 text-green-600 font-semibold' : ''; ?>">
            <span class="mr-3 text-xl">🗺️</span> Smart Dispatch
        </a>

        <!-- Module: Inventory (Admin & Super Admin ONLY) -->
        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <a href="index.php?page=inventory" class="flex items-center px-4 py-3 text-gray-700 hover:bg-purple-50 hover:text-purple-600 rounded-lg transition-colors <?php echo ($page === 'inventory') ? 'bg-purple-50 text-purple-600 font-semibold' : ''; ?>">
            <span class="mr-3 text-xl">📦</span> Inventory
        </a>
        <?php endif; ?>

        <!-- Module: User Management (Super Admin ONLY) -->
        <?php if (hasRole('super_admin')): ?>
        <a href="index.php?page=users" class="flex items-center px-4 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors <?php echo ($page === 'users') ? 'bg-red-50 text-red-600 font-semibold' : ''; ?>">
            <span class="mr-3 text-xl">👥</span> Users
        </a>
        <?php endif; ?>
    </nav>

    <div class="p-4 border-t border-gray-200">
        <a href="logout.php" class="flex items-center justify-center w-full px-4 py-2 bg-gray-100 text-gray-700 hover:bg-red-500 hover:text-white rounded-lg transition-colors font-medium">
            Logout
        </a>
    </div>
</aside>

<!-- Mobile Menu Button (Visible on small screens) -->
<div class="md:hidden bg-white border-b border-gray-200 p-4 flex justify-between items-center sticky top-0 z-40">
    <h2 class="text-xl font-bold text-indigo-600">SmartSuite</h2>
    <button id="mobileMenuBtn" class="text-gray-500 hover:text-gray-700 focus:outline-none">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
</div>

<!-- Mobile Menu Dropdown -->
<div id="mobileMenu" class="md:hidden hidden bg-white border-b border-gray-200 absolute w-full z-30 shadow-lg">
    <nav class="flex flex-col p-4 space-y-2">
        <a href="index.php?page=home" class="px-4 py-2 text-gray-700 hover:bg-indigo-50 rounded-lg">🏠 Home</a>
        <a href="index.php?page=oil" class="px-4 py-2 text-gray-700 hover:bg-blue-50 rounded-lg">⛽ Oil & Vehicles</a>
        <a href="index.php?page=dispatch" class="px-4 py-2 text-gray-700 hover:bg-green-50 rounded-lg">🗺️ Smart Dispatch</a>
        <?php if (hasRole(['admin', 'super_admin'])): ?>
            <a href="index.php?page=inventory" class="px-4 py-2 text-gray-700 hover:bg-purple-50 rounded-lg">📦 Inventory</a>
        <?php endif; ?>
        <?php if (hasRole('super_admin')): ?>
            <a href="index.php?page=users" class="px-4 py-2 text-gray-700 hover:bg-red-50 rounded-lg">👥 Users</a>
        <?php endif; ?>
        <hr class="my-2 border-gray-200">
        <a href="logout.php" class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">Logout</a>
    </nav>
</div>

<script>
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
        document.getElementById('mobileMenu').classList.toggle('hidden');
    });
</script>
