<?php
$roleLabels = [
    'superadmin' => 'Super Admin',
    'admin' => 'Admin',
    'staff' => 'Staff',
    'partner' => 'Partner'
];
$roleKey = $_SESSION['admin_role'] ?? 'admin';
$roleLabel = $roleLabels[$roleKey] ?? ucfirst($roleKey);
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
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Total Sales</span>
                <span class="text-3xl font-bold mt-2 text-green-600">$<?= number_format($totalSales ?? 0, 2) ?></span>
                <span class="text-xs text-gray-400 mt-1">All completed orders</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Total Orders</span>
                <span class="text-3xl font-bold mt-2 text-blue-600"><?= $totalOrders ?? 0 ?></span>
                <span class="text-xs text-gray-400 mt-1">All orders (any status)</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Completed</span>
                <span class="text-3xl font-bold mt-2 text-purple-600"><?= $completedOrders ?? 0 ?></span>
                <span class="text-xs text-gray-400 mt-1">Finished orders</span>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex flex-col">
                <span class="text-gray-500 text-sm font-semibold uppercase">Pending</span>
                <span class="text-3xl font-bold mt-2 text-orange-600"><?= $pendingOrders ?? 0 ?></span>
                <span class="text-xs text-gray-400 mt-1">Awaiting approval</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Sales Over Last 30 Days</h3>
                <canvas id="salesChart" class="max-h-80"></canvas>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Orders by Status</h3>
                <canvas id="statusChart" class="max-h-80"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Order Volume Over Last 30 Days</h3>
            <canvas id="volumeChart" class="max-h-80"></canvas>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" integrity="sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF" crossorigin="anonymous"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chartColors = {
            primary: '#0086C9',
            success: '#10B981',
            warning: '#F59E0B',
            danger: '#EF4444',
            purple: '#8B5CF6'
        };

        const salesData = <?php echo json_encode($salesByDate ?? []); ?>;
        const salesCtx = document.getElementById('salesChart');
        if (salesCtx) {
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: salesData.map(d => new Date(d.date).toLocaleDateString()),
                    datasets: [{
                        label: 'Daily Sales ($)',
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
                    labels: volumeData.map(d => new Date(d.date).toLocaleDateString()),
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
    });
</script>
