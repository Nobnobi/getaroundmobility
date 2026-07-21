<?php
$activeTab = ($activeTab ?? 'audit') === 'login-attempts' ? 'login-attempts' : 'audit';
$logs = $logs ?? [];
$loginAttempts = $loginAttempts ?? [];
$actions = $actions ?? [];
$adminOptions = $adminOptions ?? [];
$filters = $filters ?? [];
$loginAttemptFilters = $loginAttemptFilters ?? [];
$loginAreas = $loginAreas ?? [];
$loginAttemptUsernames = $loginAttemptUsernames ?? [];
$page = max(1, (int)($page ?? 1));
$loginAttemptPage = max(1, (int)($loginAttemptPage ?? 1));
$totalPages = max(1, (int)($totalPages ?? 1));
$totalLoginAttemptPages = max(1, (int)($totalLoginAttemptPages ?? 1));
$totalLogs = (int)($totalLogs ?? 0);
$totalLoginAttempts = (int)($totalLoginAttempts ?? 0);
$perPage = (int)($perPage ?? 15);

$esc = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$buildPageLink = function (string $pageKey, int $pageNumber, string $tab) {
    $query = $_GET;
    $query['tab'] = $tab;
    $query[$pageKey] = $pageNumber;
    return '?' . http_build_query($query);
};

$tabLink = function (string $tab) {
    $query = $_GET;
    $query['tab'] = $tab;
    return '?' . http_build_query($query);
};

$exportLink = function (string $tab) {
    $query = $_GET;
    $query['tab'] = $tab;
    return '/admin/audit-logs/export?' . http_build_query($query);
};

$formatAction = function ($action) {
    return ucwords(str_replace('_', ' ', (string)$action));
};

$formatDetails = function ($json) use ($esc) {
    if (!$json) {
        return '<span class="text-gray-400">None</span>';
    }

    $decoded = json_decode((string)$json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '<span class="break-all">' . $esc($json) . '</span>';
    }

    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return '<details class="max-w-xl"><summary class="cursor-pointer text-[#0086C9] hover:underline">View details</summary><pre class="mt-2 max-h-64 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100 whitespace-pre-wrap break-words">' . $esc($pretty) . '</pre></details>';
};

$tabClass = function (string $tab) use ($activeTab) {
    return $activeTab === $tab
        ? 'border-[#0086C9] bg-[#0086C9] text-white'
        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50';
};

$formatFilterDate = function ($value) {
    if (!$value) {
        return 'Select date';
    }
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('M j, Y', $timestamp) : (string)$value;
};
?>

<div class="flex-1 p-8">
    <div class="max-w-7xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-[#062B41]">Security Logs</h1>
            <p class="mt-1 text-sm text-gray-500">Super Admin view of important admin actions and login attempt activity.</p>
        </div>

        <div class="mb-6 flex flex-wrap gap-2">
            <a href="<?= $esc($tabLink('audit')) ?>" class="rounded-lg border px-4 py-2 text-sm font-semibold <?= $tabClass('audit') ?>">
                Audit Logs
                <span class="ml-2 rounded-full bg-black/10 px-2 py-0.5 text-xs"><?= number_format($totalLogs) ?></span>
            </a>
            <a href="<?= $esc($tabLink('login-attempts')) ?>" class="rounded-lg border px-4 py-2 text-sm font-semibold <?= $tabClass('login-attempts') ?>">
                Login Attempts
                <span class="ml-2 rounded-full bg-black/10 px-2 py-0.5 text-xs"><?= number_format($totalLoginAttempts) ?></span>
            </a>
        </div>

        <?php if ($activeTab === 'audit'): ?>
            <form method="get" action="/admin/audit-logs" class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <input type="hidden" name="tab" value="audit">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Action</label>
                        <select name="action" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">All actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?= $esc($action) ?>" <?= (($filters['action'] ?? '') === $action) ? 'selected' : '' ?>>
                                    <?= $esc($formatAction($action)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Admin</label>
                        <select name="admin_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">All admins</option>
                            <?php foreach ($adminOptions as $admin): ?>
                                <?php $adminId = (int)($admin['admin_id'] ?? 0); ?>
                                <option value="<?= $adminId ?>" <?= ((int)($filters['admin_id'] ?? 0) === $adminId) ? 'selected' : '' ?>>
                                    <?= $esc($admin['admin_username'] ?: ('Admin #' . $adminId)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Target</label>
                        <select name="target_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <?php $targetFilter = (string)($filters['target_type'] ?? ''); ?>
                            <option value="">All targets</option>
                            <?php foreach (['order', 'promo_code', 'blocked_date'] as $targetType): ?>
                                <option value="<?= $esc($targetType) ?>" <?= $targetFilter === $targetType ? 'selected' : '' ?>>
                                    <?= $esc(ucwords(str_replace('_', ' ', $targetType))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">From</label>
                        <input type="hidden" id="auditDateFrom" name="date_from" value="<?= $esc($filters['date_from'] ?? '') ?>">
                        <button type="button" class="js-security-date-trigger flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-target-input="auditDateFrom" data-target-label="auditDateFromLabel" data-title="Select audit start date">
                            <span id="auditDateFromLabel"><?= $esc($formatFilterDate($filters['date_from'] ?? '')) ?></span>
                            <span class="text-[#0086C9]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                        </button>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">To</label>
                        <input type="hidden" id="auditDateTo" name="date_to" value="<?= $esc($filters['date_to'] ?? '') ?>">
                        <button type="button" class="js-security-date-trigger flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-target-input="auditDateTo" data-target-label="auditDateToLabel" data-title="Select audit end date">
                            <span id="auditDateToLabel"><?= $esc($formatFilterDate($filters['date_to'] ?? '')) ?></span>
                            <span class="text-[#0086C9]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                        </button>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Search</label>
                        <input type="text" name="search" value="<?= $esc($filters['search'] ?? '') ?>" placeholder="Admin, IP, detail..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <button type="submit" class="rounded-lg bg-[#0086C9] px-4 py-2 text-sm font-semibold text-white hover:bg-[#08456b]">Filter</button>
                    <a href="/admin/audit-logs?tab=audit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    <a href="<?= $esc($exportLink('audit')) ?>" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">Export CSV</a>
                    <span class="ml-auto text-sm text-gray-500"><?= number_format($totalLogs) ?> log<?= $totalLogs === 1 ? '' : 's' ?></span>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <?php if (empty($logs)): ?>
                    <div class="py-16 text-center text-gray-400">
                        <p class="text-lg font-semibold">No audit logs found</p>
                        <p class="mt-1 text-sm">Try clearing filters or performing a logged admin action.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Admin</th>
                                    <th class="px-4 py-3">Action</th>
                                    <th class="px-4 py-3">Target</th>
                                    <th class="px-4 py-3">Details</th>
                                    <th class="px-4 py-3">IP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($logs as $log): ?>
                                    <tr class="align-top hover:bg-gray-50">
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-600"><?= $esc($log['created_at'] ?? '') ?></td>
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-[#062B41]"><?= $esc($log['admin_username'] ?? 'Unknown') ?></div>
                                            <div class="text-xs text-gray-500"><?= $esc($log['admin_role'] ?? '') ?><?= !empty($log['admin_id']) ? ' #' . (int)$log['admin_id'] : '' ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                                <?= $esc($formatAction($log['action'] ?? '')) ?>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                                            <?= $esc($log['target_type'] ?? 'N/A') ?>
                                            <?php if (!empty($log['target_id'])): ?>
                                                <div class="text-xs text-gray-500">#<?= (int)$log['target_id'] ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700"><?= $formatDetails($log['details_json'] ?? null) ?></td>
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-500"><?= $esc($log['ip_address'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="mt-5 flex items-center justify-between text-sm">
                    <div class="text-gray-500">Page <?= $page ?> of <?= $totalPages ?>, showing up to <?= $perPage ?> per page</div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?= $esc($buildPageLink('audit_page', $page - 1, 'audit')) ?>" class="rounded-lg border border-gray-300 px-3 py-2 font-semibold text-gray-700 hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $esc($buildPageLink('audit_page', $page + 1, 'audit')) ?>" class="rounded-lg border border-gray-300 px-3 py-2 font-semibold text-gray-700 hover:bg-gray-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="get" action="/admin/audit-logs" class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <input type="hidden" name="tab" value="login-attempts">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Area</label>
                        <select name="login_area" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">All areas</option>
                            <?php foreach ($loginAreas as $area): ?>
                                <option value="<?= $esc($area) ?>" <?= (($loginAttemptFilters['login_area'] ?? '') === $area) ? 'selected' : '' ?>>
                                    <?= $esc(ucwords(str_replace('_', ' ', $area))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Username</label>
                        <select name="login_username" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">All usernames</option>
                            <?php foreach ($loginAttemptUsernames as $username): ?>
                                <option value="<?= $esc($username) ?>" <?= (($loginAttemptFilters['username'] ?? '') === $username) ? 'selected' : '' ?>>
                                    <?= $esc($username) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Result</label>
                        <?php $resultFilter = (string)($loginAttemptFilters['result'] ?? ''); ?>
                        <select name="login_result" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">All results</option>
                            <option value="failed" <?= $resultFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                            <option value="success" <?= $resultFilter === 'success' ? 'selected' : '' ?>>Success</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">From</label>
                        <input type="hidden" id="loginDateFrom" name="login_date_from" value="<?= $esc($loginAttemptFilters['date_from'] ?? '') ?>">
                        <button type="button" class="js-security-date-trigger flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-target-input="loginDateFrom" data-target-label="loginDateFromLabel" data-title="Select login attempt start date">
                            <span id="loginDateFromLabel"><?= $esc($formatFilterDate($loginAttemptFilters['date_from'] ?? '')) ?></span>
                            <span class="text-[#0086C9]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                        </button>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">To</label>
                        <input type="hidden" id="loginDateTo" name="login_date_to" value="<?= $esc($loginAttemptFilters['date_to'] ?? '') ?>">
                        <button type="button" class="js-security-date-trigger flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-target-input="loginDateTo" data-target-label="loginDateToLabel" data-title="Select login attempt end date">
                            <span id="loginDateToLabel"><?= $esc($formatFilterDate($loginAttemptFilters['date_to'] ?? '')) ?></span>
                            <span class="text-[#0086C9]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                        </button>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Search</label>
                        <input type="text" name="login_search" value="<?= $esc($loginAttemptFilters['search'] ?? '') ?>" placeholder="Username, IP..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <button type="submit" class="rounded-lg bg-[#0086C9] px-4 py-2 text-sm font-semibold text-white hover:bg-[#08456b]">Filter</button>
                    <a href="/admin/audit-logs?tab=login-attempts" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    <a href="<?= $esc($exportLink('login-attempts')) ?>" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">Export CSV</a>
                    <span class="ml-auto text-sm text-gray-500"><?= number_format($totalLoginAttempts) ?> attempt<?= $totalLoginAttempts === 1 ? '' : 's' ?></span>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <?php if (empty($loginAttempts)): ?>
                    <div class="py-16 text-center text-gray-400">
                        <p class="text-lg font-semibold">No login attempts found</p>
                        <p class="mt-1 text-sm">Try clearing filters or testing a login attempt.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Area</th>
                                    <th class="px-4 py-3">Username</th>
                                    <th class="px-4 py-3">IP</th>
                                    <th class="px-4 py-3">Result</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($loginAttempts as $attempt): ?>
                                    <?php $successful = (int)($attempt['successful'] ?? 0) === 1; ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-600"><?= $esc($attempt['attempted_at'] ?? '') ?></td>
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-700"><?= $esc($attempt['login_area'] ?? '') ?></td>
                                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-[#062B41]"><?= $esc($attempt['username'] ?? '') ?></td>
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-500"><?= $esc($attempt['ip_address'] ?? '') ?></td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $successful ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' ?>">
                                                <?= $successful ? 'Success' : 'Failed' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($totalLoginAttemptPages > 1): ?>
                <div class="mt-5 flex items-center justify-between text-sm">
                    <div class="text-gray-500">Page <?= $loginAttemptPage ?> of <?= $totalLoginAttemptPages ?>, showing up to <?= $perPage ?> per page</div>
                    <div class="flex gap-2">
                        <?php if ($loginAttemptPage > 1): ?>
                            <a href="<?= $esc($buildPageLink('login_page', $loginAttemptPage - 1, 'login-attempts')) ?>" class="rounded-lg border border-gray-300 px-3 py-2 font-semibold text-gray-700 hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($loginAttemptPage < $totalLoginAttemptPages): ?>
                            <a href="<?= $esc($buildPageLink('login_page', $loginAttemptPage + 1, 'login-attempts')) ?>" class="rounded-lg border border-gray-300 px-3 py-2 font-semibold text-gray-700 hover:bg-gray-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div id="securityDateModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/55 px-3 py-4 sm:px-4">
    <div class="w-full max-w-md max-h-[92vh] overflow-y-auto rounded-[28px] bg-white p-5 shadow-2xl sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Filter Date</p>
                <h2 id="securityDateModalTitle" class="mt-1 text-2xl font-bold text-[#062B41]">Select date</h2>
                <p class="mt-2 text-sm text-gray-500">Choose a date for the current security log filter.</p>
            </div>
            <button type="button" id="securityDateModalClose" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Close date picker modal">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="mt-6">
            <label class="mb-2 block text-sm font-medium text-[#062B41]">Date</label>
            <input type="text" id="securityDateModalInput" class="hidden" readonly>
            <div id="securityDateModalCalendar" class="security-date-modal-calendar rounded-2xl border border-[#c8d9e6] bg-[#f9fcff] p-2 shadow-sm"></div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
            <button type="button" id="securityDateModalClear" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">Clear date</button>
            <div class="flex flex-col-reverse gap-3 sm:flex-row">
                <button type="button" id="securityDateModalCancel" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100">Cancel</button>
                <button type="button" id="securityDateModalSave" class="rounded-xl bg-[#0086C9] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#066fa6]">Set date</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" integrity="sha384-5JqMv4L/Xa0hfvtF06qboNdhvuYXUku9ZrhZh3bSk8VXF0A/RuSLHpLsSV9Zqhl6" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('securityDateModal');
    const modalTitle = document.getElementById('securityDateModalTitle');
    const modalInput = document.getElementById('securityDateModalInput');
    const modalCalendar = document.getElementById('securityDateModalCalendar');
    const closeButton = document.getElementById('securityDateModalClose');
    const cancelButton = document.getElementById('securityDateModalCancel');
    const clearButton = document.getElementById('securityDateModalClear');
    const saveButton = document.getElementById('securityDateModalSave');
    let activeInput = null;
    let activeLabel = null;
    let picker = null;

    function formatDisplayDate(value) {
        if (!value) return 'Select date';
        const parts = String(value).split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) return value;
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function ensurePicker() {
        if (picker || !modalInput || !modalCalendar || typeof flatpickr !== 'function') return;
        picker = flatpickr(modalInput, {
            inline: true,
            dateFormat: 'Y-m-d',
            clickOpens: false,
            disableMobile: true,
            appendTo: modalCalendar
        });
    }

    function openDateModal(trigger) {
        ensurePicker();
        if (!modal || !picker) return;
        activeInput = document.getElementById(trigger.dataset.targetInput || '');
        activeLabel = document.getElementById(trigger.dataset.targetLabel || '');
        if (!activeInput) return;

        modalTitle.textContent = trigger.dataset.title || 'Select date';
        picker.setDate(activeInput.value || null, false);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeDateModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function applyDate(value) {
        if (!activeInput) return;
        activeInput.value = value || '';
        if (activeLabel) {
            activeLabel.textContent = formatDisplayDate(value);
        }
    }

    document.querySelectorAll('.js-security-date-trigger').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            openDateModal(trigger);
        });
    });

    if (saveButton) {
        saveButton.addEventListener('click', function () {
            const selected = picker && picker.selectedDates[0] ? picker.formatDate(picker.selectedDates[0], 'Y-m-d') : '';
            applyDate(selected);
            closeDateModal();
        });
    }
    if (clearButton) {
        clearButton.addEventListener('click', function () {
            if (picker) picker.clear();
            applyDate('');
            closeDateModal();
        });
    }
    [closeButton, cancelButton].forEach(function (button) {
        if (!button) return;
        button.addEventListener('click', closeDateModal);
    });
    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeDateModal();
            }
        });
    }
});
</script>
<style>
.security-date-modal-calendar .flatpickr-calendar {
    width: 100%;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.security-date-modal-calendar .flatpickr-innerContainer,
.security-date-modal-calendar .flatpickr-rContainer,
.security-date-modal-calendar .flatpickr-days,
.security-date-modal-calendar .dayContainer {
    width: 100%;
    max-width: none;
}

.security-date-modal-calendar .flatpickr-day.selected,
.security-date-modal-calendar .flatpickr-day.startRange,
.security-date-modal-calendar .flatpickr-day.endRange {
    background: #0086c9;
    border-color: #0086c9;
}

.security-date-modal-calendar .flatpickr-day.today {
    border-color: #0b5f8a;
}
</style>
