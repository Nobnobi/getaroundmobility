<div class="flex-1 flex flex-col items-center justify-center w-full">
        <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-6xl mx-auto border border-blue-200">
            <h2 class="text-3xl font-bold mb-8 text-[#062B41] tracking-tight">Reservations</h2>
            <?php if (!empty($_SESSION['reservation_success'])): ?>
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <?= htmlspecialchars($_SESSION['reservation_success']) ?>
                </div>
                <?php unset($_SESSION['reservation_success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['reservation_error'])): ?>
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?= htmlspecialchars($_SESSION['reservation_error']) ?>
                </div>
                <?php unset($_SESSION['reservation_error']); ?>
            <?php endif; ?>
            <!-- Filter and Pagination Controls (Top) -->
            <form method="get" class="flex flex-wrap items-center justify-between gap-4 mb-8">
                <div class="flex items-center gap-2">
                    <label for="status" class="font-semibold text-blue-900 text-lg">Show:</label>
                    <div class="relative">
                        <select name="status" id="status"
                            class="appearance-none border-2 border-blue-300 rounded-xl px-4 py-2 pr-10 bg-white text-blue-900 font-semibold shadow focus:outline-none focus:ring-2 focus:ring-blue-400 transition cursor-pointer">
                            <option value="pending" <?= (!isset($_GET['status']) || $_GET['status'] === 'pending') ? 'selected' : '' ?>>Pending & Paid</option>
                            <option value="completed" <?= (isset($_GET['status']) && $_GET['status'] === 'completed') ? 'selected' : '' ?>>Completed</option>
                            <option value="all" <?= (isset($_GET['status']) && $_GET['status'] === 'all') ? 'selected' : '' ?>>All</option>
                        </select>
                        <span class="pointer-events-none absolute right-3 top-1/2 transform -translate-y-1/2 text-blue-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <label for="order_id" class="font-semibold text-blue-900 text-lg">Order:</label>
                    <div class="relative">
                        <select name="order_id" id="order_id"
                            class="appearance-none border-2 border-blue-300 rounded-xl px-4 py-2 pr-10 bg-white text-blue-900 font-semibold shadow focus:outline-none focus:ring-2 focus:ring-blue-400 transition cursor-pointer min-w-[180px]">
                            <option value="">All orders</option>
                            <?php foreach (($orderIds ?? []) as $oid): ?>
                                <option value="<?= (int)$oid ?>" <?= (isset($_GET['order_id']) && (int)$_GET['order_id'] === (int)$oid) ? 'selected' : '' ?>>
                                    Order #<?= (int)$oid ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="pointer-events-none absolute right-3 top-1/2 transform -translate-y-1/2 text-blue-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <label for="search" class="font-semibold text-blue-900 text-lg">Search:</label>
                    <input type="text" name="search" id="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Order ID, Reservation ID"
                        class="border-2 border-blue-300 rounded-xl px-4 py-2 bg-white text-blue-900 font-semibold shadow focus:outline-none focus:ring-2 focus:ring-blue-400 transition w-56" />
                    <button type="submit" class="ml-2 px-4 py-2 bg-blue-600 text-white rounded-xl font-semibold shadow hover:bg-blue-700 transition">Search</button>
                </div>
                <?php if (isset($totalPages) && $totalPages > 1): ?>
                <?php
                $window = 1; // pages before/after current
                $showFirst = 1;
                $showLast = $totalPages;
                $dots = false;
                $activeClass = 'bg-blue-600 text-white border-blue-600 font-bold shadow';
                $inactiveClass = 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50';
                $arrowClass = 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50';
                $pillClass = 'px-4 py-2 rounded-lg border transition font-semibold mx-0.5';
                $page = $page ?? 1;
                $statusParam = isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : '';
                $searchParam = isset($_GET['search']) && $_GET['search'] !== '' ? '&search=' . urlencode($_GET['search']) : '';
                $orderParam = isset($_GET['order_id']) && $_GET['order_id'] !== '' ? '&order_id=' . urlencode($_GET['order_id']) : '';
                ?>
                <div class="flex gap-2 justify-center items-center select-none">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $statusParam . $searchParam . $orderParam ?>" class="<?= $arrowClass ?> <?= $pillClass ?> mr-2">&laquo;</a>
                    <?php endif; ?>
                    <?php
                    for ($i = 1; $i <= $totalPages; $i++) {
                        if (
                            $i == $showFirst ||
                            $i == $showLast ||
                            ($i >= $page - $window && $i <= $page + $window)
                        ) {
                            if ($dots) {
                                echo '<span class="px-2 text-blue-700 font-bold">...</span>';
                                $dots = false;
                            }
                            $isActive = ($i == $page);
                            echo '<a href="?page=' . $i . $statusParam . $searchParam . $orderParam . '" class="' . ($isActive ? $activeClass : $inactiveClass) . ' ' . $pillClass . '">' . $i . '</a>';
                        } else {
                            $dots = true;
                        }
                    }
                    ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $statusParam . $searchParam . $orderParam ?>" class="<?= $arrowClass ?> <?= $pillClass ?> ml-2">&raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </form>
            <?php if (!empty($reservations)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-xl shadow text-base border border-gray-200">
                    <thead class="bg-blue-100 text-blue-900">
                        <tr>
                            <th class="py-3 px-4 text-center">Scooter #</th>
                            <th class="py-3 px-4 text-left">Product</th>
                            <th class="py-3 px-4 text-center">Order ID</th>
                            <th class="py-3 px-4 text-center">Pickup</th>
                            <th class="py-3 px-4 text-center">Return</th>
                            <th class="py-3 px-4 text-left">Notes</th>
                            <th class="py-3 px-4 text-left">Assign / Change Scooter</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $res): ?>
                        <?php
                            $resStatus = strtolower(trim($res['status']));
                            $statusFilter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'pending';
                            if ($statusFilter === 'pending' && !in_array($resStatus, ['pending', 'paid'], true)) continue;
                            if ($statusFilter === 'completed' && $resStatus !== 'completed') continue;

                            $badgeClass = match($resStatus) {
                                'pending'   => 'bg-yellow-100 text-yellow-800',
                                'paid'      => 'bg-blue-100 text-blue-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                default     => 'bg-gray-100 text-gray-600',
                            };
                        ?>
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-center font-bold text-[#062B41]">
                                <button
                                    type="button"
                                    onclick="openReservationOrderModal(<?= (int)$res['order_id'] ?>)"
                                    class="font-bold text-[#0086C9] hover:text-blue-700 cursor-pointer"
                                    title="View full order details">
                                    #<?= (int)$res['scooter_id'] ?>
                                </button>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-left text-gray-700">
                                <?= !empty($res['product_name']) ? htmlspecialchars($res['product_name']) : '<span class="text-gray-400">—</span>' ?>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-center">
                                <span class="font-semibold text-blue-900">
                                    <?= htmlspecialchars($res['order_id']) ?>
                                </span>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-center text-sm text-gray-700">
                                <span data-admin-datetime="<?= htmlspecialchars($res['pickup_datetime']) ?>"><?= htmlspecialchars($res['pickup_datetime']) ?></span>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-center text-sm text-gray-700">
                                <span data-admin-datetime="<?= htmlspecialchars($res['return_datetime']) ?>"><?= htmlspecialchars($res['return_datetime']) ?></span>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-left text-sm text-gray-700">
                                <?= !empty($res['notes']) ? nl2br(htmlspecialchars($res['notes'])) : '<span class="text-gray-400">No notes</span>' ?>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-left">
                                <form method="post" action="/admin/reservations/update" class="space-y-2 min-w-[260px]">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="reservation_id" value="<?= (int)$res['reservation_id'] ?>">
                                    <input type="hidden" name="redirect_query" value="<?= htmlspecialchars(http_build_query($_GET)) ?>">

                                    <?php $options = $assignableScooters[(int)$res['reservation_id']] ?? [(int)$res['scooter_id']]; ?>
                                    <select name="scooter_id" class="w-full rounded-lg border border-blue-300 px-3 py-2 text-sm font-semibold text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                        <?php foreach ($options as $sid): ?>
                                            <option value="<?= (int)$sid ?>" <?= ((int)$sid === (int)$res['scooter_id']) ? 'selected' : '' ?>>
                                                Scooter #<?= (int)$sid ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <textarea name="notes" rows="2" maxlength="1000" placeholder="Reminder/indentation notes..." class="w-full rounded-lg border border-blue-200 px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-300"><?= htmlspecialchars((string)($res['notes'] ?? '')) ?></textarea>

                                    <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition-colors">
                                        Save
                                    </button>
                                </form>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-center">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badgeClass ?>">
                                    <?= htmlspecialchars(ucfirst($res['status'])) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls (Bottom) -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <?php
            $window = 1;
            $showFirst = 1;
            $showLast = $totalPages;
            $dots = false;
            $activeClass = 'bg-blue-600 text-white border-blue-600 font-bold shadow';
            $inactiveClass = 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50';
            $arrowClass = 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50';
            $pillClass = 'px-4 py-2 rounded-lg border transition font-semibold mx-0.5';
            $page = $page ?? 1;
            $statusParam = isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : '';
            $searchParam = isset($_GET['search']) && $_GET['search'] !== '' ? '&search=' . urlencode($_GET['search']) : '';
            $orderParam = isset($_GET['order_id']) && $_GET['order_id'] !== '' ? '&order_id=' . urlencode($_GET['order_id']) : '';
            ?>
            <div class="flex justify-center mt-8 gap-2 select-none items-center">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $statusParam . $searchParam . $orderParam ?>" class="<?= $arrowClass ?> <?= $pillClass ?> mr-2">&laquo;</a>
                <?php endif; ?>
                <?php
                for ($i = 1; $i <= $totalPages; $i++) {
                    if (
                        $i == $showFirst ||
                        $i == $showLast ||
                        ($i >= $page - $window && $i <= $page + $window)
                    ) {
                        if ($dots) {
                            echo '<span class="px-2 text-blue-700 font-bold">...</span>';
                            $dots = false;
                        }
                        $isActive = ($i == $page);
                        echo '<a href="?page=' . $i . $statusParam . $searchParam . $orderParam . '" class="' . ($isActive ? $activeClass : $inactiveClass) . ' ' . $pillClass . '">' . $i . '</a>';
                    } else {
                        $dots = true;
                    }
                }
                ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $statusParam . $searchParam . $orderParam ?>" class="<?= $arrowClass ?> <?= $pillClass ?> ml-2">&raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-gray-500 text-lg text-center">No pending or paid reservations at the moment</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="reservation-order-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 px-4">
        <div id="reservation-order-modal-box" class="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-[#062B41]">Order Details</h3>
                <button type="button" onclick="closeReservationOrderModal()" class="text-2xl font-bold text-gray-400 hover:text-gray-700 cursor-pointer">&times;</button>
            </div>
            <div id="reservation-order-modal-content" class="max-h-[70vh] overflow-y-auto text-sm text-gray-700">
                <div class="py-8 text-center text-gray-500">Loading...</div>
            </div>
        </div>
    </div>

    <script>
    function openReservationOrderModal(orderId) {
        const modal = document.getElementById('reservation-order-modal');
        const content = document.getElementById('reservation-order-modal-content');
        if (!modal || !content) return;

        const esc = (value) => {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        content.innerHTML = '<div class="py-8 text-center text-gray-500">Loading...</div>';

        fetch(`/admin/orders/details?order_id=${orderId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.error || !data.order) {
                    content.innerHTML = `<div class="text-red-600">${esc(data.error || 'Order details unavailable.')}</div>`;
                    return;
                }

                const order = data.order;
                const customerName = [order.guest_first_name || '', order.guest_last_name || ''].join(' ').trim() || 'N/A';
                const customerEmail = order.guest_email || order.customer_email || 'N/A';
                const customerPhone = order.guest_phone || order.customer_phone || 'N/A';
                const fmt = (value) => {
                    if (!value) return 'N/A';
                    if (typeof window.formatAdminDateTime === 'function') {
                        return window.formatAdminDateTime(value);
                    }
                    const date = new Date(String(value).replace(' ', 'T'));
                    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
                };

                let html = `
                    <div class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-2">
                        <div><span class="font-semibold text-[#062B41]">Order ID:</span> ${esc(order.order_id ?? '')}</div>
                        <div><span class="font-semibold text-[#062B41]">Status:</span> ${esc(order.status ?? '')}</div>
                        <div><span class="font-semibold text-[#062B41]">Customer:</span> ${esc(customerName)}</div>
                        <div><span class="font-semibold text-[#062B41]">Email:</span> ${esc(customerEmail)}</div>
                        <div><span class="font-semibold text-[#062B41]">Phone:</span> ${esc(customerPhone)}</div>
                        <div><span class="font-semibold text-[#062B41]">Payment:</span> ${esc(order.payment_method ?? 'N/A')}</div>
                        <div><span class="font-semibold text-[#062B41]">Pickup:</span> ${fmt(order.pickup_datetime)}</div>
                        <div><span class="font-semibold text-[#062B41]">Return:</span> ${fmt(order.return_datetime)}</div>
                        <div><span class="font-semibold text-[#062B41]">Total:</span> $${Number(order.total_amount || 0).toFixed(2)}</div>
                        <div><span class="font-semibold text-[#062B41]">Ordered At:</span> ${fmt(order.order_date)}</div>
                    </div>
                `;

                if (order.notes) {
                    html += `<div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 p-3"><span class="font-semibold text-[#062B41]">Order Notes:</span><div class="mt-1 whitespace-pre-wrap">${esc(order.notes)}</div></div>`;
                }

                const items = Array.isArray(data.items) ? data.items : [];
                if (items.length > 0) {
                    const scooterIds = Array.from(new Set(
                        items
                            .map(item => Number(item.scooter_id || 0))
                            .filter(id => id > 0)
                    ));

                    if (scooterIds.length > 0) {
                        html += `
                            <div class="mb-4 rounded-xl border border-indigo-100 bg-indigo-50 p-3">
                                <div class="mb-2 font-semibold text-[#062B41]">Assigned Scooter IDs</div>
                                <div class="flex flex-wrap gap-2">
                                    ${scooterIds.map(id => `<span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-700 border border-indigo-200">#${id}</span>`).join('')}
                                </div>
                            </div>
                        `;
                    }

                    const groupedItemsMap = new Map();
                    items.forEach(item => {
                        const productName = item.product_name || '';
                        const variationName = item.variation_name || '';
                        const price = Number(item.price || 0);
                        const key = `${productName}__${variationName}__${price.toFixed(2)}`;

                        if (!groupedItemsMap.has(key)) {
                            groupedItemsMap.set(key, {
                                product_name: productName,
                                variation_name: variationName,
                                quantity: 0,
                                price,
                                scooter_ids: []
                            });
                        }

                        const grouped = groupedItemsMap.get(key);
                        grouped.quantity += Number(item.quantity || 1);
                        const sid = Number(item.scooter_id || 0);
                        if (sid > 0 && !grouped.scooter_ids.includes(sid)) {
                            grouped.scooter_ids.push(sid);
                        }
                    });

                    const groupedItems = Array.from(groupedItemsMap.values());

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
                                        <th class="border border-gray-200 px-2 py-1 text-right">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    groupedItems.forEach(item => {
                        const scooterCell = item.scooter_ids.length > 0
                            ? item.scooter_ids.map(id => `#${id}`).join(', ')
                            : '—';
                        html += `
                            <tr>
                                <td class="border border-gray-200 px-2 py-1">${esc(item.product_name || '')}</td>
                                <td class="border border-gray-200 px-2 py-1">${esc(item.variation_name || '')}</td>
                                <td class="border border-gray-200 px-2 py-1 text-center">${esc(scooterCell)}</td>
                                <td class="border border-gray-200 px-2 py-1 text-right">${item.quantity || 1}</td>
                                <td class="border border-gray-200 px-2 py-1 text-right">$${Number(item.price || 0).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    html += '</tbody></table></div>';
                }

                content.innerHTML = html;
                if (typeof window.applyAdminDateFormatting === 'function') {
                    window.applyAdminDateFormatting(content);
                }
            })
            .catch(() => {
                content.innerHTML = '<div class="text-red-600">Failed to load order details.</div>';
            });
    }

    function closeReservationOrderModal() {
        const modal = document.getElementById('reservation-order-modal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('reservation-order-modal');
        if (!modal) return;
        modal.addEventListener('mousedown', function (e) {
            if (e.target === modal) {
                closeReservationOrderModal();
            }
        });
    });
    </script>
