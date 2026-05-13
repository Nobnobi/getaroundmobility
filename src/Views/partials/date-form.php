<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\date-form.php -->

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
                    <span>Online bookings are limited to <strong>31 days</strong>. For longer rentals, please <strong>call us</strong>.</span>
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



        function getNearest15Min() {
            const now = new Date();
            now.setSeconds(0, 0);
            const minutes = now.getMinutes();
            const remainder = minutes % 15;
            if (remainder !== 0) {  
                now.setMinutes(minutes + (15 - remainder));
            }
            return now;
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

        function syncReturnLimit(pickupDate) {
            const minReturnDate = getMinReturnDate(pickupDate);
            returnPicker.set('minDate', minReturnDate);
            returnPicker.set('maxDate', getMaxReturnDate(pickupDate));
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
            input.value = value || '';
            if (!picker) return;
            if (value) {
                picker.setDate(value, false);
            } else {
                picker.clear();
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
            const now = getNearest15Min();

            if (!pickupDate || !returnDate) {
                return { valid: false, reason: 'missing', message: 'Please select both Pickup and Return date/time.' };
            }

            if (pickupDate < now || returnDate < now) {
                return { valid: false, reason: 'past', message: 'Past dates are not allowed. Please choose valid pickup and return dates.' };
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

        const pickupPicker = flatpickr(pickupInput, {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "F j, Y h:i K",
            minDate: getNearest15Min(),
            time_24hr: true,
            minuteIncrement: 15,
            disableMobile: true,
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
            minDate: getNearest15Min(),
            time_24hr: true,
            minuteIncrement: 15,
            disableMobile: true,
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
            const nearest = getNearest15Min();
            const formatted = flatpickr.formatDate(nearest, "Y-m-d H:i");
            pickupInput.value = formatted;
            pickupPicker.setDate(nearest, false);
            localStorage.setItem('pickupDatetime', formatted);
        } else {
            pickupInput.value = savedPickup;
            pickupPicker.setDate(savedPickup, false);
        }

        syncReturnLimit(parseDateValue(pickupInput.value));

        // Return: load saved, or set 1 day after pickup
        if (!savedReturn || savedReturn === 'null' || savedReturn === '') {
            const baseDate = new Date(savedPickup || pickupInput.value);
            const defaultReturn = new Date(baseDate);
            defaultReturn.setMinutes(defaultReturn.getMinutes() + MIN_RETURN_GAP_MINUTES);
            const formatted = flatpickr.formatDate(defaultReturn, "Y-m-d H:i");
            returnInput.value = formatted;
            returnPicker.setDate(defaultReturn, false);
            localStorage.setItem('returnDatetime', formatted);
        } else {
            returnInput.value = savedReturn;
            returnPicker.setDate(savedReturn, false);
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

        notifyDateSelectionChanged();

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
                    showCartDateChangeModal(
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
    /* @media (max-width: 768px) {
        #cartDateChangeModal { display: none !important; }
    } */
    
</style>
<?php endif; ?>