<div class="flex-1 flex flex-col items-center justify-center w-full">
        <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-6xl mx-auto border border-blue-200">
            <h2 class="text-3xl font-bold mb-8 text-[#062B41] tracking-tight">Reservations</h2>
            <!-- Filter and Pagination Controls (Top) -->
            <form method="get" class="flex flex-wrap items-center justify-between gap-4 mb-8">
                <div class="flex items-center gap-2">
                    <label for="status" class="font-semibold text-blue-900 text-lg">Show:</label>
                    <div class="relative">
                        <select name="status" id="status" onchange="this.form.submit()"
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
                ?>
                <div class="flex gap-2 justify-center items-center select-none">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $statusParam ?>" class="<?= $arrowClass ?> <?= $pillClass ?> mr-2">&laquo;</a>
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
                            echo '<a href="?page=' . $i . $statusParam . '" class="' . ($isActive ? $activeClass : $inactiveClass) . ' ' . $pillClass . '">' . $i . '</a>';
                        } else {
                            $dots = true;
                        }
                    }
                    ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $statusParam ?>" class="<?= $arrowClass ?> <?= $pillClass ?> ml-2">&raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </form>
            <?php if (!empty($reservations)): ?>
            <?php
                // Filter and group reservations by order_id
                $statusFilter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'pending';
                $grouped = [];
                foreach ($reservations as $res) {
                    $status = strtolower(trim($res['status']));
                    $include = false;
                    if ($statusFilter === 'all') {
                        $include = true;
                    } elseif ($statusFilter === 'completed') {
                        $include = ($status === 'completed');
                    } else { // 'pending' filter: show both pending and paid
                        $include = (in_array($status, ['pending', 'paid'], true));
                    }
                    if ($include && !empty($res['order_id'])) {
                        $grouped[$res['order_id']][] = $res;
                    }
                }
            ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-xl shadow text-base border border-gray-200">
                    <thead class="bg-blue-100 text-blue-900">
                        <tr>
                            <th class="py-3 px-4 text-center">Order ID</th>
                            <th class="py-3 px-4 text-center">Pickup Datetime</th>
                            <th class="py-3 px-4 text-center">Return Datetime</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $modalIndex = 0; $modalData = []; ?>
                        <?php foreach ($grouped as $orderId => $items): ?>
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-center">
                                <button type="button" onclick="document.getElementById('modal-<?= $modalIndex ?>').classList.remove('hidden')" class="text-blue-700 underline font-semibold hover:text-blue-900 cursor-pointer">
                                    <?= htmlspecialchars($orderId) ?>
                                </button>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-center">
                                <?= htmlspecialchars($items[0]['pickup_datetime']) ?>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-center">
                                <?= htmlspecialchars($items[0]['return_datetime']) ?>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-center">
                                <?= htmlspecialchars($items[0]['status']) ?>
                            </td>
                        </tr>
                        <?php 
                        // Prepare modal data for this order
                        $modalData[] = [
                            'modalIndex' => $modalIndex,
                            'orderId' => $orderId,
                            'items' => $items
                        ];
                        $modalIndex++;
                        ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Render all modals outside the table -->
            <?php foreach ($modalData as $modal): ?>
            <div id="modal-<?= $modal['modalIndex'] ?>" class="fixed inset-0 z-50 flex items-center justify-center hidden" style="background: rgba(0,0,0,0.7);" onclick="this.classList.add('hidden')">
                <div class="bg-white rounded-xl shadow-xl p-8 max-w-2xl w-full relative cursor-auto" onclick="event.stopPropagation()">
                    <button onclick="document.getElementById('modal-<?= $modal['modalIndex'] ?>').classList.add('hidden')" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 text-2xl cursor-pointer">&times;</button>
                    <h3 class="text-xl font-bold mb-4 text-blue-900">Reservation Details (Order #<?= htmlspecialchars($modal['orderId']) ?>)</h3>
                    <table class="w-full text-sm border">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="py-2 px-3">Reservation ID</th>
                                <th class="py-2 px-3">Scooter ID</th>
                                <th class="py-2 px-3">Pickup</th>
                                <th class="py-2 px-3">Return</th>
                                <th class="py-2 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modal['items'] as $res): ?>
                            <tr>
                                <td class="py-1 px-3 border-b"><?= htmlspecialchars($res['reservation_id']) ?></td>
                                <td class="py-1 px-3 border-b"><?= htmlspecialchars($res['scooter_id']) ?></td>
                                <td class="py-1 px-3 border-b"><?= htmlspecialchars($res['pickup_datetime']) ?></td>
                                <td class="py-1 px-3 border-b"><?= htmlspecialchars($res['return_datetime']) ?></td>
                                <td class="py-1 px-3 border-b"><?= htmlspecialchars($res['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
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
            ?>
            <div class="flex justify-center mt-8 gap-2 select-none items-center">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $statusParam ?>" class="<?= $arrowClass ?> <?= $pillClass ?> mr-2">&laquo;</a>
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
                        echo '<a href="?page=' . $i . $statusParam . '" class="' . ($isActive ? $activeClass : $inactiveClass) . ' ' . $pillClass . '">' . $i . '</a>';
                    } else {
                        $dots = true;
                    }
                }
                ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $statusParam ?>" class="<?= $arrowClass ?> <?= $pillClass ?> ml-2">&raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-gray-500 text-lg text-center">No pending or paid reservations at the moment</div>
            <?php endif; ?>
        </div>
    </div>
