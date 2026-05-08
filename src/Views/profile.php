<?php $editing = $editing ?? false; ?>

<div class="min-h-screen bg-gray-50 py-8 px-4 sm:py-12">
    <div class="max-w-5xl mx-auto">
        <?php if ($editing): ?>
            <!-- Edit Profile Form -->
            <form method="post" action="/profile" class="max-w-lg mx-auto bg-white p-8 rounded-xl shadow mt-12">
                <h2 class="text-xl font-bold mb-6">Edit Profile</h2>
                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">First Name</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Last Name</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full border rounded px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" class="w-full border rounded px-3 py-2">
                </div>
                <button type="submit" class="bg-[#0086C9] text-white px-6 py-2 rounded font-semibold cursor-pointer">Save Changes</button>
                <a href="/profile" class="ml-4 text-gray-600 hover:underline">Cancel</a>
            </form>
        <?php else: ?>
            <!-- Main Profile Card (unchanged - perfect as is) -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden mt-12 relative">
                <!-- Cover Background -->
                <div class="h-32 sm:h-48 bg-gradient-to-r from-[#0086C9] to-blue-700"></div>

                <?php if ($is_own_profile ?? false): ?>
                    <div class="absolute top-4 right-4 z-10">
                        <a href="/profile?edit=1"
                           class="flex items-center gap-2 px-5 py-2.5 bg-white/90 backdrop-blur-sm hover:bg-white text-gray-800 font-medium rounded-full shadow-lg border border-gray-200 hover:border-gray-300 transition-all duration-200 text-sm sm:text-base">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            <span class="hidden sm:inline">Edit Profile</span>
                            <span class="sm:hidden">Edit</span>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="relative px-4 sm:px-8 pb-10">
                    <div class="flex flex-col sm:flex-row items-center sm:items-end gap-6 -mt-16 sm:-mt-20">
                        <div class="text-center sm:text-left flex-1">
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">
                                <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?: 'User Name' ?>
                            </h1>

                            <?php if (!empty($user['bio'])): ?>
                                <p class="mt-3 text-gray-700 max-w-lg mx-auto sm:mx-0 leading-relaxed">
                                    <?= nl2br(htmlspecialchars($user['bio'])) ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-4 flex flex-col sm:flex-row items-center sm:items-start gap-4 text-gray-600 text-sm">
                                <?php if (!empty($user['location'])): ?>
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <?= htmlspecialchars($user['location']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($user['website'])): ?>
                                    <a href="<?= htmlspecialchars($user['website']) ?>" target="_blank" rel="noopener"
                                       class="flex items-center gap-1.5 text-[#0086C9] hover:underline">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                        <?= parse_url($user['website'], PHP_URL_HOST) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Section with Tabs -->
            <div class="mt-8 bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Tabs Header -->
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px space-x-8 px-6 sm:px-8" aria-label="Tabs">
                        <a href="?tab=details" 
                           class="<?= (!isset($_GET['tab']) || $_GET['tab'] === 'details') ? 'border-[#0086C9] text-[#0086C9]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> 
                                  whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                            Account Details
                        </a>
                        <a href="?tab=orders" 
                           class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'orders') ? 'border-[#0086C9] text-[#0086C9]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> 
                                  whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                             Order History
                            <?php if (!empty($orders_count ?? 0)): ?>
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#0086C9] text-white">
                                    <?= $orders_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6 sm:p-8">
                    <?php if (!isset($_GET['tab']) || $_GET['tab'] === 'details'): ?>
                        <!-- Account Details Tab -->
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Personal Information</h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-8">
                            <div>
                                <dt class="text-base font-semibold text-gray-600 uppercase tracking-wide">Email</dt>
                                <dd class="mt-2 text-gray-900"><?= htmlspecialchars($user['email'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt class="text-base font-semibold text-gray-600 uppercase tracking-wide">Phone</dt>
                                <dd class="mt-2 text-gray-900"><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></dd>
                            </div>
                            <div>
                                <dt class="text-base font-semibold text-gray-600 uppercase tracking-wide">Address</dt>
                                <dd class="mt-2 text-gray-900"><?= htmlspecialchars($user['address'] ?? 'Not provided') ?></dd>
                            </div>
                            <div>
                                <dt class="text-base font-semibold text-gray-600 uppercase tracking-wide">Member Since</dt>
                                <dd class="mt-2 text-gray-900">
                                    <?= $user['created_at'] ? date('F j, Y', strtotime($user['created_at'])) : '—' ?>
                                </dd>
                            </div>
                        </dl>

                    <?php elseif ($_GET['tab'] === 'orders'): ?>
                        <!-- Past Orders Tab -->
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Order History</h3>

                        <?php if (empty($orders)): ?>
                            <div class="text-center py-12">
                                <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h18v18H3V3zM12 8v8m-4-4h8"/>
                                </svg>
                                <p class="mt-4 text-gray-500 text-lg">No orders yet</p>
                                <a href="/shop" class="mt-4 inline-block text-[#0086C9] hover:underline font-medium">Start shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($orders as $order): ?>
                                    <div class="border border-gray-200 rounded-lg p-5 hover:border-gray-300 transition">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="text-sm text-gray-600 mt-1">
                                                    <?= date('M j, Y \a\t g:i A', strtotime($order['order_date'] ?? $order['created_at'] ?? 'now')) ?>
                                                </p>
                                                <p class="text-sm text-gray-500 mt-2">
                                                    <?= htmlspecialchars($order['items_count']) ?> items • Total: <span class="font-semibold text-gray-900">$<?= number_format($order['total'], 2) ?></span>
                                                </p>
                                            </div>
                                            <?php
                                            $status = strtolower($order['status']);
                                            $statusColors = [
                                                'delivered' => 'bg-green-100 text-green-800',
                                                'completed' => 'bg-green-100 text-green-800',
                                                'shipped'   => 'bg-blue-100 text-blue-800',
                                                'processing'=> 'bg-yellow-100 text-yellow-800',
                                                'pending'   => 'bg-orange-100 text-orange-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                'paid'      => 'bg-purple-100 text-purple-800',
                                            ];
                                            $badgeClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium font-[Barlow] <?= $badgeClass ?>">
                                                <?= ucfirst(htmlspecialchars($order['status'])) ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($orderItems[$order['order_id']])): ?>
                                            <?php
                                            // Group items by product_id
                                            $grouped = [];
                                            foreach ($orderItems[$order['order_id']] as $item) {
                                                $pid = $item['product_id'];
                                                if (!isset($grouped[$pid])) {
                                                    $grouped[$pid] = [
                                                        'product_name' => $item['product_name'],
                                                        'quantity' => 0,
                                                        'price' => $item['price']
                                                    ];
                                                }
                                                $grouped[$pid]['quantity'] += (int)$item['quantity'];
                                            }
                                            ?>
                                            <ul class="mt-3 pl-4 list-disc text-gray-700 text-sm">
                                                <?php foreach ($grouped as $g): ?>
                                                    <li>
                                                        <?= htmlspecialchars($g['product_name']) ?>
                                                        × <?= $g['quantity'] ?>
                                                        — $<?= number_format($g['price'], 2) ?> each
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="flex items-center justify-center gap-2 mt-6">
                                    <?php if ($currentPage > 1): ?>
                                        <a href="?tab=orders&page=<?= $currentPage - 1 ?>"
                                           class="px-3 py-1.5 rounded border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition">
                                            &laquo; Prev
                                        </a>
                                    <?php endif; ?>

                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                        <a href="?tab=orders&page=<?= $p ?>"
                                           class="px-3 py-1.5 rounded border text-sm font-medium transition
                                                  <?= $p === $currentPage
                                                        ? 'bg-[#0086C9] border-[#0086C9] text-white'
                                                        : 'border-gray-300 text-gray-600 hover:bg-gray-50' ?>">
                                            <?= $p ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="?tab=orders&page=<?= $currentPage + 1 ?>"
                                           class="px-3 py-1.5 rounded border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition">
                                            Next &raquo;
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>