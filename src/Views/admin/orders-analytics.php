<?php
$roleLabels = [
    'superadmin' => 'Super Admin',
    'admin' => 'Admin',
    'staff' => 'Staff',
    'partner' => 'Partner'
];
$roleKey = $_SESSION['admin_role'] ?? 'admin';
$roleLabel = $roleLabels[$roleKey] ?? ucfirst($roleKey);

$summary = is_array($analyticsSummary ?? null) ? $analyticsSummary : [];
$totalAmount = (float)($summary['total_amount'] ?? 0);
$salesBeforeTax = (float)($summary['sales_before_tax'] ?? 0);
$salesAfterTax = (float)($summary['sales_after_tax'] ?? 0);
$taxCollected = (float)($summary['tax_collected'] ?? 0);
$securityDepositCollected = (float)($summary['security_deposit_collected'] ?? 0);
$securityDepositRefunded = (float)($summary['security_deposit_refunded'] ?? 0);
$netSalesAfterRefunds = (float)($summary['net_sales_after_refunds'] ?? 0);
$totalOrders = (int)($summary['total_orders'] ?? 0);
$completedOrders = (int)($summary['completed_orders'] ?? 0);
$pendingOrders = (int)($summary['pending_orders'] ?? 0);
$heardAboutBreakdownRows = is_array($heardAboutBreakdown ?? null) ? $heardAboutBreakdown : [];
$topHeardAbout = !empty($heardAboutBreakdownRows) ? (string)($heardAboutBreakdownRows[0]['heard_about'] ?? 'Not specified') : 'Not specified';
$topHeardAboutCount = !empty($heardAboutBreakdownRows) ? (int)($heardAboutBreakdownRows[0]['count'] ?? 0) : 0;

$selectedPeriod = (string)($period ?? 'month');
$selectedPeriodLabel = (string)($periodLabel ?? 'Past Month');
$periodChoices = is_array($periodOptions ?? null) ? $periodOptions : [];
?>

<div class="flex-1 flex flex-col">
    <header class="bg-white shadow p-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold">Orders Analytics</h1>
        <div class="flex items-center gap-4">
            <a href="/admin/orders" class="px-4 py-2 bg-[#062B41] text-white rounded-md font-semibold hover:bg-[#041b2b] transition-colors duration-150">Back to Orders</a>
            <span class="text-gray-600">Welcome, <?= htmlspecialchars($roleLabel) ?></span>
        </div>
    </header>

    <main class="p-6">
        <div class="mb-6 rounded-lg border border-[#c6d9e8] bg-white p-4 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="period" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Sales Period</label>
                    <select id="period" name="period" class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                        <?php foreach ($periodChoices as $key => $option): ?>
                            <option value="<?= htmlspecialchars((string)$key) ?>" <?= $selectedPeriod === (string)$key ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)($option['label'] ?? strtoupper((string)$key))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-[#062B41] px-4 py-2 text-sm font-semibold text-white hover:bg-[#041b2b] transition-colors duration-150">Apply</button>
                <span class="text-xs text-gray-500">Current window: <?= htmlspecialchars($selectedPeriodLabel) ?></span>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Total Amount Charged</span>
                <span class="text-3xl font-bold mt-2 text-blue-600">$<?= number_format($totalAmount, 2) ?></span>
                <span class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($selectedPeriodLabel) ?>, completed orders</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Sales Before Tax</span>
                <span class="text-3xl font-bold mt-2 text-emerald-600">$<?= number_format($salesBeforeTax, 2) ?></span>
                <span class="text-xs text-gray-400 mt-1">Estimated net sales excluding tax</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Sales After Tax</span>
                <span class="text-3xl font-bold mt-2 text-green-600">$<?= number_format($salesAfterTax, 2) ?></span>
                <span class="text-xs text-gray-400 mt-1">Product sales excluding deposit</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Tax Collected</span>
                <span class="text-3xl font-bold mt-2 text-violet-600">$<?= number_format($taxCollected, 2) ?></span>
                <span class="text-xs text-gray-400 mt-1">Computed from sales totals</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Total Orders</span>
                <span class="text-3xl font-bold mt-2 text-[#062B41]"><?= $totalOrders ?></span>
                <span class="text-xs text-gray-400 mt-1">All statuses in selected period</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Completed Orders</span>
                <span class="text-3xl font-bold mt-2 text-indigo-600"><?= $completedOrders ?></span>
                <span class="text-xs text-gray-400 mt-1">Status: completed</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Pending Orders</span>
                <span class="text-3xl font-bold mt-2 text-orange-600"><?= $pendingOrders ?></span>
                <span class="text-xs text-gray-400 mt-1">Status: pending</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Net Sales After Refunds</span>
                <span class="text-3xl font-bold mt-2 text-teal-600">$<?= number_format($netSalesAfterRefunds, 2) ?></span>
                <span class="text-xs text-gray-400 mt-1">Sales after tax minus refunded deposit</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Security Deposit Collected</span>
                <span class="text-2xl font-bold mt-2 text-amber-600">$<?= number_format($securityDepositCollected, 2) ?></span>
                <span class="text-xs text-gray-400 mt-1">Completed orders in selected period</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Security Deposit Refunded</span>
                <span class="text-2xl font-bold mt-2 text-rose-600">$<?= number_format($securityDepositRefunded, 2) ?></span>
                <span class="text-xs text-gray-400 mt-1">Refund records in selected period</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Top Heard About Source</span>
                <span class="text-2xl font-bold mt-2 text-cyan-700"><?= htmlspecialchars($topHeardAbout) ?></span>
                <span class="text-xs text-gray-400 mt-1"><?= number_format($topHeardAboutCount) ?> order<?= $topHeardAboutCount === 1 ? '' : 's' ?> in <?= htmlspecialchars($selectedPeriodLabel) ?></span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Sales After Tax Trend (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h3>
                <canvas id="salesChart" class="max-h-80"></canvas>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Orders by Status (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h3>
                <canvas id="statusChart" class="max-h-80"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Order Volume (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h3>
                <canvas id="volumeChart" class="max-h-80"></canvas>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Refund Trend (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h3>
                <canvas id="refundsChart" class="max-h-80"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Payment Channel Mix (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h3>
            <canvas id="paymentProviderChart" class="max-h-80"></canvas>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Heard About Us Mix (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h3>
                <canvas id="heardAboutChart" class="max-h-80"></canvas>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Referral Source Statistics</h3>
                <?php if (!empty($heardAboutBreakdownRows)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm border border-gray-200">
                            <thead class="bg-gray-50 text-gray-700">
                                <tr>
                                    <th class="px-3 py-2 text-left border-b border-gray-200">Source</th>
                                    <th class="px-3 py-2 text-right border-b border-gray-200">Orders</th>
                                    <th class="px-3 py-2 text-right border-b border-gray-200">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($heardAboutBreakdownRows as $row): ?>
                                    <?php
                                        $source = (string)($row['heard_about'] ?? 'Not specified');
                                        $count = (int)($row['count'] ?? 0);
                                        $share = $totalOrders > 0 ? ($count / $totalOrders) * 100 : 0;
                                    ?>
                                    <tr class="border-b border-gray-100">
                                        <td class="px-3 py-2"><?= htmlspecialchars($source) ?></td>
                                        <td class="px-3 py-2 text-right font-semibold text-[#062B41]"><?= number_format($count) ?></td>
                                        <td class="px-3 py-2 text-right"><?= number_format($share, 1) ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-500">No referral source data available for this period yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" integrity="sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF" crossorigin="anonymous"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const formatChartDate = function(value) {
            const raw = String(value || '').trim();
            if (!raw) {
                return '';
            }

            const parsed = new Date(raw + 'T00:00:00');
            if (Number.isNaN(parsed.getTime())) {
                return raw;
            }

            const monthNames = ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'May', 'Jun.', 'Jul.', 'Aug.', 'Sep.', 'Oct.', 'Nov.', 'Dec.'];
            return `${monthNames[parsed.getMonth()]} ${parsed.getDate()}, ${parsed.getFullYear()}`;
        };

        const chartColors = {
            primary: '#0086C9',
            success: '#10B981',
            warning: '#F59E0B',
            danger: '#EF4444',
            purple: '#8B5CF6',
            indigo: '#4F46E5',
            rose: '#E11D48',
            teal: '#0F766E'
        };

        const salesData = <?php echo json_encode($salesByDate ?? []); ?>;
        const salesCtx = document.getElementById('salesChart');
        if (salesCtx) {
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: salesData.map(d => formatChartDate(d.date)),
                    datasets: [{
                        label: 'Sales After Tax ($)',
                        data: salesData.map(d => parseFloat(d.total || 0)),
                        borderColor: chartColors.primary,
                        backgroundColor: 'rgba(0, 134, 201, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: chartColors.primary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sales ($)'
                            }
                        }
                    }
                }
            });
        }

        const statusData = <?php echo json_encode($ordersByStatus ?? []); ?>;
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx && statusData.length > 0) {
            const statusLabels = statusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1));
            const statusCounts = statusData.map(d => d.count);
            const statusColors = [chartColors.warning, chartColors.primary, chartColors.success, chartColors.danger, chartColors.purple];

            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: statusColors.slice(0, statusLabels.length),
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        const volumeData = <?php echo json_encode($orderCountByDate ?? []); ?>;
        const volumeCtx = document.getElementById('volumeChart');
        if (volumeCtx) {
            new Chart(volumeCtx, {
                type: 'bar',
                data: {
                    labels: volumeData.map(d => formatChartDate(d.date)),
                    datasets: [{
                        label: 'Orders per Day',
                        data: volumeData.map(d => parseInt(d.count || 0, 10)),
                        backgroundColor: chartColors.success,
                        borderColor: '#047857',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        const refundData = <?php echo json_encode($refundsByDate ?? []); ?>;
        const refundsCtx = document.getElementById('refundsChart');
        if (refundsCtx) {
            new Chart(refundsCtx, {
                type: 'line',
                data: {
                    labels: refundData.map(d => formatChartDate(d.date)),
                    datasets: [{
                        label: 'Refunded Amount ($)',
                        data: refundData.map(d => parseFloat(d.total || 0)),
                        borderColor: chartColors.rose,
                        backgroundColor: 'rgba(225, 29, 72, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: chartColors.rose,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Refunds ($)'
                            }
                        }
                    }
                }
            });
        }

        const providerData = <?php echo json_encode($paymentProviderBreakdown ?? []); ?>;
        const providerCtx = document.getElementById('paymentProviderChart');
        if (providerCtx && providerData.length > 0) {
            const providerLabels = providerData.map(d => String(d.provider || 'unknown').toUpperCase());
            const providerCounts = providerData.map(d => parseInt(d.count || 0, 10));
            const providerColors = [
                chartColors.primary,
                chartColors.success,
                chartColors.warning,
                chartColors.purple,
                chartColors.indigo,
                chartColors.teal
            ];

            new Chart(providerCtx, {
                type: 'doughnut',
                data: {
                    labels: providerLabels,
                    datasets: [{
                        data: providerCounts,
                        backgroundColor: providerColors.slice(0, providerLabels.length),
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        const heardAboutData = <?php echo json_encode($heardAboutBreakdownRows ?? []); ?>;
        const heardAboutCtx = document.getElementById('heardAboutChart');
        if (heardAboutCtx && heardAboutData.length > 0) {
            const heardAboutLabels = heardAboutData.map(d => String(d.heard_about || 'Not specified'));
            const heardAboutCounts = heardAboutData.map(d => parseInt(d.count || 0, 10));
            const heardAboutColors = [
                chartColors.primary,
                chartColors.success,
                chartColors.warning,
                chartColors.purple,
                chartColors.indigo,
                chartColors.teal,
                chartColors.rose,
                '#0EA5E9',
                '#14B8A6',
                '#F97316'
            ];

            new Chart(heardAboutCtx, {
                type: 'doughnut',
                data: {
                    labels: heardAboutLabels,
                    datasets: [{
                        data: heardAboutCounts,
                        backgroundColor: heardAboutColors.slice(0, heardAboutLabels.length),
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    });
</script>
