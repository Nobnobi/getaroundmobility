<?php
// At the top of your form view
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


$roleLabels = [
    'superadmin' => 'Super Admin',
    'admin' => 'Admin',
    'staff' => 'Staff',
    'partner' => 'Partner'
];
$roleKey = $_SESSION['admin_role'] ?? 'admin';
$roleLabel = $roleLabels[$roleKey] ?? ucfirst($roleKey);

$statusOptions = ['pending', 'approved', 'paid', 'completed', 'cancelled'];
$currentQuery = $_GET;
unset($currentQuery['page']);

$sortByCurrent = $sortBy ?? 'order_id';
$sortDirCurrent = strtolower($sortDir ?? 'desc') === 'asc' ? 'asc' : 'desc';

$buildSortLink = function (string $column) use ($currentQuery, $sortByCurrent, $sortDirCurrent) {
    $nextDir = ($sortByCurrent === $column && $sortDirCurrent === 'asc') ? 'desc' : 'asc';
    $query = array_merge($currentQuery, ['sort_by' => $column, 'sort_dir' => $nextDir, 'page' => 1]);
    return '?' . http_build_query($query);
};

$buildPageLink = function (int $pageNumber) use ($currentQuery) {
    $query = array_merge($currentQuery, ['page' => $pageNumber]);
    return '?' . http_build_query($query);
};

$formatDateTime = function ($value) {
    if (empty($value)) {
        return '';
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return (string)$value;
    }
    return date('M j, Y g:i A', $ts);
};

?>


<body class="bg-gray-100 min-h-screen flex">
    <div id="order-action-loading-overlay" class="fixed inset-0 z-[60] hidden items-center justify-center bg-[#062B41]/70 px-6">
        <div class="w-full max-w-sm rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
            <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-gray-200 border-t-[#0086C9]"></div>
            <h2 id="order-action-loading-title" class="mt-4 text-xl font-bold text-[#062B41]">Processing order</h2>
            <p id="order-action-loading-copy" class="mt-2 text-sm text-gray-500">Please wait while the order status is being updated.</p>
        </div>
    </div>

    <!-- <?php echo($_SESSION['admin_role']); ?> -->
    <!-- Main Content -->
    <div class="flex-1 flex flex-col">
        <!-- Topbar -->
        <header class="bg-white shadow p-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">Orders</h1>
            <div class="flex items-center gap-4">
                <a href="/admin/orders/analytics" class="px-4 py-2 bg-[#0086C9] text-white rounded-md font-semibold hover:bg-[#005a99] transition-colors duration-150">View Analytics</a>
                <span class="text-gray-600">Welcome, <?= htmlspecialchars($roleLabel) ?></span>
            </div>
        </header>

        <!-- Orders Table -->


        <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-4 space-x-2 items-center select-none">
            <?php
            $window = 1; // pages before/after current
            $showFirst = 1;
            $showLast = $totalPages;
            $dots = false;
            $sidebarColor = '#062B41';
            $activeClass = 'bg-[#062B41] text-white font-bold shadow';
            $inactiveClass = 'bg-white text-[#062B41] hover:bg-[#062B41] hover:text-white transition-colors duration-150';
            $arrowClass = 'bg-white text-[#062B41] hover:bg-[#062B41] hover:text-white transition-colors duration-150';
            $pillClass = 'px-3 py-1 mx-0.5 text-base';
            // Left arrow
            if ($page > 1) {
                echo '<a href="' . htmlspecialchars($buildPageLink($page - 1)) . '" class="' . $arrowClass . ' ' . $pillClass . ' mr-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></a>';
            }
            for ($i = 1; $i <= $totalPages; $i++) {
                if (
                    $i == $showFirst ||
                    $i == $showLast ||
                    ($i >= $page - $window && $i <= $page + $window)
                ) {
                    if ($dots) {
                        echo '<span class="px-2 text-[#062B41] font-bold">...</span>';
                        $dots = false;
                    }
                    $isActive = ($i == $page);
                    echo '<a href="' . htmlspecialchars($buildPageLink($i)) . '" class="' . ($isActive ? $activeClass : $inactiveClass) . ' ' . $pillClass . '">' . $i . '</a>';
                } else {
                    $dots = true;
                }
            }
            // Right arrow
            if ($page < $totalPages) {
                echo '<a href="' . htmlspecialchars($buildPageLink($page + 1)) . '" class="' . $arrowClass . ' ' . $pillClass . ' ml-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></a>';
            }
            ?>
        </div>
        <?php endif; ?>

        <main class="flex-1 p-6">
            <div class="bg-white rounded shadow p-4">
                <h2 class="text-xl font-bold mb-4">Orders Table</h2>
                
                <?php if (!empty($_SESSION['order_cancel_message'])): ?>
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">
                        <?= htmlspecialchars($_SESSION['order_cancel_message']) ?>
                    </div>
                    <?php unset($_SESSION['order_cancel_message']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['order_complete_message'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <?= $_SESSION['order_complete_message'] ?>
                    </div>
                    <?php unset($_SESSION['order_complete_message']); ?>
                <?php endif; ?>
                
                <form method="GET" class="mb-4 rounded-xl border border-[#c6d9e8] bg-gradient-to-r from-[#f8fcff] to-[#eef6fb] p-4 shadow-sm">
                    <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sortByCurrent) ?>">
                    <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sortDirCurrent) ?>">

                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-[#062B41]">Advanced Filters</p>
                            <p class="text-xs text-gray-500">Applied to all orders, including pagination.</p>
                        </div>
                        <a href="/admin/orders" class="text-xs font-semibold text-[#0b5f8a] hover:underline">Reset all</a>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-8">
                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Order ID</label>
                            <input type="text" name="order_id_search" value="<?= htmlspecialchars($searchTerm ?? '') ?>" placeholder="Search Order ID..." class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Status</label>
                            <select name="status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <option value="<?= htmlspecialchars($statusOption) ?>" <?= (($statusFilter ?? '') === $statusOption) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($statusOption)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Customer Type</label>
                            <select name="customer_type" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="user" <?= (($customerTypeFilter ?? '') === 'user') ? 'selected' : '' ?>>User</option>
                                <option value="guest" <?= (($customerTypeFilter ?? '') === 'guest') ? 'selected' : '' ?>>Guest</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Sale Type</label>
                            <select name="sale_type" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="rental" <?= (($saleTypeFilter ?? '') === 'rental') ? 'selected' : '' ?>>Rental</option>
                                <option value="sale" <?= (($saleTypeFilter ?? '') === 'sale') ? 'selected' : '' ?>>Sale</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Booking Source</label>
                            <select name="booking_source" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="walk-in" <?= (($bookingSourceFilter ?? '') === 'walk-in') ? 'selected' : '' ?>>Walk-in</option>
                                <option value="online" <?= (($bookingSourceFilter ?? '') === 'online') ? 'selected' : '' ?>>Online</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Promo</label>
                            <select name="promo_usage" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="with" <?= (($promoUsageFilter ?? '') === 'with') ? 'selected' : '' ?>>With Promo</option>
                                <option value="without" <?= (($promoUsageFilter ?? '') === 'without') ? 'selected' : '' ?>>Without Promo</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Booked By Role</label>
                            <select name="creator_role" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="partner" <?= (($creatorRoleFilter ?? '') === 'partner') ? 'selected' : '' ?>>Partner</option>
                                <option value="admin" <?= (($creatorRoleFilter ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                                <option value="staff" <?= (($creatorRoleFilter ?? '') === 'staff') ? 'selected' : '' ?>>Staff</option>
                                <option value="superadmin" <?= (($creatorRoleFilter ?? '') === 'superadmin') ? 'selected' : '' ?>>Super Admin</option>
                            </select>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Date Range (Order Date)</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input id="orderDateFromDisplay" type="text" value="<?= htmlspecialchars($dateFromFilter ?? '') ?>" placeholder="From date" class="js-order-date-display w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <input id="orderDateToDisplay" type="text" value="<?= htmlspecialchars($dateToFilter ?? '') ?>" placeholder="To date" class="js-order-date-display w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                            </div>
                            <input id="orderDateFrom" type="hidden" name="date_from" value="<?= htmlspecialchars($dateFromFilter ?? '') ?>">
                            <input id="orderDateTo" type="hidden" name="date_to" value="<?= htmlspecialchars($dateToFilter ?? '') ?>">
                        </div>
                    </div>

                    <div class="mt-3 flex justify-end gap-2">
                        <button type="submit" class="cursor-pointer rounded-lg bg-[#062B41] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#08456b] transition-colors duration-150">Apply Filters</button>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table id="ordersTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left"><a href="<?= htmlspecialchars($buildSortLink('order_id')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Order ID <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'order_id') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th>
                                <th class="px-4 py-2">Customer</th>
                                <th class="px-4 py-2">Type</th> <!-- New: Customer Type -->
                                <th class="px-4 py-2">Email</th>
                                <th class="px-4 py-2 text-left"><a href="<?= htmlspecialchars($buildSortLink('sale_type')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Sale Type <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'sale_type') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th> <!-- New: Sale Type -->
                                <th class="px-4 py-2 text-left"><a href="<?= htmlspecialchars($buildSortLink('total_amount')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Total Amount <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'total_amount') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th>
                                <th class="px-4 py-2 text-left"><a href="<?= htmlspecialchars($buildSortLink('status')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Status <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'status') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th>
                                <th class="px-4 py-2">Source</th>
                                <th class="px-4 py-2">Booked By</th>
                                <th class="px-4 py-2">Promo</th>
                                <th class="px-4 py-2">Promo Applied By</th>
                                <th class="px-4 py-2 text-left"><a href="<?= htmlspecialchars($buildSortLink('order_date')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Date Ordered <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'order_date') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th>
                                <th class="text-left"><a href="<?= htmlspecialchars($buildSortLink('pickup_datetime')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Pickup Date/Time <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'pickup_datetime') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th>
                                <th class="text-left"><a href="<?= htmlspecialchars($buildSortLink('return_datetime')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Return Date/Time <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'return_datetime') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th>
                                <th class="px-4 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="px-4 py-2 cursor-pointer text-blue-600 underline" onclick="openOrderModal(<?= $order['order_id'] ?>)">
                                    <?= htmlspecialchars($order['order_id']) ?>
                                </td>
                                <td class="px-4 py-2">
                                    <?= htmlspecialchars($order['display_name'] ?? '') ?>
                                </td>
                                <td class="px-4 py-2">
                                    <?= $order['customer_type'] === 'user' ? '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">User</span>' : '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">Guest</span>' ?>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($order['customer_email'] ?? $order['guest_email'] ?? '') ?></td>
                                <td class="px-4 py-2">
                                    <?= $order['sale_type'] === 'sale' ? '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">Sale</span>' : '<span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">Rental</span>' ?>
                                </td>
                                <td class="px-4 py-2">$<?= number_format($order['total_amount'], 2) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($order['status']) ?></td>
                                <td class="px-4 py-2">
                                    <?php $isWalkIn = (strtolower((string)($order['booking_source'] ?? '')) === 'walk-in') || (strtolower((string)($order['pickup_location'] ?? '')) === 'walk-in booking'); ?>
                                    <?= $isWalkIn
                                        ? '<span class="bg-amber-100 text-amber-800 px-2 py-1 rounded text-xs">Walk-in</span>'
                                        : '<span class="bg-sky-100 text-sky-800 px-2 py-1 rounded text-xs">Online</span>' ?>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-700">
                                    <?php if (!empty($order['created_by_admin_role']) || !empty($order['created_by_admin_name'])): ?>
                                        <div class="font-semibold"><?= htmlspecialchars($order['created_by_admin_name'] ?? 'Admin') ?></div>
                                        <div class="text-gray-500"><?= htmlspecialchars($roleLabels[strtolower((string)($order['created_by_admin_role'] ?? ''))] ?? ucfirst((string)($order['created_by_admin_role'] ?? ''))) ?></div>
                                    <?php else: ?>
                                        <span class="text-gray-400">Website Checkout</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2">
                                    <?php if (!empty($order['promo_code'])): ?>
                                        <span class="bg-emerald-100 text-emerald-800 px-2 py-1 rounded text-xs font-semibold"><?= htmlspecialchars($order['promo_code']) ?></span>
                                        <?php if (isset($order['promo_discount']) && $order['promo_discount'] !== null): ?>
                                            <div class="text-xs text-gray-500 mt-1">-$<?= number_format((float)$order['promo_discount'], 2) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-700">
                                    <?php if (!empty($order['promo_code'])): ?>
                                        <div class="font-semibold"><?= htmlspecialchars($order['promo_applied_by_admin_name'] ?? 'N/A') ?></div>
                                        <div class="text-gray-500"><?= htmlspecialchars($order['promo_applied_by_admin_role'] ?? 'N/A') ?></div>
                                    <?php else: ?>
                                        <span class="text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2"><span data-admin-datetime="<?= htmlspecialchars($order['order_date'] ?? '') ?>"><?= htmlspecialchars($order['order_date'] ?? '') ?></span></td>
                                <td class="px-4 py-2"><span data-admin-datetime="<?= htmlspecialchars($order['pickup_datetime'] ?? '') ?>"><?= htmlspecialchars($order['pickup_datetime'] ?? '') ?></span></td>
                                <td class="px-4 py-2"><span data-admin-datetime="<?= htmlspecialchars($order['return_datetime'] ?? '') ?>"><?= htmlspecialchars($order['return_datetime'] ?? '') ?></span></td>

                                <!-- ACTIONS -->
                                <td class="px-4 py-2 space-x-2">
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <form method="post" action="/admin/orders/approve" class="inline">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600 cursor-pointer">Approve</button>
                                        </form>
                                        <form method="post" action="/admin/orders/cancel" class="inline" onsubmit="return confirm('Are you sure you want to reject this order?');">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 cursor-pointer">Reject</button>
                                        </form>
                                    <?php elseif ($order['status'] === 'approved'): ?>
                                        <form method="post" action="/admin/orders/paid" class="inline">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="bg-yellow-500 text-white px-2 py-1 rounded text-xs hover:bg-yellow-600 cursor-pointer">Mark as Paid</button>
                                        </form>
                                    <?php elseif ($order['status'] === 'paid'): ?>
                                        <form method="post" action="/admin/orders/complete" class="inline order-complete-form">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600 cursor-pointer">Complete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>


        <?php if ($totalPages > 1): ?>
        <div class="flex justify-center space-x-2 items-center select-none mb-3">
            <?php
            $window = 1; // pages before/after current
            $showFirst = 1;
            $showLast = $totalPages;
            $dots = false;
            $sidebarColor = '#062B41';
            $activeClass = 'bg-[#062B41] text-white font-bold shadow';
            $inactiveClass = 'bg-white text-[#062B41] hover:bg-[#062B41] hover:text-white transition-colors duration-150';
            $arrowClass = 'bg-white text-[#062B41] hover:bg-[#062B41] hover:text-white transition-colors duration-150';
            $pillClass = 'px-3 py-1 mx-0.5 text-base';
            // Left arrow
            if ($page > 1) {
                echo '<a href="' . htmlspecialchars($buildPageLink($page - 1)) . '" class="' . $arrowClass . ' ' . $pillClass . ' mr-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></a>';
            }
            for ($i = 1; $i <= $totalPages; $i++) {
                if (
                    $i == $showFirst ||
                    $i == $showLast ||
                    ($i >= $page - $window && $i <= $page + $window)
                ) {
                    if ($dots) {
                        echo '<span class="px-2 text-[#062B41] font-bold">...</span>';
                        $dots = false;
                    }
                    $isActive = ($i == $page);
                    echo '<a href="' . htmlspecialchars($buildPageLink($i)) . '" class="' . ($isActive ? $activeClass : $inactiveClass) . ' ' . $pillClass . '">' . $i . '</a>';
                } else {
                    $dots = true;
                }
            }
            // Right arrow
            if ($page < $totalPages) {
                echo '<a href="' . htmlspecialchars($buildPageLink($page + 1)) . '" class="' . $arrowClass . ' ' . $pillClass . ' ml-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></a>';
            }
            ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="fixed inset-0 flex items-center justify-center z-50 hidden" style="background: rgba(0,0,0,0.7);">
        <div class="bg-white rounded-lg shadow p-6 w-full max-w-2xl" id="orderModalBox">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold" id="orderModalTitle">Order Details</h3>
                <button onclick="closeOrderModal()" class="text-gray-500 hover:text-gray-700 text-2xl font-bold cursor-pointer">&times;</button>
            </div>
            <div id="orderModalContent">
                <div class="text-center text-gray-500">Loading...</div>
            </div>
        </div>
    </div>

    <script>
    function showOrderActionLoadingState(title, message) {
        const overlay = document.getElementById('order-action-loading-overlay');
        const titleNode = document.getElementById('order-action-loading-title');
        const copyNode = document.getElementById('order-action-loading-copy');

        if (titleNode && title) {
            titleNode.textContent = title;
        }

        if (copyNode && message) {
            copyNode.textContent = message;
        }

        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
        }
    }

    function toOrdinaryTime(value) {
        if (!value) return '';
        if (typeof window.formatAdminDateTime === 'function') {
            return window.formatAdminDateTime(value);
        }
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
    }

    function openOrderModal(orderId) {
        document.getElementById('orderModal').classList.remove('hidden');
        const content = document.getElementById('orderModalContent');
        const esc = (value) => {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };
        content.innerHTML = '<div class="text-center text-gray-500">Loading...</div>';
        fetch(`/admin/orders/details?order_id=${orderId}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (data.error) {
                    content.innerHTML = `<div class="text-red-500">${esc(data.error)}</div>`;
                    return;
                }

                let html = `
                    <div class="mb-4">
                        <span class="font-semibold">Order ID:</span> ${esc(data.order.order_id)}<br>
                                                <span class="font-semibold">Customer:</span> ${
                                                    (data.order.guest_first_name && data.order.guest_last_name)
                                                        ? esc(data.order.guest_first_name + ' ' + data.order.guest_last_name)
                                                        : esc(data.order.customer_name || '')
                                                }<br>
                        <span class="font-semibold">Email:</span> ${esc(data.order.guest_email || data.order.customer_email)}<br>
                        <span class="font-semibold">Status:</span> ${esc(data.order.status)}<br>
                        <span class="font-semibold">Date:</span> ${toOrdinaryTime(data.order.order_date)}<br>
                        <span class="font-semibold">Pickup:</span> ${toOrdinaryTime(data.order.pickup_datetime)}<br>
                        <span class="font-semibold">Return:</span> ${toOrdinaryTime(data.order.return_datetime)}<br>
                    </div>
                    <div class="mb-4">
                        <span class="font-semibold">Items:</span>
                        <table class="w-full border mt-2 text-sm">
                            <thead>
                                <tr>
                                    <th class="border px-2 py-1">Item</th>
                                    <th class="border px-2 py-1">Variation</th>
                                    <th class="border px-2 py-1">Price</th>
                                    <th class="border px-2 py-1">Qty</th>
                                    <th class="border px-2 py-1">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                // Group items by product name, variation, and price
                const grouped = {};
                (data.items || []).forEach(function(item) {
                    const name = item.product_name || item.name || '';
                    const variation = item.variation_name || '';
                    const price = parseFloat(item.price).toFixed(2);
                    const key = name + '||' + variation + '||' + price;
                    if (!grouped[key]) {
                        grouped[key] = {
                            name: name,
                            variation: variation,
                            price: price,
                            quantity: 0
                        };
                    }
                    grouped[key].quantity += parseInt(item.quantity || 1);
                });
                Object.values(grouped).forEach(function(item) {
                    const total = (parseFloat(item.price) * item.quantity).toFixed(2);
                    html += `<tr>
                        <td class="border px-2 py-1">${esc(item.name)}</td>
                        <td class="border px-2 py-1">${item.variation ? `<span class='border border-gray-300 rounded px-2 py-0.5 text-xs bg-gray-50'>${esc(item.variation)}</span>` : ''}</td>
                        <td class="border px-2 py-1">$${item.price}</td>
                        <td class="border px-2 py-1">${item.quantity}</td>
                        <td class="border px-2 py-1 font-semibold">$${total}</td>
                    </tr>`;
                });
                html += `</tbody></table></div>`;

                if (data.contract_pdf) {
                    html += `
                        <div class="mb-2">
                            <a href="${esc(data.contract_pdf)}" target="_blank" class="bg-gray-500 text-white px-2 py-1 rounded text-xs hover:bg-gray-700">Open Contract PDF</a>
                        </div>
                    `;
                } else {
                    html += `<div class="mb-2 text-red-500 text-xs">Contract not found</div>`;
                }

                if (data.invoice_pdf) {
                    html += `
                        <div class="mb-4">
                            <a href="${esc(data.invoice_pdf)}" target="_blank" class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-800">Open Invoice PDF</a>
                        </div>
                    `;
                } else {
                    html += `<div class="mb-4 text-red-500 text-xs">Invoice not found</div>`;
                }

                content.innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading order details:', error);
                content.innerHTML = '<div class="text-red-500">Failed to load order details.</div>';
            });

    }
    function closeOrderModal() {
        document.getElementById('orderModal').classList.add('hidden');
    }

    // Close modal when clicking outside the modal box
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('orderModal');
        const modalBox = document.getElementById('orderModalBox');
        if (modal && modalBox) {
            modal.addEventListener('mousedown', function(e) {
                if (e.target === modal) {
                    closeOrderModal();
                }
            });
        }

        document.querySelectorAll('.order-complete-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (form.dataset.submitting === 'true') {
                    e.preventDefault();
                    return;
                }

                const confirmed = window.confirm('Mark this order as completed?');
                if (!confirmed) {
                    e.preventDefault();
                    return;
                }

                form.dataset.submitting = 'true';
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }
                showOrderActionLoadingState('Completing order', 'Please wait while inventory, PDFs, and order status are being finalized.');
            });
        });

        if (typeof flatpickr === 'function') {
            const fromHidden = document.getElementById('orderDateFrom');
            const toHidden = document.getElementById('orderDateTo');
            const fromDisplay = document.getElementById('orderDateFromDisplay');
            const toDisplay = document.getElementById('orderDateToDisplay');

            if (fromDisplay && fromHidden) {
                flatpickr(fromDisplay, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'M j, Y',
                    defaultDate: fromHidden.value || null,
                    allowInput: true,
                    disableMobile: true,
                    onChange: function(selectedDates, dateStr) {
                        fromHidden.value = dateStr || '';
                    },
                    onClose: function(selectedDates, dateStr) {
                        fromHidden.value = dateStr || '';
                    }
                });
            }

            if (toDisplay && toHidden) {
                flatpickr(toDisplay, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'M j, Y',
                    defaultDate: toHidden.value || null,
                    allowInput: true,
                    disableMobile: true,
                    onChange: function(selectedDates, dateStr) {
                        toHidden.value = dateStr || '';
                    },
                    onClose: function(selectedDates, dateStr) {
                        toHidden.value = dateStr || '';
                    }
                });
            }
        }

        const filterForm = document.querySelector('form[method="GET"]');
        if (filterForm) {
            filterForm.addEventListener('submit', function() {
                const fromDisplayInput = document.getElementById('orderDateFromDisplay');
                const toDisplayInput = document.getElementById('orderDateToDisplay');
                const fromHiddenInput = document.getElementById('orderDateFrom');
                const toHiddenInput = document.getElementById('orderDateTo');

                if (fromDisplayInput && fromDisplayInput._flatpickr && fromHiddenInput) {
                    fromHiddenInput.value = fromDisplayInput._flatpickr.input.value || '';
                }

                if (toDisplayInput && toDisplayInput._flatpickr && toHiddenInput) {
                    toHiddenInput.value = toDisplayInput._flatpickr.input.value || '';
                }
            });
        }
    });
    
    function validatePickupLocation() {
        const deliveryType = document.querySelector('input[name="delivery_type"]:checked')?.value;
        if (deliveryType === 'pickup') {
            const pickupDropdown = document.querySelector('select[name="pickup_location"]');
            if (!pickupDropdown || !pickupDropdown.value) {
                alert('Please select a store for pickup.');
                return false;
            }
        }
        return true;
    }

    var checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            if (!validatePickupLocation()) {
                e.preventDefault();
            }
        });
    }
    </script>
</body>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" integrity="sha384-RkASv+6KfBMW9eknReJIJ6b3UnjKOKC5bOUaNgIY778NFbQ8MtWq9Lr/khUgqtTt" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" integrity="sha384-5JqMv4L/Xa0hfvtF06qboNdhvuYXUku9ZrhZh3bSk8VXF0A/RuSLHpLsSV9Zqhl6" crossorigin="anonymous"></script>

    <style>
    .flatpickr-calendar {
        border-radius: 14px;
        border: 1px solid #dbe8f2;
        box-shadow: 0 16px 34px rgba(6, 43, 65, 0.14);
    }

    .flatpickr-day.selected,
    .flatpickr-day.startRange,
    .flatpickr-day.endRange {
        background: #0086c9;
        border-color: #0086c9;
    }

    .flatpickr-day.today {
        border-color: #0b5f8a;
    }
    </style>

</html>