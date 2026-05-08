<?php
// At the top of your form view
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


$roleLabels = [
    'superAdmin' => 'Super Admin',
    'admin' => 'Admin',
    'staff' => 'Staff'
];
$roleKey = $_SESSION['admin_role'] ?? 'admin';
$roleLabel = $roleLabels[$roleKey] ?? ucfirst($roleKey);

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
            <span class="text-gray-600">Welcome, <?= htmlspecialchars($roleLabel) ?></span>
        </header>

        <!-- Analytics/Graphs -->
        <section class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded shadow p-4 flex flex-col items-center">
                <span class="text-gray-500">Total Sales</span>
                <span class="text-2xl font-bold mt-2">$<?= number_format($totalSales ?? 0, 2) ?></span>
            </div>
            <div class="bg-white rounded shadow p-4 flex flex-col items-center">
                <span class="text-gray-500">Total Orders</span>
                <span class="text-2xl font-bold mt-2"><?= $totalOrders ?? 0 ?></span>
            </div>
            <div class="bg-white rounded shadow p-4 flex flex-col items-center">
                <span class="text-gray-500">Completed Orders</span>
                <span class="text-2xl font-bold mt-2"><?= $completedOrders ?? 0 ?></span>
            </div>
        </section>

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
            $searchParam = isset($_GET['order_id_search']) && $_GET['order_id_search'] !== '' ? '&order_id_search=' . urlencode($_GET['order_id_search']) : '';
            // Left arrow
            if ($page > 1) {
                echo '<a href="?page=' . ($page - 1) . $searchParam . '" class="' . $arrowClass . ' ' . $pillClass . ' mr-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></a>';
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
                    echo '<a href="?page=' . $i . $searchParam . '" class="' . ($isActive ? $activeClass : $inactiveClass) . ' ' . $pillClass . '">' . $i . '</a>';
                } else {
                    $dots = true;
                }
            }
            // Right arrow
            if ($page < $totalPages) {
                echo '<a href="?page=' . ($page + 1) . $searchParam . '" class="' . $arrowClass . ' ' . $pillClass . ' ml-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></a>';
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
                
                <!-- Search Bar for Order ID -->
                <form method="GET" class="mb-4 flex gap-2 items-center">
                    <input type="text" name="order_id_search" value="<?= htmlspecialchars($_GET['order_id_search'] ?? '') ?>" placeholder="Search Order ID..." class="border rounded px-4 py-2 w-64 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="cursor-pointer ml-2 px-4 py-2 bg-[#062B41] text-white rounded-md font-semibold shadow hover:bg-[#08456b] transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-[#062B41] focus:ring-opacity-50">Search</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2">Order ID</th>
                                <th class="px-4 py-2">Customer</th>
                                <th class="px-4 py-2">Type</th> <!-- New: Customer Type -->
                                <th class="px-4 py-2">Email</th>
                                <th class="px-4 py-2">Sale Type</th> <!-- New: Sale Type -->
                                <th class="px-4 py-2">Total Amount</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Date Ordered</th>
                                <th>Pickup Date/Time</th>
                                <th>Return Date/Time</th>
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
                                <td class="px-4 py-2"><?= htmlspecialchars($order['order_date']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($order['pickup_datetime']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($order['return_datetime']) ?></td>

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
                                        <form method="post" action="/admin/orders/complete" class="inline" onsubmit="return confirm('Mark this order as completed?');">
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
            $searchParam = isset($_GET['order_id_search']) && $_GET['order_id_search'] !== '' ? '&order_id_search=' . urlencode($_GET['order_id_search']) : '';
            // Left arrow
            if ($page > 1) {
                echo '<a href="?page=' . ($page - 1) . $searchParam . '" class="' . $arrowClass . ' ' . $pillClass . ' mr-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></a>';
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
                    echo '<a href="?page=' . $i . $searchParam . '" class="' . ($isActive ? $activeClass : $inactiveClass) . ' ' . $pillClass . '">' . $i . '</a>';
                } else {
                    $dots = true;
                }
            }
            // Right arrow
            if ($page < $totalPages) {
                echo '<a href="?page=' . ($page + 1) . $searchParam . '" class="' . $arrowClass . ' ' . $pillClass . ' ml-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></a>';
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

    function openOrderModal(orderId) {
        document.getElementById('orderModal').classList.remove('hidden');
        const content = document.getElementById('orderModalContent');
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
                    content.innerHTML = `<div class="text-red-500">${data.error}</div>`;
                    return;
                }

                let html = `
                    <div class="mb-4">
                        <span class="font-semibold">Order ID:</span> ${data.order.order_id}<br>
                                                <span class="font-semibold">Customer:</span> ${
                                                    (data.order.guest_first_name && data.order.guest_last_name)
                                                        ? (data.order.guest_first_name + ' ' + data.order.guest_last_name)
                                                        : (data.order.customer_name || '')
                                                }<br>
                        <span class="font-semibold">Email:</span> ${data.order.guest_email || data.order.customer_email}<br>
                        <span class="font-semibold">Status:</span> ${data.order.status}<br>
                        <span class="font-semibold">Date:</span> ${data.order.order_date}<br>
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
                        <td class="border px-2 py-1">${item.name}</td>
                        <td class="border px-2 py-1">${item.variation ? `<span class='border border-gray-300 rounded px-2 py-0.5 text-xs bg-gray-50'>${item.variation}</span>` : ''}</td>
                        <td class="border px-2 py-1">$${item.price}</td>
                        <td class="border px-2 py-1">${item.quantity}</td>
                        <td class="border px-2 py-1 font-semibold">$${total}</td>
                    </tr>`;
                });
                html += `</tbody></table></div>`;

                if (data.contract_pdf) {
                    html += `
                        <div class="mb-2">
                            <a href="${data.contract_pdf}" target="_blank" class="bg-gray-500 text-white px-2 py-1 rounded text-xs hover:bg-gray-700">Open Contract PDF</a>
                        </div>
                    `;
                } else {
                    html += `<div class="mb-2 text-red-500 text-xs">Contract not found</div>`;
                }

                if (data.invoice_pdf) {
                    html += `
                        <div class="mb-4">
                            <a href="${data.invoice_pdf}" target="_blank" class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-800">Open Invoice PDF</a>
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

        document.querySelectorAll('form[action="/admin/orders/complete"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (form.dataset.submitting === 'true') {
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

<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
</html>