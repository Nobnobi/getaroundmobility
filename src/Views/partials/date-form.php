<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\date-form.php -->
<?php $usePickerModal = true; ?>

<!-- PICKUP & RETURN DATE FORM -->
<div class="flex p-6 justify-center">
    <div id="productListCard" class="flex justify-center">
        <div id="productListForm" action="" method="GET" class="rounded-lg w-full max-w-[1000px] bg-white shadow-md">
            <div class="flex flex-col md:flex-row border border-[#D9D9D9] rounded-lg p-2 gap-4 w-full">
                <!-- Pickup and Return inputs -->
                <div class="p-1 flex-1 md:border-r md:pr-2">
                    <label for="pickup_datetime" class="block text-sm font-medium text-gray-700 mb-1 font-[Barlow]">Pickup date & time</label>
                    <input
                        id="pickupDatetime"
                        name="pickup_datetime"
                        type="text"
                        readonly
                        class="w-full rounded-lg p-2 border border-[#D9D9D9] bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-[Barlow] transition-all duration-300"
                        placeholder="Select Date & Time"
                        autocomplete="off"
                        required
                    />
                </div>
                <div class="p-1 flex-1 md:pl-2">
                    <label for="return_datetime" class="block text-sm font-medium text-gray-700 mb-1 font-[Barlow]">Return date & time</label>
                    <input
                        id="returnDatetime"
                        name="return_datetime"
                        type="text"
                        readonly
                        class="w-full rounded-lg p-2 border border-[#D9D9D9] bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-[Barlow] transition-all duration-300"
                        placeholder="Select Date & Time"
                        autocomplete="off"
                        required
                    />
                </div>
            </div>
            <div id="formMessage" class="hidden mt-5 mb-4 w-full flex justify-center">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded relative animate-bounce font-semibold text-center max-w-md w-full">
                    Please select both Pickup/Delivery and Return date & time first.
                </div>
            </div>
            <!-- Static 31-day limit notice -->
            <div class="mt-3 mb-1 flex justify-center">
                <div class="flex items-center gap-2 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-2 rounded-lg text-sm font-[Barlow] max-w-md w-full">
                    <svg class="w-4 h-4 flex-shrink-0 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span>Online bookings are limited to <strong>31 days</strong>. For longer rentals and same day booking, please <strong>call us</strong>.</span>
                </div>
            </div>
            <!-- Dynamic "call us" banner shown when return date reaches the 31-day max -->
            <div id="longRentalCallBanner" class="hidden mt-2 mb-1 flex justify-center">
                <div class="flex items-center gap-2 bg-amber-50 border border-amber-400 text-amber-800 px-4 py-2 rounded-lg text-sm font-[Barlow] max-w-md w-full">
                    <svg class="w-4 h-4 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    <span>Need more than 31 days? Please <strong>call us</strong> to arrange a longer rental.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!defined('DATE_FORM_ASSETS_RENDERED')): define('DATE_FORM_ASSETS_RENDERED', true); ?>
<!-- Cart Date Change Modal: Only one instance, outside all panels -->
<div id="cartDateChangeModal" class="fixed inset-0 z-[1050] items-center justify-center bg-black/50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 relative">
        <h2 class="text-xl font-bold mb-4 text-center">Change Date?</h2>
        <p class="mb-6 text-gray-700 text-center">Changing your rental dates may affect stock availability for items already in your cart. Clear the cart and check available items for the new dates?</p>
        <div class="flex justify-center gap-4">
            <button id="cartDateChangeConfirm" class="bg-red-600 text-white px-4 py-2 rounded font-semibold cursor-pointer">Update Dates</button>
            <button id="cartDateChangeCancel" class="bg-gray-300 text-gray-800 px-4 py-2 rounded font-semibold cursor-pointer">Keep Current Dates</button>
        </div>
    </div>
</div>

<?php if ($usePickerModal): ?>
<div id="dateTimeSelectionModal" class="fixed inset-0 z-[1060] hidden items-center justify-center bg-black/55 px-3 py-4 sm:px-4">
    <div class="w-full max-w-lg max-h-[92vh] overflow-y-auto rounded-[28px] bg-white p-5 shadow-2xl sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Schedule Rental</p>
                <h2 id="dateTimeModalTitle" class="mt-1 text-3xl font-bold text-[#062B41]">Select pickup date &amp; time</h2>
                <p class="mt-2 text-sm text-gray-500">Available hours are 8:30am to 5:30pm only.</p>
            </div>
            <button type="button" id="closeDateTimeSelectionModal" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Close date picker modal">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="mt-6 space-y-4">
            <div>
                <label class="mb-2 block text-sm font-medium text-[#062B41]">Date</label>
                <input type="text" id="dateTimeModalDate" class="hidden" readonly>
                <div id="dateTimeModalCalendar" class="date-time-modal-calendar rounded-2xl border border-[#c8d9e6] bg-[#f9fcff] p-2 shadow-sm"></div>
            </div>
            <div>
                <label for="dateTimeModalTime" class="mb-2 block text-sm font-medium text-[#062B41]">Time slot</label>
                <div class="relative">
                    <select id="dateTimeModalTime" class="w-full appearance-none rounded-2xl border border-[#c8d9e6] bg-[linear-gradient(180deg,#ffffff_0%,#f3f9fe_100%)] px-4 py-3 pr-11 text-base font-semibold text-[#062B41] shadow-sm focus:outline-none focus:ring-2 focus:ring-[#0086C9]"></select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[#0b5f8a]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </span>
                </div>
            </div>
            <div id="dateTimeModalInfo" class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"></div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" id="cancelDateTimeSelectionModal" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100">Cancel</button>
            <button type="button" id="saveDateTimeSelectionModal" class="rounded-xl bg-[#0086C9] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#066fa6]">Set date &amp; time</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function showDateValidationAlertOnce(message) {
        const now = Date.now();
        if (!window.__dateValidationAlertState) {
            window.__dateValidationAlertState = { message: '', at: 0 };
        }

        const last = window.__dateValidationAlertState;
        if (last.message === message && (now - last.at) < 1200) {
            return;
        }

        window.__dateValidationAlertState = { message: message, at: now };
        alert(message);
    }

    function emphasizeRentalForm() {
        // Prevent emphasize if modal is open
        const cartDateChangeModal = document.getElementById('cartDateChangeModal');
        if (cartDateChangeModal && !cartDateChangeModal.classList.contains('hidden')) return;
        const rentalForm = document.getElementById('productListForm');
        if (rentalForm) {
            rentalForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            rentalForm.classList.add('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');
            setTimeout(() => {
                rentalForm.classList.remove('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');
            }, 3000);
        }
    }
    window.emphasizeRentalForm = emphasizeRentalForm;

    document.addEventListener('DOMContentLoaded', function() {
        const pickupInput = document.getElementById('pickupDatetime');
        const returnInput = document.getElementById('returnDatetime');
        if (!pickupInput || !returnInput) return;
        const usePickerModal = <?= $usePickerModal ? 'true' : 'false' ?>;



        const BUSINESS_OPEN_MINUTES = (8 * 60) + 30;
        const BUSINESS_CLOSE_MINUTES = (17 * 60) + 30;
        const MIN_ADVANCE_HOURS = 24;

        function ceilToNext15Minutes(date) {
            const rounded = new Date(date);
            rounded.setSeconds(0, 0);
            const minutes = rounded.getMinutes();
            const remainder = minutes % 15;
            if (remainder !== 0) {
                rounded.setMinutes(minutes + (15 - remainder));
            }
            return rounded;
        }

        function normalizeToBusinessHours(date) {
            const normalized = ceilToNext15Minutes(date);
            while (true) {
                const mins = (normalized.getHours() * 60) + normalized.getMinutes();
                if (mins < BUSINESS_OPEN_MINUTES) {
                    normalized.setHours(8, 30, 0, 0);
                    break;
                }
                if (mins > BUSINESS_CLOSE_MINUTES) {
                    normalized.setDate(normalized.getDate() + 1);
                    normalized.setHours(8, 30, 0, 0);
                    continue;
                }
                break;
            }
            return normalized;
        }

        function getMinimumPickupDate() {
            const now = new Date();
            now.setMinutes(now.getMinutes() + (MIN_ADVANCE_HOURS * 60));
            return normalizeToBusinessHours(now);
        }

        function startOfDay(date) {
            const normalized = new Date(date);
            normalized.setHours(0, 0, 0, 0);
            return normalized;
        }

        function isBlockedCalendarDate(date) {
            const dayKey = flatpickr.formatDate(date, 'Y-m-d');
            return blockedDateSet.has(dayKey);
        }

        function isBeforeMinimumPickupDay(date) {
            return startOfDay(date).getTime() < startOfDay(getMinimumPickupDate()).getTime();
        }

        function isWithinBusinessHours(date) {
            if (!date) return false;
            const mins = (date.getHours() * 60) + date.getMinutes();
            return mins >= BUSINESS_OPEN_MINUTES && mins <= BUSINESS_CLOSE_MINUTES;
        }

        let blockedDateSet = new Set();
        const pickupDisableRules = [function(date) {
            return isBeforeMinimumPickupDay(date) || isBlockedCalendarDate(date);
        }];
        const returnDisableRules = [function(date) {
            return isBeforeMinimumPickupDay(date) || isBlockedCalendarDate(date);
        }];

        async function loadBlockedDates() {
            try {
                const response = await fetch('/api/blocked-dates', { cache: 'no-store' });
                const payload = await response.json();
                const values = Array.isArray(payload?.blocked_dates) ? payload.blocked_dates : [];
                blockedDateSet = new Set(values.map(v => String(v || '').trim()).filter(Boolean));
            } catch (err) {
                blockedDateSet = new Set();
            }
        }

        const MAX_RENTAL_DAYS = 31;
        const MIN_RETURN_GAP_MINUTES = 30;

        function getMaxReturnDate(pickupDate) {
            const maxReturnDate = new Date(pickupDate);
            maxReturnDate.setDate(maxReturnDate.getDate() + MAX_RENTAL_DAYS);
            return maxReturnDate;
        }

        function getMinReturnDate(pickupDate) {
            const minReturnDate = new Date(pickupDate);
            minReturnDate.setMinutes(minReturnDate.getMinutes() + MIN_RETURN_GAP_MINUTES);
            return minReturnDate;
        }

        function getSafeReturnFallbackDate(pickupDate) {
            const basePickup = pickupDate instanceof Date && !Number.isNaN(pickupDate.getTime())
                ? pickupDate
                : getMinimumPickupDate();

            let candidate = normalizeToBusinessHours(getMinReturnDate(basePickup));
            let guard = 0;
            while (isBlockedCalendarDate(candidate) && guard < 31) {
                candidate = normalizeToBusinessHours(new Date(candidate.getTime() + (24 * 60 * 60 * 1000)));
                guard += 1;
            }

            return candidate;
        }

        function getNextAvailablePickupDate(sourceDate) {
            const minimumPickupDate = getMinimumPickupDate();
            const baseDate = sourceDate instanceof Date && !Number.isNaN(sourceDate.getTime())
                ? sourceDate
                : minimumPickupDate;

            let candidate = new Date(Math.max(baseDate.getTime(), minimumPickupDate.getTime()));
            candidate = normalizeToBusinessHours(candidate);

            let guard = 0;
            while ((candidate < minimumPickupDate || isBlockedCalendarDate(candidate)) && guard < 60) {
                candidate = normalizeToBusinessHours(new Date(candidate.getTime() + (24 * 60 * 60 * 1000)));
                guard += 1;
            }

            return candidate;
        }

        function padDatePart(value) {
            return String(value).padStart(2, '0');
        }

        function formatStorageDateTime(date) {
            return [
                date.getFullYear(),
                padDatePart(date.getMonth() + 1),
                padDatePart(date.getDate())
            ].join('-') + ' ' + [
                padDatePart(date.getHours()),
                padDatePart(date.getMinutes())
            ].join(':');
        }

        function formatNativeDateTime(date) {
            return [
                date.getFullYear(),
                padDatePart(date.getMonth() + 1),
                padDatePart(date.getDate())
            ].join('-') + 'T' + [
                padDatePart(date.getHours()),
                padDatePart(date.getMinutes())
            ].join(':');
        }

        if (typeof window.showCartDateChangeModal !== 'function') {
            window.showCartDateChangeModal = function(onConfirm, onCancel) {
                const modal = document.getElementById('cartDateChangeModal');
                if (!modal) return;
                const pickerModal = document.getElementById('dateTimeSelectionModal');
                if (pickerModal) {
                    pickerModal.classList.add('hidden');
                    pickerModal.classList.remove('flex');
                }
                modal.classList.remove('hidden');
                modal.classList.add('flex');

                const confirmBtn = document.getElementById('cartDateChangeConfirm');
                const cancelBtn = document.getElementById('cartDateChangeCancel');

                if (confirmBtn) {
                    confirmBtn.onclick = function() {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                        if (onConfirm) onConfirm();
                    };
                }

                if (cancelBtn) {
                    cancelBtn.onclick = function() {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                        if (onCancel) onCancel();
                    };
                }
            };
        }

        if (usePickerModal) {
            const pickerModal = document.getElementById('dateTimeSelectionModal');
            const pickerModalTitle = document.getElementById('dateTimeModalTitle');
            const pickerModalDate = document.getElementById('dateTimeModalDate');
            const pickerModalCalendar = document.getElementById('dateTimeModalCalendar');
            const pickerModalTime = document.getElementById('dateTimeModalTime');
            const pickerModalInfo = document.getElementById('dateTimeModalInfo');
            const pickerModalClose = document.getElementById('closeDateTimeSelectionModal');
            const pickerModalCancel = document.getElementById('cancelDateTimeSelectionModal');
            const pickerModalSave = document.getElementById('saveDateTimeSelectionModal');
            let modalCalendarPicker = null;

            let activeDateField = 'pickup';
            let modalPreviousSelection = null;

            function formatCalendarDay(date) {
                return [
                    date.getFullYear(),
                    padDatePart(date.getMonth() + 1),
                    padDatePart(date.getDate())
                ].join('-');
            }

            function formatTimeValue(date) {
                return [padDatePart(date.getHours()), padDatePart(date.getMinutes())].join(':');
            }

            function formatDisplayDateTime(date) {
                const month = date.toLocaleString(undefined, { month: 'long' });
                const day = date.getDate();
                const year = date.getFullYear();
                let hours = date.getHours();
                const minutes = padDatePart(date.getMinutes());
                const meridiem = hours >= 12 ? 'pm' : 'am';
                hours = hours % 12;
                if (hours === 0) hours = 12;
                return `${month} ${day}, ${year} ${hours}:${minutes} ${meridiem}`;
            }

            function formatDisplayTimeOnly(date) {
                let hours = date.getHours();
                const minutes = padDatePart(date.getMinutes());
                const meridiem = hours >= 12 ? 'pm' : 'am';
                hours = hours % 12;
                if (hours === 0) hours = 12;
                return `${hours}:${minutes} ${meridiem}`;
            }

            function parseStoredDate(value) {
                return parseDateValue(value);
            }

            function setModalFieldValue(input, date) {
                if (!input) return;
                input.value = date ? formatStorageDateTime(date) : '';
                input.dataset.displayValue = date ? formatDisplayDateTime(date) : '';
            }

            function getFieldDate(fieldName) {
                return fieldName === 'pickup' ? parseStoredDate(pickupInput.value) : parseStoredDate(returnInput.value);
            }

            function notifyModalDateSelectionChanged() {
                if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                window.dispatchEvent(new CustomEvent('rental-dates-updated', {
                    detail: {
                        pickup: pickupInput.value || '',
                        return: returnInput.value || ''
                    }
                }));
            }

            function persistCurrentModalSelection() {
                if (pickupInput.value) {
                    localStorage.setItem('pickupDatetime', pickupInput.value);
                } else {
                    localStorage.removeItem('pickupDatetime');
                }

                if (returnInput.value) {
                    localStorage.setItem('returnDatetime', returnInput.value);
                } else {
                    localStorage.removeItem('returnDatetime');
                }

                notifyModalDateSelectionChanged();
            }

            function restoreModalSelection(snapshot) {
                setModalFieldValue(pickupInput, parseStoredDate(snapshot.pickup || ''));
                setModalFieldValue(returnInput, parseStoredDate(snapshot.return || ''));
                persistCurrentModalSelection();
            }

            function closePickerModal() {
                if (!pickerModal) return;
                pickerModal.classList.add('hidden');
                pickerModal.classList.remove('flex');
            }

            function openPickerModal(fieldName) {
                if (!pickerModal || !pickerModalDate || !pickerModalTime) return;
                activeDateField = fieldName;
                modalPreviousSelection = {
                    pickup: pickupInput.value || '',
                    return: returnInput.value || ''
                };

                const currentDate = getFieldDate(fieldName);
                const fallbackDate = fieldName === 'pickup'
                    ? getNextAvailablePickupDate(currentDate || getMinimumPickupDate())
                    : getSafeReturnFallbackDate(getFieldDate('pickup') || getMinimumPickupDate());

                pickerModalTitle.textContent = fieldName === 'pickup' ? 'Select pickup date & time' : 'Select return date & time';
                refreshModalCalendar(currentDate || fallbackDate);
                populateModalTimeOptions();
                if (!pickerModalTime.value) {
                    const refreshedDate = getFieldDate(fieldName) || fallbackDate;
                    refreshModalCalendar(refreshedDate);
                    populateModalTimeOptions();
                }

                pickerModal.classList.remove('hidden');
                pickerModal.classList.add('flex');
            }

            function getModalMinimumDate() {
                if (activeDateField === 'pickup') {
                    return getMinimumPickupDate();
                }

                const pickupDate = getFieldDate('pickup') || getNextAvailablePickupDate(getMinimumPickupDate());
                return getMinReturnDate(pickupDate);
            }

            function getModalMaximumDate() {
                if (activeDateField !== 'return') {
                    return null;
                }

                const pickupDate = getFieldDate('pickup') || getNextAvailablePickupDate(getMinimumPickupDate());
                return getMaxReturnDate(pickupDate);
            }

            function getNextAvailableDateForModal(date) {
                const minimumDate = getModalMinimumDate();
                const maximumDate = getModalMaximumDate();
                let candidate = date instanceof Date && !Number.isNaN(date.getTime())
                    ? new Date(date)
                    : new Date(minimumDate);

                candidate.setHours(minimumDate.getHours(), minimumDate.getMinutes(), 0, 0);
                if (candidate < minimumDate) {
                    candidate = new Date(minimumDate);
                }

                let guard = 0;
                while ((isBlockedCalendarDate(candidate) || (maximumDate && startOfDay(candidate) > startOfDay(maximumDate))) && guard < 60) {
                    candidate = normalizeToBusinessHours(new Date(candidate.getTime() + (24 * 60 * 60 * 1000)));
                    guard += 1;
                }

                return maximumDate && candidate > maximumDate ? new Date(maximumDate) : candidate;
            }

            function ensureModalCalendar() {
                if (modalCalendarPicker || !pickerModalCalendar || typeof flatpickr !== 'function') {
                    return;
                }

                modalCalendarPicker = flatpickr(pickerModalDate, {
                    inline: true,
                    dateFormat: 'Y-m-d',
                    defaultDate: getMinimumPickupDate(),
                    clickOpens: false,
                    disableMobile: true,
                    appendTo: pickerModalCalendar,
                    onChange: function() {
                        populateModalTimeOptions();
                    }
                });
            }

            function refreshModalCalendar(referenceDate) {
                ensureModalCalendar();
                if (!modalCalendarPicker) return;

                const minimumDate = getModalMinimumDate();
                const maximumDate = getModalMaximumDate();
                const disableRules = [function(date) {
                    if (isBlockedCalendarDate(date)) return true;
                    if (startOfDay(date) < startOfDay(minimumDate)) return true;
                    if (maximumDate && startOfDay(date) > startOfDay(maximumDate)) return true;
                    return false;
                }];

                modalCalendarPicker.set('minDate', minimumDate);
                modalCalendarPicker.set('maxDate', maximumDate || null);
                modalCalendarPicker.set('disable', disableRules);

                const target = referenceDate instanceof Date ? referenceDate : getNextAvailableDateForModal(minimumDate);
                modalCalendarPicker.setDate(target, false);
                pickerModalDate.value = formatCalendarDay(target);
            }

            function populateModalTimeOptions(preferredTime) {
                if (!pickerModalDate || !pickerModalTime) return;

                const selectedDate = parseDateValue(`${pickerModalDate.value} 00:00`);
                const adjustedDate = getNextAvailableDateForModal(selectedDate);
                pickerModalDate.value = formatCalendarDay(adjustedDate);
                refreshModalCalendar(adjustedDate);
                pickerModalTime.innerHTML = '';

                const minimumDate = getModalMinimumDate();
                const maximumDate = getModalMaximumDate();
                let firstOptionValue = '';

                for (let minutes = BUSINESS_OPEN_MINUTES; minutes <= BUSINESS_CLOSE_MINUTES; minutes += 15) {
                    const hour = Math.floor(minutes / 60);
                    const minute = minutes % 60;
                    const candidate = new Date(adjustedDate);
                    candidate.setHours(hour, minute, 0, 0);

                    if (candidate < minimumDate) continue;
                    if (maximumDate && candidate > maximumDate) continue;
                    if (activeDateField === 'return') {
                        const pickupDate = getFieldDate('pickup');
                        if (pickupDate && candidate <= pickupDate) continue;
                    }

                    const option = document.createElement('option');
                    option.value = `${padDatePart(hour)}:${padDatePart(minute)}`;
                    option.textContent = formatDisplayTimeOnly(candidate);
                    pickerModalTime.appendChild(option);
                    if (!firstOptionValue) {
                        firstOptionValue = option.value;
                    }
                }

                pickerModalTime.value = preferredTime && pickerModalTime.querySelector(`option[value="${preferredTime}"]`)
                    ? preferredTime
                    : firstOptionValue;

                const infoDate = activeDateField === 'pickup' ? getMinimumPickupDate() : getModalMinimumDate();
                pickerModalInfo.textContent = activeDateField === 'pickup'
                    ? `Earliest available pickup is ${formatDisplayDateTime(infoDate)}.`
                    : `Return time must be after ${formatDisplayDateTime(infoDate)}.`;
            }

            function buildModalSelection() {
                if (!pickerModalDate || !pickerModalTime || !pickerModalDate.value || !pickerModalTime.value) {
                    return null;
                }

                const selected = parseDateValue(`${pickerModalDate.value} ${pickerModalTime.value}`);
                return selected ? normalizeToBusinessHours(selected) : null;
            }

            function ensureValidModalStoredDates() {
                const savedPickup = localStorage.getItem('pickupDatetime') || pickupInput.value;
                const savedReturn = localStorage.getItem('returnDatetime') || returnInput.value;

                const validPickupDate = getNextAvailablePickupDate(parseStoredDate(savedPickup) || getMinimumPickupDate());
                setModalFieldValue(pickupInput, validPickupDate);

                const parsedReturn = parseStoredDate(savedReturn);
                const returnInvalid = !parsedReturn
                    || parsedReturn <= validPickupDate
                    || parsedReturn < getMinReturnDate(validPickupDate)
                    || parsedReturn > getMaxReturnDate(validPickupDate)
                    || !isWithinBusinessHours(parsedReturn)
                    || isBlockedCalendarDate(parsedReturn);

                if (returnInvalid) {
                    setModalFieldValue(returnInput, getSafeReturnFallbackDate(validPickupDate));
                } else {
                    setModalFieldValue(returnInput, parsedReturn);
                }

                persistCurrentModalSelection();
            }

            function applyModalSelection(selectedDate) {
                if (!selectedDate) return;

                if (activeDateField === 'pickup') {
                    const pickupDate = getNextAvailablePickupDate(selectedDate);
                    setModalFieldValue(pickupInput, pickupDate);

                    const currentReturn = getFieldDate('return');
                    if (!currentReturn || currentReturn < getMinReturnDate(pickupDate) || currentReturn > getMaxReturnDate(pickupDate) || isBlockedCalendarDate(currentReturn)) {
                        setModalFieldValue(returnInput, getSafeReturnFallbackDate(pickupDate));
                    }
                } else {
                    const pickupDate = getFieldDate('pickup') || getNextAvailablePickupDate(getMinimumPickupDate());
                    const returnDate = selectedDate < getMinReturnDate(pickupDate)
                        ? getSafeReturnFallbackDate(pickupDate)
                        : selectedDate;
                    setModalFieldValue(returnInput, returnDate);
                }

                persistCurrentModalSelection();
            }

            function loadCart() {
                return JSON.parse(localStorage.getItem('cart') || '[]');
            }

            function saveCart(cart) {
                localStorage.setItem('cart', JSON.stringify(cart));
            }

            if (pickerModalClose) pickerModalClose.addEventListener('click', closePickerModal);
            if (pickerModalCancel) pickerModalCancel.addEventListener('click', function() {
                if (modalPreviousSelection) {
                    restoreModalSelection(modalPreviousSelection);
                }
                closePickerModal();
            });
            if (pickerModal) {
                pickerModal.addEventListener('click', function(event) {
                    if (event.target === pickerModal) {
                        if (modalPreviousSelection) {
                            restoreModalSelection(modalPreviousSelection);
                        }
                        closePickerModal();
                    }
                });
            }

            ensureModalCalendar();

            if (pickerModalSave) {
                pickerModalSave.addEventListener('click', function() {
                    const selectedDate = buildModalSelection();
                    if (!selectedDate) {
                        showDateValidationAlertOnce('Please select a valid date and time.');
                        return;
                    }

                    const currentSelection = activeDateField === 'pickup' ? pickupInput.value : returnInput.value;
                    const newSelectionValue = formatStorageDateTime(selectedDate);
                    const previousSelection = {
                        pickup: localStorage.getItem('pickupDatetime') || '',
                        return: localStorage.getItem('returnDatetime') || ''
                    };

                    if (loadCart().length > 0 && currentSelection !== newSelectionValue) {
                        window.showCartDateChangeModal(
                            function onConfirm() {
                                saveCart([]);
                                applyModalSelection(selectedDate);
                                closePickerModal();
                            },
                            function onCancel() {
                                restoreModalSelection(previousSelection);
                                closePickerModal();
                            }
                        );
                        return;
                    }

                    applyModalSelection(selectedDate);
                    closePickerModal();
                });
            }

            pickupInput.addEventListener('click', function() {
                openPickerModal('pickup');
            });
            returnInput.addEventListener('click', function() {
                openPickerModal('return');
            });

            loadBlockedDates().finally(function() {
                ensureValidModalStoredDates();
                refreshModalCalendar(getFieldDate('pickup') || getMinimumPickupDate());

                const searchForm = document.querySelector('form[action="/search"]');
                if (searchForm) {
                    searchForm.addEventListener('submit', function(e) {
                        const rentalCheck = validateRentalWindow(pickupInput.value, returnInput.value);
                        if (!rentalCheck.valid) {
                            e.preventDefault();
                            showRentalValidationAlert(rentalCheck.message);
                            ensureValidModalStoredDates();
                        }
                    });
                }
            });

            return;
        }

        function syncReturnLimit(pickupDate) {
            const minReturnDate = getMinReturnDate(pickupDate);
            returnPicker.set('minDate', minReturnDate);
            returnPicker.set('maxDate', getMaxReturnDate(pickupDate));
        }

        function refreshPickerConstraints() {
            const pickupInstance = pickupInput._flatpickr;
            const returnInstance = returnInput._flatpickr;
            if (!pickupInstance || !returnInstance) {
                return;
            }

            const minimumPickupDate = getMinimumPickupDate();
            pickupInstance.set('minDate', minimumPickupDate);
            pickupInstance.set('disable', pickupDisableRules);
            returnInstance.set('disable', returnDisableRules);

            const currentPickup = parseDateValue(pickupInput.value);
            if (currentPickup) {
                syncReturnLimit(currentPickup);
            } else {
                returnInstance.set('minDate', minimumPickupDate);
                returnInstance.set('maxDate', null);
            }
        }

        function ensureValidStoredDates() {
            const minimumPickupDate = getMinimumPickupDate();
            const currentPickup = parseDateValue(pickupInput.value);

            const pickupInvalid = !currentPickup
                || currentPickup < minimumPickupDate
                || !isWithinBusinessHours(currentPickup)
                || isBlockedCalendarDate(currentPickup);

            if (pickupInvalid) {
                const fallbackPickup = flatpickr.formatDate(minimumPickupDate, 'Y-m-d H:i');
                pickupInput.value = fallbackPickup;
                pickupPicker.setDate(minimumPickupDate, false);
                localStorage.setItem('pickupDatetime', fallbackPickup);
            }

            const normalizedPickup = parseDateValue(pickupInput.value) || minimumPickupDate;
            syncReturnLimit(normalizedPickup);

            const normalizedReturn = parseDateValue(returnInput.value);
            const returnInvalid = !normalizedReturn
                || normalizedReturn <= normalizedPickup
                || normalizedReturn < getMinReturnDate(normalizedPickup)
                || normalizedReturn > getMaxReturnDate(normalizedPickup)
                || !isWithinBusinessHours(normalizedReturn)
                || isBlockedCalendarDate(normalizedReturn);

            if (returnInvalid) {
                const fallbackReturnDate = getSafeReturnFallbackDate(normalizedPickup);
                const fallbackReturn = flatpickr.formatDate(fallbackReturnDate, 'Y-m-d H:i');
                returnInput.value = fallbackReturn;
                returnPicker.setDate(fallbackReturnDate, false);
                localStorage.setItem('returnDatetime', fallbackReturn);
            }

            persistCurrentDateSelection();
        }

        function parseDateValue(raw) {
            if (!raw) return null;
            const normalized = String(raw).replace(' ', 'T');
            const date = new Date(normalized);
            return isNaN(date) ? null : date;
        }

        function clearDateSelection(options = {}) {
            const keepPickup = !!options.keepPickup;
            const keepReturn = !!options.keepReturn;

            if (!keepPickup) {
                pickupInput.value = '';
                pickupPicker.clear();
                localStorage.removeItem('pickupDatetime');
            }

            if (!keepReturn) {
                returnInput.value = '';
                returnPicker.clear();
                localStorage.removeItem('returnDatetime');
            }

            const longRentalBanner = document.getElementById('longRentalCallBanner');
            if (longRentalBanner) {
                longRentalBanner.classList.add('hidden');
                longRentalBanner.classList.remove('flex');
            }

            if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
            if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
            window.dispatchEvent(new CustomEvent('rental-dates-updated', {
                detail: {
                    pickup: pickupInput.value || '',
                    return: returnInput.value || ''
                }
            }));
        }

        function setInputAndPickerValue(input, picker, value) {
            if (!input) return;
            const normalizedValue = value instanceof Date
                ? flatpickr.formatDate(value, 'Y-m-d H:i')
                : (value || '');
            input.value = normalizedValue;
            if (!picker) return;
            if (normalizedValue) {
                const parsedValue = value instanceof Date ? value : parseDateValue(normalizedValue);
                const resolvedDate = parsedValue || parseDateValue(normalizedValue);
                picker.setDate(resolvedDate || normalizedValue, false);
                if (picker.altInput && resolvedDate) {
                    picker.altInput.value = flatpickr.formatDate(resolvedDate, picker.config.altFormat);
                    normalizeClassicMeridiem(picker);
                }
            } else {
                picker.clear();
                if (picker.altInput) {
                    picker.altInput.value = '';
                }
            }
        }

        function syncMirroredDateInputs() {
            const pickupValue = pickupInput.value || localStorage.getItem('pickupDatetime') || '';
            const returnValue = returnInput.value || localStorage.getItem('returnDatetime') || '';
            const pickupMirrors = document.querySelectorAll('#pickupDatetime, #mobileFilterPanel #pickupDatetime');
            const returnMirrors = document.querySelectorAll('#returnDatetime, #mobileFilterPanel #returnDatetime');

            pickupMirrors.forEach(function(input) {
                if (!input || input === pickupInput) return;
                input.value = pickupValue;
                if (input._flatpickr) input._flatpickr.setDate(pickupValue, false);
            });

            returnMirrors.forEach(function(input) {
                if (!input || input === returnInput) return;
                input.value = returnValue;
                if (input._flatpickr) input._flatpickr.setDate(returnValue, false);
            });
        }

        function notifyDateSelectionChanged() {
            syncMirroredDateInputs();
            if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
            if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
            window.dispatchEvent(new CustomEvent('rental-dates-updated', {
                detail: {
                    pickup: pickupInput.value || '',
                    return: returnInput.value || ''
                }
            }));
        }

        function persistCurrentDateSelection() {
            if (pickupInput.value) {
                localStorage.setItem('pickupDatetime', pickupInput.value);
            } else {
                localStorage.removeItem('pickupDatetime');
            }

            if (returnInput.value) {
                localStorage.setItem('returnDatetime', returnInput.value);
            } else {
                localStorage.removeItem('returnDatetime');
            }

            notifyDateSelectionChanged();
        }

        function restoreDateSelection(snapshot) {
            setInputAndPickerValue(pickupInput, pickupPicker, snapshot.pickup || '');
            setInputAndPickerValue(returnInput, returnPicker, snapshot.return || '');
            if (pickupInput.value) {
                syncReturnLimit(parseDateValue(pickupInput.value));
            }
            persistCurrentDateSelection();
        }

        function clearReturnSelection(message) {
            setInputAndPickerValue(returnInput, returnPicker, '');
            localStorage.removeItem('returnDatetime');
            notifyDateSelectionChanged();
            if (message) {
                showRentalValidationAlert(message);
            }
        }

        function submitDateFormIfReady(form) {
            if (!form) return;
            const rentalCheck = validateRentalWindow(pickupInput.value, returnInput.value);
            if (!rentalCheck.valid) return;
            form.submit();
        }

        function showRentalValidationAlert(message) {
            showDateValidationAlertOnce(message);
            if (typeof emphasizeRentalForm === 'function') emphasizeRentalForm();
        }

        function validateRentalWindow(pickupRaw, returnRaw) {
            const pickupDate = parseDateValue(pickupRaw);
            const returnDate = parseDateValue(returnRaw);
            const now = getMinimumPickupDate();

            if (!pickupDate || !returnDate) {
                return { valid: false, reason: 'missing', message: 'Please select both Pickup and Return date/time.' };
            }

            if (pickupDate < now || returnDate < now) {
                return { valid: false, reason: 'past', message: 'Bookings must be made at least 24 hours in advance.' };
            }

            if (!isWithinBusinessHours(pickupDate) || !isWithinBusinessHours(returnDate)) {
                return { valid: false, reason: 'business-hours', message: 'Pickups and returns are available from 8:30 am to 5:30 pm only.' };
            }

            const pickupDay = flatpickr.formatDate(pickupDate, 'Y-m-d');
            const returnDay = flatpickr.formatDate(returnDate, 'Y-m-d');
            if (blockedDateSet.has(pickupDay) || blockedDateSet.has(returnDay)) {
                return { valid: false, reason: 'blocked-date', message: 'Selected date is blocked for online bookings. Please choose another date.' };
            }

            if (returnDate <= pickupDate) {
                return { valid: false, reason: 'sequence', message: 'Return date/time must be after pickup date/time.' };
            }

            const minReturnDate = getMinReturnDate(pickupDate);
            if (returnDate < minReturnDate) {
                return {
                    valid: false,
                    reason: 'min-gap',
                    message: 'Return date/time must be at least 30 minutes after pickup date/time.'
                };
            }

            const diffDays = Math.ceil((returnDate - pickupDate) / (1000 * 60 * 60 * 24));
            if (diffDays > MAX_RENTAL_DAYS) {
                return {
                    valid: false,
                    reason: 'max-days',
                    message: 'Online booking is limited to 31 days. For rentals longer than 31 days, please call us.',
                    days: diffDays
                };
            }

            return { valid: true, days: diffDays };
        }

        function normalizeClassicMeridiem(instance) {
            if (!instance || !instance.altInput) return;
            instance.altInput.value = instance.altInput.value.replace(/\bAM\b/g, 'am').replace(/\bPM\b/g, 'pm');
        }

        const pickupPicker = flatpickr(pickupInput, {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "F j, Y h:i K",
            minDate: getMinimumPickupDate(),
            minTime: '08:30',
            maxTime: '17:30',
            time_24hr: false,
            minuteIncrement: 15,
            disable: pickupDisableRules,
            disableMobile: true,
            onReady: function(selectedDates, dateStr, instance) {
                refreshPickerConstraints();
                normalizeClassicMeridiem(instance);
            },
            onValueUpdate: function(selectedDates, dateStr, instance) {
                normalizeClassicMeridiem(instance);
            },
            onOpen: function() {
                refreshPickerConstraints();
            },
            onChange: function(selectedDates) {
                if (selectedDates[0]) {
                    syncReturnLimit(selectedDates[0]);

                    const currentReturn = parseDateValue(returnInput.value);
                    const maxReturnDate = getMaxReturnDate(selectedDates[0]);
                    const minReturnDate = getMinReturnDate(selectedDates[0]);

                    if (currentReturn && currentReturn < minReturnDate) {
                        setInputAndPickerValue(returnInput, returnPicker, flatpickr.formatDate(minReturnDate, 'Y-m-d H:i'));
                        persistCurrentDateSelection();
                    } else if (currentReturn && currentReturn > maxReturnDate) {
                        clearReturnSelection();
                    } else {
                        notifyDateSelectionChanged();
                    }
                }
            }
        });
                

        const returnPicker = flatpickr(returnInput, {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "F j, Y h:i K",
            minDate: getMinimumPickupDate(),
            minTime: '08:30',
            maxTime: '17:30',
            time_24hr: false,
            minuteIncrement: 15,
            disable: returnDisableRules,
            disableMobile: true,
            onReady: function(selectedDates, dateStr, instance) {
                refreshPickerConstraints();
                normalizeClassicMeridiem(instance);
            },
            onValueUpdate: function(selectedDates, dateStr, instance) {
                normalizeClassicMeridiem(instance);
            },
            onOpen: function() {
                refreshPickerConstraints();
            },
            onChange: function(selectedDates) {
                if (selectedDates[0]) {
                    // Show "call us" banner if return date is at the 31-day maximum
                    const longRentalBanner = document.getElementById('longRentalCallBanner');
                    if (longRentalBanner && pickupInput.value) {
                        const pickupDate = parseDateValue(pickupInput.value);
                        const diffDays = Math.ceil((selectedDates[0] - pickupDate) / (1000 * 60 * 60 * 24));
                        if (diffDays >= MAX_RENTAL_DAYS) {
                            longRentalBanner.classList.remove('hidden');
                            longRentalBanner.classList.add('flex');
                        } else {
                            longRentalBanner.classList.add('hidden');
                            longRentalBanner.classList.remove('flex');
                        }
                    }
                    // Keep return at least 30 minutes after pickup; avoid alert loops during selection.
                    if (pickupInput.value && parseDateValue(pickupInput.value) >= selectedDates[0]) {
                        const minReturnDate = getMinReturnDate(parseDateValue(pickupInput.value));
                        setInputAndPickerValue(returnInput, returnPicker, flatpickr.formatDate(minReturnDate, 'Y-m-d H:i'));
                        persistCurrentDateSelection();
                        return;
                    }

                    const rentalCheck = validateRentalWindow(pickupInput.value, flatpickr.formatDate(selectedDates[0], 'Y-m-d H:i'));
                    if (!rentalCheck.valid) {
                        if (rentalCheck.reason === 'min-gap' && pickupInput.value) {
                            const minReturnDate = getMinReturnDate(parseDateValue(pickupInput.value));
                            setInputAndPickerValue(returnInput, returnPicker, flatpickr.formatDate(minReturnDate, 'Y-m-d H:i'));
                            persistCurrentDateSelection();
                        } else {
                            clearReturnSelection();
                        }
                        return;
                    }

                    notifyDateSelectionChanged();
                }
            }
        });

        const savedPickup = localStorage.getItem('pickupDatetime');
        const savedReturn = localStorage.getItem('returnDatetime');



        // MAIN LOGIC: Always ensure pickup has a value
        if (!savedPickup || savedPickup === 'null' || savedPickup === '') {
            const nearest = getMinimumPickupDate();
            const formatted = flatpickr.formatDate(nearest, "Y-m-d H:i");
            setInputAndPickerValue(pickupInput, pickupPicker, nearest);
            localStorage.setItem('pickupDatetime', formatted);
        } else {
            const parsedSavedPickup = parseDateValue(savedPickup);
            const minPickup = getMinimumPickupDate();
            if (!parsedSavedPickup || parsedSavedPickup < minPickup || !isWithinBusinessHours(parsedSavedPickup)) {
                const fallbackPickup = flatpickr.formatDate(minPickup, "Y-m-d H:i");
                setInputAndPickerValue(pickupInput, pickupPicker, minPickup);
                localStorage.setItem('pickupDatetime', fallbackPickup);
            } else {
                const normalizedPickup = flatpickr.formatDate(parsedSavedPickup, 'Y-m-d H:i');
                setInputAndPickerValue(pickupInput, pickupPicker, parsedSavedPickup);
                localStorage.setItem('pickupDatetime', normalizedPickup);
            }
        }

        syncReturnLimit(parseDateValue(pickupInput.value));

        // Return: load saved, or set 1 day after pickup
        if (!savedReturn || savedReturn === 'null' || savedReturn === '') {
            const baseDate = new Date(savedPickup || pickupInput.value);
            const defaultReturn = normalizeToBusinessHours(new Date(baseDate.getTime() + (MIN_RETURN_GAP_MINUTES * 60000)));
            const formatted = flatpickr.formatDate(defaultReturn, "Y-m-d H:i");
            setInputAndPickerValue(returnInput, returnPicker, defaultReturn);
            localStorage.setItem('returnDatetime', formatted);
        } else {
            const parsedSavedReturn = parseDateValue(savedReturn);
            if (parsedSavedReturn) {
                const normalizedReturn = flatpickr.formatDate(parsedSavedReturn, 'Y-m-d H:i');
                setInputAndPickerValue(returnInput, returnPicker, parsedSavedReturn);
                localStorage.setItem('returnDatetime', normalizedReturn);
            } else {
                setInputAndPickerValue(returnInput, returnPicker, '');
                localStorage.removeItem('returnDatetime');
            }
        }

        // --- Date validation on load: clear return if pickup > return, or pickup if return < pickup ---
        const pickupVal = pickupInput.value;
        const returnVal = returnInput.value;
        if (pickupVal && returnVal) {
            const pickupDate = parseDateValue(pickupVal);
            const returnDate = parseDateValue(returnVal);
            if (pickupDate > returnDate) {
                clearReturnSelection();
            } else if (returnDate > getMaxReturnDate(pickupDate)) {
                clearReturnSelection();
            } else if (returnDate < pickupDate) {
                clearReturnSelection();
            }
        }

        loadBlockedDates().finally(function() {
            refreshPickerConstraints();
            ensureValidStoredDates();
        });

        // Final guard: prevent submission when dates are invalid, then clear form.
        const searchForm = document.querySelector('form[action="/search"]');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                const rentalCheck = validateRentalWindow(pickupInput.value, returnInput.value);
                if (!rentalCheck.valid) {
                    e.preventDefault();
                    showRentalValidationAlert(rentalCheck.message);
                    clearDateSelection({ keepPickup: !!pickupInput.value });
                }
            });
        }



        // --- Cart Date Change Modal Logic ---
        let lastPickupValue = pickupInput.value;
        let lastReturnValue = returnInput.value;

        function loadCart() {
            return JSON.parse(localStorage.getItem('cart') || '[]');
        }
        function saveCart(cart) {
            localStorage.setItem('cart', JSON.stringify(cart));
        }

        window.showCartDateChangeModal = function(onConfirm, onCancel) {
            // var isMobile = window.innerWidth <= 768;
            var modal = document.getElementById('cartDateChangeModal');
            if (!modal) return;
            var pickerModal = document.getElementById('dateTimeSelectionModal');
            if (pickerModal) {
                pickerModal.classList.add('hidden');
                pickerModal.classList.remove('flex');
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            var confirmBtn = document.getElementById('cartDateChangeConfirm');
            var cancelBtn = document.getElementById('cartDateChangeCancel');
            confirmBtn.onclick = function() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                if (onConfirm) onConfirm();
            };
            cancelBtn.onclick = function() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                if (onCancel) onCancel();
            };
        }

        function handleDateInputChangeWithCartCheck(input, key, picker, getLastValue, setLastValue) {
            input.addEventListener('focus', function() {
                setLastValue(input.value);
            });
            input.addEventListener('change', function(e) {
                const cart = loadCart();
                const newValue = input.value || '';
                const previousSelection = {
                    pickup: localStorage.getItem('pickupDatetime') || '',
                    return: localStorage.getItem('returnDatetime') || ''
                };

                if (cart.length > 0 && newValue !== getLastValue()) {
                    window.showCartDateChangeModal(
                        function onConfirm() {
                            saveCart([]);
                            persistCurrentDateSelection();
                            submitDateFormIfReady(input.form);
                        },
                        function onCancel() {
                            restoreDateSelection(previousSelection);
                            setLastValue(input.id === 'pickupDatetime' ? previousSelection.pickup : previousSelection.return);
                        }
                    );
                } else {
                    persistCurrentDateSelection();
                    submitDateFormIfReady(input.form);
                }

                setLastValue(newValue);
            });
        }

        handleDateInputChangeWithCartCheck(
            pickupInput,
            'pickupDatetime',
            pickupPicker,
            () => lastPickupValue,
            v => { lastPickupValue = v; }
        );
        handleDateInputChangeWithCartCheck(
            returnInput,
            'returnDatetime',
            returnPicker,
            () => lastReturnValue,
            v => { lastReturnValue = v; }
        );



        //console.log('Final Pickup:', localStorage.getItem('pickupDatetime'));
        //console.log('Final Return:', localStorage.getItem('returnDatetime'));
    });
</script>

<!-- Cart Date Change Modal (Desktop) -->
<style>
    #cartDateChangeModal { z-index: 1050 !important; }
    .flatpickr-calendar { z-index: 1040 !important; }
    .date-time-modal-calendar .flatpickr-calendar {
        width: 100%;
        border: 0;
        box-shadow: none;
        background: transparent;
        font-family: Barlow, sans-serif;
    }
    .date-time-modal-calendar .flatpickr-months {
        background: transparent;
        margin-bottom: 4px;
    }
    .date-time-modal-calendar .flatpickr-current-month {
        font-size: 1.15rem;
        font-weight: 700;
        color: #062B41;
    }
    .date-time-modal-calendar .flatpickr-weekday {
        color: #0b5f8a;
        font-weight: 700;
    }
    .date-time-modal-calendar .flatpickr-day {
        border-radius: 10px;
        color: #0f172a;
        font-weight: 600;
    }
    .date-time-modal-calendar .flatpickr-day.today {
        border-color: #0b5f8a;
    }
    .date-time-modal-calendar .flatpickr-day.selected,
    .date-time-modal-calendar .flatpickr-day.startRange,
    .date-time-modal-calendar .flatpickr-day.endRange {
        background: #0086c9;
        border-color: #0086c9;
        color: #fff;
    }
    .date-time-modal-calendar .flatpickr-day.flatpickr-disabled,
    .date-time-modal-calendar .flatpickr-day.flatpickr-disabled:hover {
        color: #b9c6d2;
        cursor: not-allowed;
    }
    @media (max-width: 640px) {
        .date-time-modal-calendar .flatpickr-current-month {
            font-size: 1rem;
        }
        .date-time-modal-calendar .flatpickr-day {
            max-width: 34px;
            line-height: 34px;
            height: 34px;
        }
    }
    /* @media (max-width: 768px) {
        #cartDateChangeModal { display: none !important; }
    } */
    
</style>
<?php endif; ?>