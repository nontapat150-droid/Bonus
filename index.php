<?php
// index.php (Main Home Page Router)
require_once 'config/db.php';
require_once 'config/auth.php';

requireLogin();
$user = getCurrentUser();

// Simple router to include the correct dashboard or load the main menu
$page = $_GET['page'] ?? 'home';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Business Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
    </style>
</head>
<body class="text-gray-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar Layout -->
    <?php include 'views/layouts/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-4 sm:p-8">
        
        <?php if ($page === 'home'): ?>
            <!-- Home Page Menu Cards -->
            <div class="mb-8 text-center sm:text-left">
                <h1 class="text-3xl font-extrabold text-gray-900">Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
                <p class="mt-2 text-gray-600">Select a module to continue.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                
                <!-- Module 1: Oil & Vehicle -->
                <a href="index.php?page=oil" class="glass-card rounded-xl p-6 flex flex-col items-center text-center cursor-pointer block">
                    <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-4 text-2xl">
                        ⛽
                    </div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Oil & Vehicles</h2>
                    <p class="text-gray-500 text-sm">
                        <?php echo hasRole(['technician']) ? 'Submit your fuel receipts and update mileage.' : 'View fuel reports, charts, and verification images.'; ?>
                    </p>
                </a>

                <!-- Module 3: Smart Dispatch -->
                <a href="index.php?page=dispatch" class="glass-card rounded-xl p-6 flex flex-col items-center text-center cursor-pointer block">
                    <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-4 text-2xl">
                        🗺️
                    </div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Smart Dispatch</h2>
                    <p class="text-gray-500 text-sm">
                        <?php echo hasRole(['technician']) ? 'View your daily route and tasks.' : 'Manage routes, auto-assign quotas, and import jobs.'; ?>
                    </p>
                </a>

                <!-- Module 2: Inventory (Admin & Super Admin ONLY) -->
                <?php if (hasRole(['admin', 'super_admin'])): ?>
                <a href="index.php?page=inventory" class="glass-card rounded-xl p-6 flex flex-col items-center text-center cursor-pointer block">
                    <div class="w-16 h-16 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mb-4 text-2xl">
                        📦
                    </div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Inventory System</h2>
                    <p class="text-gray-500 text-sm">Manage stock, scan barcodes, and export outbound reports.</p>
                </a>
                <?php endif; ?>

                <!-- User Management (Super Admin ONLY) -->
                <?php if (hasRole('super_admin')): ?>
                <a href="index.php?page=users" class="glass-card rounded-xl p-6 flex flex-col items-center text-center cursor-pointer block">
                    <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mb-4 text-2xl">
                        👥
                    </div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">User Management</h2>
                    <p class="text-gray-500 text-sm">Add, edit, or remove users and manage roles.</p>
                </a>
                <?php endif; ?>

            </div>

        <?php else: ?>
            
            <?php 
            // Router mapping
            $routes = [
                'oil' => hasRole(['technician']) ? 'views/modules/oil_form.php' : 'views/modules/oil_report.php',
                'oil_test_form' => 'views/modules/oil_form.php', // For admin testing
                'dispatch' => 'views/modules/dispatch_map.php',
                'inventory' => 'views/modules/inventory_app.php',
                'users' => 'views/modules/user_settings.php'
            ];

            if (array_key_exists($page, $routes) && file_exists($routes[$page])) {
                include $routes[$page];
            } else {
                echo '<div class="glass-card p-8 rounded-2xl text-center">
                        <h2 class="text-2xl font-bold text-red-600 mb-4">Under Construction</h2>
                        <p class="text-gray-600 mb-4">The module view for <strong>' . htmlspecialchars(ucfirst($page)) . '</strong> is not yet available.</p>
                      </div>';
            }
            ?>

        <?php endif; ?>

        <!-- Footer inside main -->
        <footer class="mt-8 text-center text-sm text-gray-500 pb-4">
            &copy; <?php echo date('Y'); ?> Smart Business Suite
        </footer>
    </main>

</body>
</html>
