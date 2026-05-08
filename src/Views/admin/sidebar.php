<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\admin\sidebar.php -->
<aside class="w-64 bg-[#062B41] text-white flex flex-col min-h-screen">
    <!-- Logo and Header -->
    <div class="p-6 border-b border-white">
        <!-- Logo on its own row -->
        <div class="flex justify-center mb-3">
            <!-- Replace src with your actual logo image if needed -->
            <img src="/img/Original logo.svg" alt="GetAroundMobility Logo" class="h-10 w-auto">
        </div>
        <!-- Title on the next row -->
        <h1 class="font-bold text-xl text-center">Admin Dashboard</h1>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-1">
                
        <a href="/admin/orders" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            Orders
        </a>

        <a href="/admin/rental-prices" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 10c-4.41 0-8-1.79-8-4V6c0-2.21 3.59-4 8-4s8 1.79 8 4v8c0 2.21-3.59 4-8 4z" />
            </svg>
            Rental Prices
        </a>

        <a href="/admin/reservations" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Reservations
        </a>

        <a href="/admin/locations" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2h5m6 0v-2a2 2 0 00-2-2H9a2 2 0 00-2 2v2m6 0a2 2 0 002-2v-2a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2z" />
            </svg>
            Locations (Hotels & Pickups)
        </a>

        <a href="/admin/featured-products" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
            </svg>
            Featured Products
        </a>

        <a href="/admin/orders/new" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            New Booking
        </a>

        <a href="/admin/categories" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
            </svg>
            Category
        </a>

        <a href="/admin/scooters-for-sale" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            Scooters For Sale
        </a>

        <a href="/admin/products" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-4a2 2 0 00-2 2v2"></path>
            </svg>
            Products
        </a>

        <a href="/admin/product-variations" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7H9"></path>
            </svg>
            Product Variation
        </a>

        <a href="/admin/scooters" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            Scooter Inventory
        </a>

        <a href="/admin/testimonials" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
            </svg>
            Testimonials
        </a>

        <a href="/admin/tips-troubleshooting" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4v-4z"></path>
            </svg>
            Tips & Troubleshooting
        </a>

        <?php
        // Only show Admins link for super admin and admin roles
        $role = strtolower($_SESSION['admin_role'] ?? '');
        if ($role === 'superadmin' || $role === 'admin'): ?>
            <a href="/admin/admins" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                Admins
            </a>
        <?php endif; ?>

        <a href="/admin/customers" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM6 9a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            Customers
        </a>

        <a href="/admin/logout" class="flex items-center py-2 px-4 rounded hover:bg-white hover:text-black transition-colors font-semibold text-base">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            Logout
        </a>
    </nav>
</aside>