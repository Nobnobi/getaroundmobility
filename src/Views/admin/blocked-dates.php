<?php
$canEditBlockedDates = !empty($canEditBlockedDates);
$blockedDates = is_array($blockedDates ?? null) ? $blockedDates : [];

$formatBlockedDate = static function ($value) {
    if (empty($value)) {
        return '';
    }
    $ts = strtotime((string)$value);
    return $ts ? date('F j, Y', $ts) : (string)$value;
};

$formatCreatedAt = static function ($value) {
    if (empty($value)) {
        return '';
    }
    $ts = strtotime((string)$value);
    return $ts ? date('M j, Y g:i a', $ts) : (string)$value;
};

$blockedDateKeys = [];
foreach ($blockedDates as $blocked) {
    $value = trim((string)($blocked['blocked_date'] ?? ''));
    if ($value !== '') {
        $blockedDateKeys[] = $value;
    }
}
$blockedDateKeys = array_values(array_unique($blockedDateKeys));
?>

<body class="bg-gray-100 min-h-screen flex">
    <div class="flex-1 flex flex-col">
        <header class="border-b border-[#d7e7f2] bg-white/90 px-6 py-5 backdrop-blur">
            <div class="flex flex-col gap-1 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Booking Controls</p>
                    <h1 class="text-3xl font-bold text-[#062B41]">Blocked Dates</h1>
                </div>
                <span class="text-sm text-gray-600">Keep holidays and no-service days out of online booking.</span>
            </div>
        </header>

        <main class="flex-1 bg-[radial-gradient(circle_at_top,_rgba(0,134,201,0.12),_transparent_38%),linear-gradient(180deg,_#f5fbff_0%,_#f8fafc_38%,_#eef4f8_100%)] p-6 md:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <section class="overflow-hidden rounded-[28px] border border-[#cfe3f1] bg-white shadow-[0_20px_60px_rgba(6,43,65,0.08)]">
                    <div class="grid gap-0 lg:grid-cols-[1.2fr_0.8fr]">
                        <div class="border-b border-[#dbe8f2] px-6 py-7 lg:border-b-0 lg:border-r lg:px-8">
                            <div class="max-w-2xl">
                                <h2 class="text-2xl font-bold text-[#062B41]">Control which days customers can book online</h2>
                                <p class="mt-2 text-[15px] leading-7 text-gray-600">Use this page to block holidays, closures, or any date that should never appear as selectable in the booking form. Blocked days are applied directly to the pickup and return calendar.</p>
                            </div>

                            <div class="mt-5 grid gap-3 md:grid-cols-3">
                                <div class="rounded-2xl border border-[#d8e9f4] bg-[#f7fbfe] px-4 py-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Live Rule</div>
                                    <div class="mt-2 text-lg font-bold text-[#062B41]">24 hours ahead</div>
                                    <p class="mt-1 text-sm text-gray-600">Today is never available for online pickup.</p>
                                </div>
                                <div class="rounded-2xl border border-[#d8e9f4] bg-[#f7fbfe] px-4 py-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Business Hours</div>
                                    <div class="mt-2 text-lg font-bold text-[#062B41]">8:30am to 5:30pm</div>
                                    <p class="mt-1 text-sm text-gray-600">Pickup and return slots stay inside operating hours.</p>
                                </div>
                                <div class="rounded-2xl border border-[#d8e9f4] bg-[#f7fbfe] px-4 py-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Blocked Days</div>
                                    <div class="mt-2 text-lg font-bold text-[#062B41]"><?= count($blockedDates) ?></div>
                                    <p class="mt-1 text-sm text-gray-600">Dates currently hidden from online booking.</p>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-7 lg:px-8">
                            <div class="rounded-[24px] border border-[#d5e6f2] bg-white p-4 shadow-sm">
                                <div class="flex flex-col gap-2 border border-[#D9D9D9] rounded-2xl p-3">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Block a date</label>
                                        <p class="text-sm text-gray-500">Prevent customers from choosing a holiday or unavailable day.</p>
                                    </div>
                                    <?php if ($canEditBlockedDates): ?>
                                        <form method="POST" action="/admin/orders/blocked-dates/add" class="space-y-3">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? '')) ?>">
                                            <input type="text" id="blockedDateInput" name="blocked_date" class="w-full rounded-xl border border-[#D9D9D9] bg-white px-4 py-3 text-sm text-[#062B41] focus:outline-none focus:ring-2 focus:ring-[#0086C9]" placeholder="Select blocked date" autocomplete="off" required>
                                            <input type="text" name="reason" maxlength="160" placeholder="Reason (example: Christmas Day)" class="w-full rounded-xl border border-[#D9D9D9] bg-white px-4 py-3 text-sm text-[#062B41] focus:outline-none focus:ring-2 focus:ring-[#0086C9]">
                                            <button type="submit" class="w-full cursor-pointer rounded-xl bg-[#0086C9] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#066fa6]">Save blocked date</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-4 text-sm text-blue-800">
                                            You can view blocked dates, but only Admin and Super Admin can edit them.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if (!empty($_SESSION['order_cancel_message'])): ?>
                    <div class="rounded-2xl border border-yellow-200 bg-yellow-50 px-5 py-4 text-sm font-medium text-yellow-800 shadow-sm">
                        <?= htmlspecialchars((string)$_SESSION['order_cancel_message']) ?>
                    </div>
                    <?php unset($_SESSION['order_cancel_message']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['order_complete_message'])): ?>
                    <div class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-800 shadow-sm">
                        <?= htmlspecialchars((string)$_SESSION['order_complete_message']) ?>
                    </div>
                    <?php unset($_SESSION['order_complete_message']); ?>
                <?php endif; ?>

                <section class="overflow-hidden rounded-[28px] border border-[#cfe3f1] bg-white shadow-[0_20px_60px_rgba(6,43,65,0.08)]">
                    <div class="flex items-center justify-between border-b border-[#dbe8f2] px-6 py-5">
                        <div>
                            <h2 class="text-xl font-bold text-[#062B41]">Current blocked dates</h2>
                            <p class="mt-1 text-sm text-gray-500">These dates are removed from the online booking calendar.</p>
                        </div>
                    </div>

                    <?php if (!empty($blockedDates)): ?>
                        <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                            <?php foreach ($blockedDates as $blocked): ?>
                                <article class="rounded-[24px] border border-[#d8e9f4] bg-[linear-gradient(180deg,_#ffffff_0%,_#f7fbfe_100%)] p-5 shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Blocked day</p>
                                            <h3 class="mt-2 text-xl font-bold text-[#062B41]"><?= htmlspecialchars($formatBlockedDate($blocked['blocked_date'] ?? '')) ?></h3>
                                        </div>
                                        <span class="rounded-full bg-[#e7f5fc] px-3 py-1 text-xs font-semibold text-[#0b5f8a]">Unavailable</span>
                                    </div>

                                    <div class="mt-4 rounded-2xl border border-[#e2edf5] bg-white px-4 py-3">
                                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Reason</div>
                                        <div class="mt-2 text-sm font-medium text-gray-700"><?= htmlspecialchars((string)($blocked['reason'] ?? '')) ?: 'No reason provided' ?></div>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between gap-3">
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Created</div>
                                            <div class="mt-1 text-sm text-gray-600"><?= htmlspecialchars($formatCreatedAt($blocked['created_at'] ?? '')) ?></div>
                                        </div>
                                        <?php if ($canEditBlockedDates): ?>
                                            <form method="POST" action="/admin/orders/blocked-dates/delete" onsubmit="return confirm('Remove this blocked date?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? '')) ?>">
                                                <input type="hidden" name="blocked_date_id" value="<?= (int)($blocked['id'] ?? 0) ?>">
                                                <button type="submit" class="cursor-pointer rounded-xl bg-[#062B41] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0a4568]">Remove</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-400">Read only</span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-6">
                            <div class="rounded-[24px] border border-dashed border-[#cfe3f1] bg-[#f8fbfe] px-6 py-10 text-center">
                                <p class="text-lg font-semibold text-[#062B41]">No blocked dates configured yet.</p>
                                <p class="mt-2 text-sm text-gray-500">Add holidays, closures, or special blackout dates to keep them out of the booking calendar.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" integrity="sha384-2f8Q8CVR3RF4S+N4M2QvUWIJw4fQv4EeG+9A4M8NV6M6fV8Vs7Y0xGJf4Qx6C9vA" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" integrity="sha384-5JqMv4L/Xa0hfvtF06qboNdhvuYXUku9ZrhZh3bSk8VXF0A/RuSLHpLsSV9Zqhl6" crossorigin="anonymous"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('blockedDateInput');
        if (!input || typeof flatpickr !== 'function') return;

        const blockedDates = new Set(<?= json_encode($blockedDateKeys, JSON_UNESCAPED_SLASHES) ?>);

        flatpickr(input, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'F j, Y',
            disableMobile: true,
            defaultDate: 'today',
            onDayCreate: function(_dObj, _dStr, _fp, dayElem) {
                const dayDate = dayElem.dateObj;
                if (!dayDate) return;
                const yyyy = dayDate.getFullYear();
                const mm = String(dayDate.getMonth() + 1).padStart(2, '0');
                const dd = String(dayDate.getDate()).padStart(2, '0');
                const key = `${yyyy}-${mm}-${dd}`;
                if (blockedDates.has(key)) {
                    dayElem.classList.add('admin-blocked-date');
                    dayElem.setAttribute('title', 'Already blocked date');
                }
            }
        });
    });
    </script>
    <style>
    .flatpickr-day.admin-blocked-date {
        background: #fff3cd;
        border-color: #facc15;
        color: #92400e;
        font-weight: 700;
    }
    .flatpickr-day.admin-blocked-date:hover {
        background: #fde68a;
        border-color: #f59e0b;
        color: #78350f;
    }
    </style>
</body>
