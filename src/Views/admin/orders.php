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
$rawRoleKey = $_SESSION['admin_role'] ?? 'admin';
$roleKey = strtolower(str_replace(['_', '-', ' '], '', (string)$rawRoleKey));
$canManageMoneyActions = in_array($roleKey, ['admin', 'superadmin'], true);
$canManageSecurityDeposit = $roleKey === 'superadmin';
$canRefundSecurityDeposit = $roleKey === 'superadmin';
$canCompleteOrders = in_array($roleKey, ['staff', 'admin', 'superadmin'], true);
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

$buildQuickPeriodLink = function (string $period) use ($currentQuery) {
    $query = $currentQuery;
    unset($query['date_from'], $query['date_to'], $query['status']);
    $query['quick_period'] = $period;
    $query['page'] = 1;
    return '?' . http_build_query($query);
};

$quickPeriodActive = strtolower((string)($quickPeriodFilter ?? ''));

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
                
                <form method="GET" class="orders-filter-compact mb-4 rounded-xl border border-[#c6d9e8] bg-gradient-to-r from-[#f8fcff] to-[#eef6fb] p-4 shadow-sm">
                    <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sortByCurrent) ?>">
                    <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sortDirCurrent) ?>">

                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-gray-600">Quick Period (Ongoing Incomplete):</span>
                        <div class="quick-period-pills">
                            <a href="<?= htmlspecialchars($buildQuickPeriodLink('late')) ?>" class="rounded-full border text-xs font-semibold transition-colors duration-150 <?= $quickPeriodActive === 'late' ? 'border-[#062B41] bg-[#062B41] text-white' : 'border-[#9abbd1] bg-white text-[#0b5f8a] hover:bg-[#e8f4fb]' ?>">Late</a>
                            <a href="<?= htmlspecialchars($buildQuickPeriodLink('today')) ?>" class="rounded-full border text-xs font-semibold transition-colors duration-150 <?= $quickPeriodActive === 'today' ? 'border-[#062B41] bg-[#062B41] text-white' : 'border-[#9abbd1] bg-white text-[#0b5f8a] hover:bg-[#e8f4fb]' ?>">Today</a>
                            <a href="<?= htmlspecialchars($buildQuickPeriodLink('upcoming')) ?>" class="rounded-full border text-xs font-semibold transition-colors duration-150 <?= $quickPeriodActive === 'upcoming' ? 'border-[#062B41] bg-[#062B41] text-white' : 'border-[#9abbd1] bg-white text-[#0b5f8a] hover:bg-[#e8f4fb]' ?>">Upcoming</a>
                        </div>
                    </div>

                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-[#062B41]">Advanced Filters</p>
                            <p class="text-xs text-gray-500">Applied to all orders, including pagination.</p>
                        </div>
                        <a href="/admin/orders" class="text-xs font-semibold text-[#0b5f8a] hover:underline">Reset all</a>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-9">
                        <div class="filter-field lg:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Order ID</label>
                            <input type="text" name="order_id_search" value="<?= htmlspecialchars($searchTerm ?? '') ?>" placeholder="Search Order ID..." class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                        </div>

                        <div class="filter-field">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Status</label>
                            <select name="status" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <option value="<?= htmlspecialchars($statusOption) ?>" <?= (($statusFilter ?? '') === $statusOption) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($statusOption)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Customer Type</label>
                            <select name="customer_type" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="user" <?= (($customerTypeFilter ?? '') === 'user') ? 'selected' : '' ?>>User</option>
                                <option value="guest" <?= (($customerTypeFilter ?? '') === 'guest') ? 'selected' : '' ?>>Guest</option>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Sale Type</label>
                            <select name="sale_type" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="rental" <?= (($saleTypeFilter ?? '') === 'rental') ? 'selected' : '' ?>>Rental</option>
                                <option value="sale" <?= (($saleTypeFilter ?? '') === 'sale') ? 'selected' : '' ?>>Sale</option>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Booking Source</label>
                            <select name="booking_source" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="walk-in" <?= (($bookingSourceFilter ?? '') === 'walk-in') ? 'selected' : '' ?>>Walk-in</option>
                                <option value="online" <?= (($bookingSourceFilter ?? '') === 'online') ? 'selected' : '' ?>>Online</option>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Heard About Us</label>
                            <select name="heard_about" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="others" <?= (($heardAboutFilter ?? '') === 'others') ? 'selected' : '' ?>>Others</option>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Promo</label>
                            <select name="promo_usage" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="with" <?= (($promoUsageFilter ?? '') === 'with') ? 'selected' : '' ?>>With Promo</option>
                                <option value="without" <?= (($promoUsageFilter ?? '') === 'without') ? 'selected' : '' ?>>Without Promo</option>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Booked By Role</label>
                            <select name="creator_role" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <option value="">All</option>
                                <option value="partner" <?= (($creatorRoleFilter ?? '') === 'partner') ? 'selected' : '' ?>>Partner</option>
                                <option value="admin" <?= (($creatorRoleFilter ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                                <option value="staff" <?= (($creatorRoleFilter ?? '') === 'staff') ? 'selected' : '' ?>>Staff</option>
                                <option value="superadmin" <?= (($creatorRoleFilter ?? '') === 'superadmin') ? 'selected' : '' ?>>Super Admin</option>
                            </select>
                        </div>

                        <div class="filter-field filter-field-wide lg:col-span-2">
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
                        <?php
                            $ordersExportQuery = $_GET;
                            unset($ordersExportQuery['page']);
                            $ordersExportUrl = '/admin/orders/export' . (!empty($ordersExportQuery) ? ('?' . http_build_query($ordersExportQuery)) : '');
                        ?>
                        <a href="<?= htmlspecialchars($ordersExportUrl) ?>" class="cursor-pointer rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 shadow hover:bg-emerald-100 transition-colors duration-150">Export CSV</a>
                        <button type="submit" class="cursor-pointer rounded-lg bg-[#062B41] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#08456b] transition-colors duration-150">Apply Filters</button>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table id="ordersTable" class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr data-order-id="<?= (int)$order['order_id'] ?>">
                                <th class="px-4 py-2 text-left"><a href="<?= htmlspecialchars($buildSortLink('order_id')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Order ID <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'order_id') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th>
                                <th class="px-4 py-2">Customer</th>
                                <th class="px-4 py-2">Type</th> <!-- New: Customer Type -->
                                <th class="px-4 py-2">Email</th>
                                <th class="px-4 py-2 text-left"><a href="<?= htmlspecialchars($buildSortLink('sale_type')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Sale Type <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'sale_type') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th> <!-- New: Sale Type -->
                                <th class="px-4 py-2 text-left min-w-[210px]"><a href="<?= htmlspecialchars($buildSortLink('total_amount')) ?>" class="inline-flex items-center gap-1 hover:text-[#0b5f8a]">Amounts <span class="text-xs text-gray-400"><?= ($sortByCurrent === 'total_amount') ? ($sortDirCurrent === 'asc' ? '↑' : '↓') : '⇅' ?></span></a></th>
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
                                <?php
                                    $totalAmount = (float)($order['total_amount'] ?? 0);
                                    $salesAmount = isset($order['sales_amount'])
                                        ? (float)$order['sales_amount']
                                        : max(0, (float)($order['total_amount'] ?? 0) - (float)($order['security_deposit'] ?? 0));
                                    $refundAmount = isset($order['refund_amount'])
                                        ? (float)$order['refund_amount']
                                        : (float)($order['security_deposit_refunded_amount'] ?? 0);
                                    $isFinalPriceEdited = (int)($order['final_price_edited'] ?? 0) === 1;
                                    $computedTotalAmount = isset($order['computed_total_amount']) && $order['computed_total_amount'] !== null
                                        ? (float)$order['computed_total_amount']
                                        : null;
                                    $finalPriceEditorName = trim((string)($order['final_price_edited_by_admin_name'] ?? ''));
                                    $finalPriceEditorRoleKey = strtolower(str_replace(['_', '-', ' '], '', (string)($order['final_price_edited_by_admin_role'] ?? '')));
                                    $finalPriceEditorRoleLabel = $roleLabels[$finalPriceEditorRoleKey] ?? ucfirst($finalPriceEditorRoleKey);
                                ?>
                                <td class="px-4 py-2 align-top">
                                    <div class="leading-5">
                                        <div class="text-base font-semibold text-[#062B41]" data-order-total>Total: $<?= number_format($totalAmount, 2) ?></div>
                                        <?php if ($isFinalPriceEdited): ?>
                                            <div class="mt-1 inline-flex rounded bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-800">Final price edited</div>
                                            <?php if ($computedTotalAmount !== null): ?>
                                                <div class="text-xs text-amber-700">Original: $<?= number_format($computedTotalAmount, 2) ?></div>
                                            <?php endif; ?>
                                            <div class="text-xs text-amber-700">
                                                Edited by:
                                                <?= htmlspecialchars($finalPriceEditorName !== '' ? $finalPriceEditorName : 'Super Admin') ?>
                                                <?php if ($finalPriceEditorRoleLabel !== ''): ?>
                                                    (<?= htmlspecialchars($finalPriceEditorRoleLabel) ?>)
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-sm text-gray-700">Sales: $<?= number_format($salesAmount, 2) ?></div>
                                        <div class="text-sm text-gray-700">Refund: $<?= number_format($refundAmount, 2) ?></div>
                                    </div>
                                </td>
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
                                    <?php if ($order['status'] === 'pending' && $canManageMoneyActions): ?>
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
                                    <?php elseif ($order['status'] === 'approved' && $canManageMoneyActions): ?>
                                        <form method="post" action="/admin/orders/paid" class="inline">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="bg-yellow-500 text-white px-2 py-1 rounded text-xs hover:bg-yellow-600 cursor-pointer">Mark as Paid</button>
                                        </form>
                                    <?php elseif ($order['status'] === 'paid' && $canCompleteOrders): ?>
                                        <form method="post" action="/admin/orders/complete" class="inline order-complete-form">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="security_deposit" value="<?= htmlspecialchars((string)($order['security_deposit'] ?? '0')) ?>">
                                            <input type="hidden" name="security_deposit_refunded_amount" value="<?= htmlspecialchars((string)($order['security_deposit_refunded_amount'] ?? '0')) ?>">
                                            <button type="submit" class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600 cursor-pointer">Complete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">No actions</span>
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
    <div id="orderModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 px-4">
        <div id="orderModalBox" class="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-[#062B41]" id="orderModalTitle">Order Details</h3>
                <button onclick="closeOrderModal()" class="text-2xl font-bold text-gray-400 hover:text-gray-700 cursor-pointer">&times;</button>
            </div>
            <div id="orderModalContent" class="max-h-[70vh] overflow-y-auto text-sm text-gray-700">
                <div class="py-8 text-center text-gray-500">Loading...</div>
            </div>
        </div>
    </div>

    <style>
        .orders-filter-compact .quick-period-pills {
            width: 246px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.35rem;
            flex-shrink: 0;
        }

        .orders-filter-compact .quick-period-pills a {
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.4rem;
            line-height: 1;
        }

        .orders-filter-compact .filter-field {
            max-width: 168px;
        }

        .orders-filter-compact .filter-field-wide {
            max-width: 356px;
        }

        .orders-filter-compact select,
        .orders-filter-compact input[name="order_id_search"],
        .orders-filter-compact .js-order-date-display {
            height: 34px;
            font-size: 12px;
            padding-top: 0;
            padding-bottom: 0;
        }

        @media (max-width: 1023px) {
            .orders-filter-compact .quick-period-pills {
                width: 100%;
                max-width: 246px;
            }

            .orders-filter-compact .filter-field,
            .orders-filter-compact .filter-field-wide {
                max-width: 100%;
            }
        }

        #ordersTable th,
        #ordersTable td {
            font-size: 12px;
            padding: 0.35rem 0.55rem;
            white-space: nowrap;
        }
    </style>

    <script>
    const ADMIN_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const CAN_MANAGE_SECURITY_DEPOSIT = <?= $canManageSecurityDeposit ? 'true' : 'false' ?>;
    const CAN_REFUND_SECURITY_DEPOSIT = <?= $canRefundSecurityDeposit ? 'true' : 'false' ?>;

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
        const modal = document.getElementById('orderModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        const content = document.getElementById('orderModalContent');
        const esc = (value) => {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };
        const toFriendlyAjaxError = (value, fallback) => {
            const raw = String(value ?? '').trim();
            if (!raw) {
                return fallback;
            }
            if (/<!doctype html|<html/i.test(raw)) {
                return 'Your admin session has expired. Please log in again and retry.';
            }
            if (raw.length > 300) {
                return fallback;
            }
            return raw;
        };
        content.innerHTML = '<div class="py-8 text-center text-gray-500">Loading...</div>';
        const detailsUrl = `/admin/orders/details?order_id=${orderId}&_=${Date.now()}`;
        fetch(detailsUrl, { cache: 'no-store' })
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

                const order = data.order || {};
                const items = Array.isArray(data.items) ? data.items : [];
                const refundSummary = data.refund_summary || {};
                const refunds = Array.isArray(data.refunds) ? data.refunds : [];
                const customerName = [order.guest_first_name || '', order.guest_last_name || ''].join(' ').trim() || order.customer_name || 'N/A';
                const customerEmail = order.guest_email || order.customer_email || 'N/A';
                const customerPhone = order.guest_phone || order.customer_phone || 'N/A';
                const paymentPlatform = order.payment_provider || refundSummary.payment_provider || (order.payment_method === 'paypal' ? 'paypal' : (order.payment_method === 'card' ? 'stripe' : 'unknown'));
                const isWalkInBooking = String(order.booking_source || '').toLowerCase() === 'walk-in';
                const weightOptionRaw = String(order.client_weight_option || '').trim();
                const weightLbsRaw = Number(order.client_weight_lbs || 0);
                const weightDisplay = weightOptionRaw !== ''
                    ? weightOptionRaw
                    : (weightLbsRaw > 0 ? `${weightLbsRaw} lbs` : 'Not specified');
                const heightDisplay = String(order.client_height || '').trim() || 'Not specified';
                const heardAboutRaw = String(order.heard_about_label || order.heard_about_display || '').trim();
                const heardAboutOptionId = Number(order.heard_about_option_id || 0);
                let heardAboutDisplay = 'Not specified';
                if (heardAboutRaw && heardAboutRaw.toLowerCase() !== 'not specified') {
                    heardAboutDisplay = heardAboutOptionId > 0 ? heardAboutRaw : `Other - ${heardAboutRaw}`;
                }
                const defaultRefundMethod = isWalkInBooking
                    ? (String(order.payment_method || '').toLowerCase() === 'cash' ? 'cash' : 'card-terminal')
                    : 'provider';

                let html = `
                    <div class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-2">
                        <div><span class="font-semibold text-[#062B41]">Order ID:</span> ${esc(order.order_id || '')}</div>
                        <div><span class="font-semibold text-[#062B41]">Status:</span> ${esc(order.status || '')}</div>
                        <div><span class="font-semibold text-[#062B41]">Customer:</span> ${esc(customerName)}</div>
                        <div><span class="font-semibold text-[#062B41]">Email:</span> ${esc(customerEmail)}</div>
                        <div><span class="font-semibold text-[#062B41]">Phone:</span> ${esc(customerPhone)}</div>
                        <div><span class="font-semibold text-[#062B41]">Client Weight (lbs):</span> ${esc(weightDisplay)}</div>
                        <div><span class="font-semibold text-[#062B41]">Client Height:</span> ${esc(heightDisplay)}</div>
                        <div><span class="font-semibold text-[#062B41]">Payment:</span> ${esc(order.payment_method || 'N/A')}</div>
                        <div><span class="font-semibold text-[#062B41]">Platform:</span> ${esc(String(paymentPlatform).toUpperCase())}</div>
                        <div><span class="font-semibold text-[#062B41]">Pickup Location:</span> ${esc(order.pickup_location_name || order.pickup_location || 'N/A')}</div>
                        <div><span class="font-semibold text-[#062B41]">Heard About Us:</span> ${esc(heardAboutDisplay)}</div>
                        <div><span class="font-semibold text-[#062B41]">Pickup:</span> ${toOrdinaryTime(order.pickup_datetime)}</div>
                        <div><span class="font-semibold text-[#062B41]">Return:</span> ${toOrdinaryTime(order.return_datetime)}</div>
                        <div><span class="font-semibold text-[#062B41]">Total:</span> $${Number(order.total_amount || 0).toFixed(2)}</div>
                        <div><span class="font-semibold text-[#062B41]">Ordered At:</span> ${toOrdinaryTime(order.order_date)}</div>
                    </div>
                `;

                html += `
                    <div class="mb-4 grid grid-cols-1 gap-2 rounded-xl border-2 border-sky-200 bg-sky-50 p-4 md:grid-cols-2">
                        <div>
                            <span class="font-bold text-[#0b5f8a]">Delivery Hotel:</span>
                            <div class="mt-1 text-base font-semibold text-[#062B41]">${esc(order.hotel_name || 'N/A')}</div>
                        </div>
                        <div>
                            <span class="font-bold text-[#0b5f8a]">Return Hotel:</span>
                            <div class="mt-1 text-base font-semibold text-[#062B41]">${esc(order.return_hotel_name || order.hotel_name || 'N/A')}</div>
                        </div>
                    </div>
                `;

                if (order.notes) {
                    html += `<div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 p-3"><span class="font-semibold text-[#062B41]">Order Notes:</span><div class="mt-1 whitespace-pre-wrap">${esc(order.notes)}</div></div>`;
                }

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
                            quantity: 0,
                            scooterIds: []
                        };
                    }
                    grouped[key].quantity += parseInt(item.quantity || 1);
                    const sid = Number(item.scooter_id || 0);
                    if (sid > 0 && !grouped[key].scooterIds.includes(sid)) {
                        grouped[key].scooterIds.push(sid);
                    }
                });

                const uniqueScooters = Array.from(new Set(
                    items
                        .map(item => Number(item.scooter_id || 0))
                        .filter(id => id > 0)
                ));

                if (uniqueScooters.length > 0) {
                    html += `
                        <div class="mb-4 rounded-xl border border-indigo-100 bg-indigo-50 p-3">
                            <div class="mb-2 font-semibold text-[#062B41]">Assigned Scooter IDs</div>
                            <div class="flex flex-wrap gap-2">
                                ${uniqueScooters.map(id => `<span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-700 border border-indigo-200">#${id}</span>`).join('')}
                            </div>
                        </div>
                    `;
                }

                let groupedSubtotal = 0;
                html += `
                    <div class="mt-4">
                        <h4 class="mb-2 font-semibold text-[#062B41]">Items</h4>
                        <table class="w-full border border-gray-200 text-sm">
                            <thead class="bg-blue-50 text-[#062B41]">
                                <tr>
                                    <th class="border border-gray-200 px-2 py-1 text-left">Product</th>
                                    <th class="border border-gray-200 px-2 py-1 text-left">Variation</th>
                                    <th class="border border-gray-200 px-2 py-1 text-center">Scooter #</th>
                                    <th class="border border-gray-200 px-2 py-1 text-right">Qty</th>
                                    <th class="border border-gray-200 px-2 py-1 text-right">Unit Price</th>
                                    <th class="border border-gray-200 px-2 py-1 text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                Object.values(grouped).forEach(function(item) {
                    const total = (parseFloat(item.price) * item.quantity).toFixed(2);
                    groupedSubtotal += Number(total);
                    const scooterCell = item.scooterIds.length > 0
                        ? item.scooterIds.map(id => `#${id}`).join(', ')
                        : '—';
                    html += `<tr>
                        <td class="border border-gray-200 px-2 py-1">${esc(item.name)}</td>
                        <td class="border border-gray-200 px-2 py-1">${item.variation ? `<span class='border border-gray-300 rounded px-2 py-0.5 text-xs bg-gray-50'>${esc(item.variation)}</span>` : ''}</td>
                        <td class="border border-gray-200 px-2 py-1 text-center">${esc(scooterCell)}</td>
                        <td class="border border-gray-200 px-2 py-1 text-right">${item.quantity}</td>
                        <td class="border border-gray-200 px-2 py-1 text-right">$${item.price}</td>
                        <td class="border border-gray-200 px-2 py-1 text-right font-semibold">$${total}</td>
                    </tr>`;
                });
                html += `</tbody></table></div>`;

                const orderTotal = Number(order.total_amount || 0);
                const discount = Number(order.promo_discount || 0);
                const productTotalWithTax = Math.max(0, groupedSubtotal - discount);
                const dbSecurityDeposit = Number(order.security_deposit);
                const securityDeposit = Number.isFinite(dbSecurityDeposit) && dbSecurityDeposit >= 0
                    ? dbSecurityDeposit
                    : Math.max(0, orderTotal - productTotalWithTax);
                const refundedTotal = Number(refundSummary.refunded_total || order.security_deposit_refunded_amount || 0);
                const refundableRemaining = Number(refundSummary.refundable_remaining ?? Math.max(0, securityDeposit - refundedTotal));
                const depositReason = String(order.security_deposit_reason || '').trim();
                const depositUpdatedAt = String(order.security_deposit_updated_at || '').trim();
                html += `
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <div class="font-semibold text-[#7c3e00]">Security Deposit</div>
                        <div class="mt-1 text-sm text-[#7c3e00]">Current deposit: <span class="font-semibold">$${securityDeposit.toFixed(2)}</span></div>
                        <div class="mt-1 text-xs text-[#7c3e00]">Refunded so far: <span class="font-semibold">$${refundedTotal.toFixed(2)}</span> | Remaining refundable: <span class="font-semibold">$${refundableRemaining.toFixed(2)}</span></div>
                        <div class="mt-1 text-xs text-[#7c3e00]">Adjust this amount for damage hold or when the default deposit is not enough. Order total will be recalculated automatically.</div>
                        ${depositReason ? `<div class="mt-2 rounded-md border border-amber-300 bg-white/70 p-2 text-xs text-[#7c3e00]"><span class="font-semibold">Latest reason:</span> ${esc(depositReason)}${depositUpdatedAt ? ` <span class="text-[#9a5310]">(${esc(toOrdinaryTime(depositUpdatedAt))})</span>` : ''}</div>` : ''}
                        ${CAN_MANAGE_SECURITY_DEPOSIT ? `
                        <form class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-12 md:items-end" data-security-deposit-form>
                            <input type="hidden" name="order_id" value="${Number(order.order_id || orderId)}">
                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-[#7c3e00]">New Deposit ($)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="10000"
                                    name="security_deposit"
                                    value="${securityDeposit.toFixed(2)}"
                                    class="mt-1 w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-sm text-[#7c3e00] focus:border-[#b45309] focus:outline-none focus:ring-2 focus:ring-amber-300/60"
                                    required
                                >
                            </div>
                            <div class="md:col-span-7">
                                <label class="block text-xs font-semibold text-[#7c3e00]">Reason (required)</label>
                                <textarea
                                    name="security_deposit_reason"
                                    rows="2"
                                    minlength="5"
                                    maxlength="1000"
                                    placeholder="Example: Increased hold due to broken equipment risk"
                                    class="mt-1 w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-sm text-[#7c3e00] focus:border-[#b45309] focus:outline-none focus:ring-2 focus:ring-amber-300/60"
                                    required
                                ></textarea>
                            </div>
                            <button type="submit" class="md:col-span-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 cursor-pointer">Save Deposit</button>
                        </form>
                        <div class="mt-2 text-xs" data-security-deposit-feedback></div>
                        ` : `<div class="mt-2 rounded-md border border-amber-300 bg-white/70 p-2 text-xs text-[#7c3e00]">Only Super Admin can change security deposit amounts.</div>`}

                        <div class="mt-4 border-t border-amber-200 pt-3">
                            <div class="font-semibold text-[#7c3e00]">Return Security Deposit</div>
                            <div class="mt-1 text-xs text-[#7c3e00]">${isWalkInBooking ? 'Record the walk-in refund method used at the desk or terminal.' : `Refunds go back to the original ${esc(String(paymentPlatform).toUpperCase())} payment source.`}</div>
                            ${CAN_REFUND_SECURITY_DEPOSIT ? `
                            <form class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-12 md:items-end" data-security-deposit-refund-form>
                                <input type="hidden" name="order_id" value="${Number(order.order_id || orderId)}">
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-semibold text-[#7c3e00]">Refund Amount ($)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max="${Math.max(0.01, refundableRemaining).toFixed(2)}"
                                        name="refund_amount"
                                        value="${Math.max(0, refundableRemaining).toFixed(2)}"
                                        class="mt-1 w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-sm text-[#7c3e00] focus:border-[#b45309] focus:outline-none focus:ring-2 focus:ring-amber-300/60"
                                        ${refundableRemaining > 0 ? 'required' : 'disabled'}
                                    >
                                </div>
                                <div class="md:col-span-3">
                                    ${isWalkInBooking ? `
                                        <label class="block text-xs font-semibold text-[#7c3e00]">Refund Method</label>
                                        <select
                                            name="refund_method"
                                            class="mt-1 w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-sm text-[#7c3e00] focus:border-[#b45309] focus:outline-none focus:ring-2 focus:ring-amber-300/60"
                                            ${refundableRemaining > 0 ? '' : 'disabled'}
                                        >
                                            <option value="card-terminal" ${defaultRefundMethod === 'card-terminal' ? 'selected' : ''}>Card terminal</option>
                                            <option value="cash" ${defaultRefundMethod === 'cash' ? 'selected' : ''}>Cash</option>
                                        </select>
                                    ` : `
                                        <label class="block text-xs font-semibold text-[#7c3e00]">Refund Method</label>
                                        <div class="mt-1 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm text-[#7c3e00]">
                                            Original payment service (${esc(String(paymentPlatform).toUpperCase())})
                                        </div>
                                        <input type="hidden" name="refund_method" value="provider">
                                    `}
                                </div>
                                <div class="md:col-span-7">
                                    <label class="block text-xs font-semibold text-[#7c3e00]">Refund Reason (required)</label>
                                    <textarea
                                        name="refund_reason"
                                        rows="2"
                                        minlength="5"
                                        maxlength="1000"
                                        placeholder="Example: Returned unused deposit after inspection"
                                        class="mt-1 w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-sm text-[#7c3e00] focus:border-[#b45309] focus:outline-none focus:ring-2 focus:ring-amber-300/60"
                                        ${refundableRemaining > 0 ? 'required' : 'disabled'}
                                    ></textarea>
                                </div>
                                <button type="submit" class="md:col-span-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 cursor-pointer" ${refundableRemaining > 0 ? '' : 'disabled'}>${refundableRemaining > 0 ? 'Refund Deposit' : 'No Balance'}</button>
                            </form>
                            <div class="mt-2 text-xs" data-security-deposit-refund-feedback></div>
                            ` : `<div class="mt-2 rounded-md border border-amber-300 bg-white/70 p-2 text-xs text-[#7c3e00]">Only Super Admin can process security deposit refunds.</div>`}

                            <div class="mt-3 rounded-md border border-amber-300 bg-white/70 p-2">
                                <div class="text-xs font-semibold text-[#7c3e00] mb-1">Refund History</div>
                                ${refunds.length > 0 ? `
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-left text-[#7c3e00]">
                                                    <th class="py-1 pr-2">Date</th>
                                                    <th class="py-1 pr-2">Platform</th>
                                                    <th class="py-1 pr-2">Amount</th>
                                                    <th class="py-1 pr-2">Status</th>
                                                    <th class="py-1">Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${refunds.map(r => `<tr class="border-t border-amber-100"><td class="py-1 pr-2">${esc(toOrdinaryTime(r.created_at))}</td><td class="py-1 pr-2">${esc(String(r.payment_provider || '').toUpperCase())}${r.refund_method ? `<div class='text-[10px] text-gray-500'>${esc(String(r.refund_method).replace('-', ' '))}</div>` : ''}</td><td class="py-1 pr-2">$${Number(r.approved_amount || r.requested_amount || 0).toFixed(2)}</td><td class="py-1 pr-2">${esc(r.status || '')}</td><td class="py-1">${esc(r.reason || '')}</td></tr>`).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                ` : `<div class="text-xs text-[#9a5310]">No refunds yet.</div>`}
                            </div>
                        </div>
                    </div>
                `;

                if (data.contract_pdf) {
                    html += `
                        <div class="mb-2 mt-4">
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

                if (data.proforma_pdf) {
                    html += `
                        <div class="mb-4">
                            <a href="${esc(data.proforma_pdf)}" target="_blank" class="bg-indigo-600 text-white px-2 py-1 rounded text-xs hover:bg-indigo-800">Open Pro-forma PDF</a>
                        </div>
                    `;
                }

                content.innerHTML = html;

                const depositForm = content.querySelector('[data-security-deposit-form]');
                if (depositForm) {
                    depositForm.addEventListener('submit', function (event) {
                        event.preventDefault();

                        const feedback = content.querySelector('[data-security-deposit-feedback]');
                        const submitButton = depositForm.querySelector('button[type="submit"]');
                        const depositInput = depositForm.querySelector('input[name="security_deposit"]');
                        const reasonInput = depositForm.querySelector('textarea[name="security_deposit_reason"]');

                        const parsed = Number(depositInput ? depositInput.value : NaN);
                        if (!Number.isFinite(parsed) || parsed < 0) {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-red-700';
                                feedback.textContent = 'Enter a valid non-negative amount.';
                            }
                            return;
                        }

                        const reason = String(reasonInput ? reasonInput.value : '').trim();
                        if (reason.length < 5) {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-red-700';
                                feedback.textContent = 'Please provide a reason of at least 5 characters.';
                            }
                            return;
                        }

                        const confirmMessage = `Change security deposit from $${securityDeposit.toFixed(2)} to $${parsed.toFixed(2)}?\n\nReason:\n${reason}`;
                        const confirmed = window.confirm(confirmMessage);
                        if (!confirmed) {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-amber-800';
                                feedback.textContent = 'Deposit update cancelled.';
                            }
                            return;
                        }

                        if (submitButton) {
                            submitButton.disabled = true;
                            submitButton.textContent = 'Saving...';
                        }
                        if (feedback) {
                            feedback.className = 'mt-2 text-xs text-amber-800';
                            feedback.textContent = 'Updating security deposit...';
                        }

                        const payload = new URLSearchParams();
                        payload.set('order_id', String(Number(order.order_id || orderId)));
                        payload.set('security_deposit', parsed.toFixed(2));
                        payload.set('security_deposit_reason', reason);
                        payload.set('csrf_token', ADMIN_CSRF_TOKEN);

                        fetch('/admin/orders/security-deposit', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: payload.toString()
                        })
                        .then(async (response) => {
                            const rawText = await response.text();
                            let body = {};
                            if (rawText) {
                                try {
                                    body = JSON.parse(rawText);
                                } catch (_e) {
                                    body = { error: rawText };
                                }
                            }
                            if (!response.ok || body.error) {
                                throw new Error(toFriendlyAjaxError(body.error || rawText, 'Failed to update security deposit.'));
                            }
                            return body;
                        })
                        .then((body) => {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-green-700';
                                feedback.textContent = 'Security deposit updated.';
                            }

                            const updatedTotal = Number(body?.order?.total_amount || 0);
                            if (Number.isFinite(updatedTotal) && updatedTotal > 0) {
                                const row = document.querySelector(`tr[data-order-id="${Number(order.order_id || orderId)}"]`);
                                const totalNode = row ? row.querySelector('[data-order-total]') : null;
                                if (totalNode) {
                                    totalNode.textContent = `Total: $${updatedTotal.toFixed(2)}`;
                                }
                            }

                            openOrderModal(Number(order.order_id || orderId));
                        })
                        .catch((error) => {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-red-700';
                                feedback.textContent = toFriendlyAjaxError(error.message, 'Failed to update security deposit.');
                            }
                        })
                        .finally(() => {
                            if (submitButton) {
                                submitButton.disabled = false;
                                submitButton.textContent = 'Save Deposit';
                            }
                        });
                    });
                }

                const refundForm = content.querySelector('[data-security-deposit-refund-form]');
                if (refundForm) {
                    refundForm.addEventListener('submit', function (event) {
                        event.preventDefault();

                        const feedback = content.querySelector('[data-security-deposit-refund-feedback]');
                        const submitButton = refundForm.querySelector('button[type="submit"]');
                        const amountInput = refundForm.querySelector('input[name="refund_amount"]');
                        const methodInput = refundForm.querySelector('[name="refund_method"]');
                        const reasonInput = refundForm.querySelector('textarea[name="refund_reason"]');

                        const amount = Number(amountInput ? amountInput.value : NaN);
                        if (!Number.isFinite(amount) || amount <= 0) {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-red-700';
                                feedback.textContent = 'Enter a valid refund amount.';
                            }
                            return;
                        }

                        if (amount > refundableRemaining + 0.0001) {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-red-700';
                                feedback.textContent = 'Refund amount exceeds remaining refundable balance.';
                            }
                            return;
                        }

                        const reason = String(reasonInput ? reasonInput.value : '').trim();
                        if (reason.length < 5) {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-red-700';
                                feedback.textContent = 'Please provide a reason of at least 5 characters.';
                            }
                            return;
                        }

                        const refundMethod = String(methodInput ? methodInput.value : '').trim();

                        const confirmationTarget = isWalkInBooking
                            ? `record a ${refundMethod.replace('-', ' ')} refund`
                            : `refund via ${String(paymentPlatform).toUpperCase()}`;
                        const confirmed = window.confirm(`Are you sure you want to ${confirmationTarget} for $${amount.toFixed(2)}?\n\nReason:\n${reason}`);
                        if (!confirmed) {
                            return;
                        }

                        if (submitButton) {
                            submitButton.disabled = true;
                            submitButton.textContent = 'Refunding...';
                        }
                        if (feedback) {
                            feedback.className = 'mt-2 text-xs text-amber-800';
                            feedback.textContent = 'Processing refund...';
                        }

                        const payload = new URLSearchParams();
                        payload.set('order_id', String(Number(order.order_id || orderId)));
                        payload.set('refund_amount', amount.toFixed(2));
                        payload.set('refund_reason', reason);
                        if (refundMethod) {
                            payload.set('refund_method', refundMethod);
                        }
                        payload.set('csrf_token', ADMIN_CSRF_TOKEN);

                        fetch('/admin/orders/security-deposit/refund', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: payload.toString()
                        })
                        .then(async (response) => {
                            const rawText = await response.text();
                            let body = {};
                            if (rawText) {
                                try {
                                    body = JSON.parse(rawText);
                                } catch (_e) {
                                    body = { error: rawText.trim() };
                                }
                            }
                            if (!response.ok || body.error) {
                                throw new Error(toFriendlyAjaxError(body.error || rawText, `Refund failed (${response.status}).`));
                            }
                            return body;
                        })
                        .then(() => {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-green-700';
                                feedback.textContent = isWalkInBooking ? 'Walk-in refund recorded successfully.' : 'Refund processed successfully.';
                            }
                            openOrderModal(Number(order.order_id || orderId));
                        })
                        .catch((error) => {
                            if (feedback) {
                                feedback.className = 'mt-2 text-xs text-red-700';
                                feedback.textContent = toFriendlyAjaxError(error.message, 'Refund failed.');
                            }
                        })
                        .finally(() => {
                            if (submitButton) {
                                submitButton.disabled = false;
                                submitButton.textContent = 'Refund Deposit';
                            }
                        });
                    });
                }
            })
            .catch(error => {
                console.error('Error loading order details:', error);
                content.innerHTML = '<div class="text-red-500">Failed to load order details.</div>';
            });

    }
    function closeOrderModal() {
        const modal = document.getElementById('orderModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
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

                const depositValue = Number((form.querySelector('input[name="security_deposit"]') || {}).value || 0);
                const refundedValue = Number((form.querySelector('input[name="security_deposit_refunded_amount"]') || {}).value || 0);
                const refundableRemaining = Math.max(0, depositValue - refundedValue);
                const noRefundInitiated = depositValue > 0 && refundedValue <= 0;
                if (noRefundInitiated && refundableRemaining > 0) {
                    const refundCta = window.confirm(
                        `No security deposit refund has been initiated for this order.\n` +
                        `Refundable balance: $${refundableRemaining.toFixed(2)}\n\n` +
                        `Click OK to complete anyway, or Cancel to review refund first.`
                    );
                    if (!refundCta) {
                        e.preventDefault();
                        return;
                    }
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
