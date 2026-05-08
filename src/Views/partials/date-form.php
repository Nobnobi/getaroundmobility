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

<!-- Cart Date Change Modal: Only one instance, outside all panels -->
<div id="cartDateChangeModal" class="fixed inset-0 z-[1050] items-center justify-center bg-black/50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 relative">
        <h2 class="text-xl font-bold mb-4 text-center">Change Date?</h2>
        <p class="mb-6 text-gray-700 text-center">Changing the pickup or return date will clear your cart. Do you want to proceed?</p>
        <div class="flex justify-center gap-4">
            <button id="cartDateChangeConfirm" class="bg-red-600 text-white px-4 py-2 rounded font-semibold cursor-pointer">Yes, Clear Cart</button>
            <button id="cartDateChangeCancel" class="bg-gray-300 text-gray-800 px-4 py-2 rounded font-semibold cursor-pointer">Cancel</button>
        </div>
    </div>
</div>




<script>
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

        function getMaxReturnDate(pickupDate) {
            const maxReturnDate = new Date(pickupDate);
            maxReturnDate.setDate(maxReturnDate.getDate() + MAX_RENTAL_DAYS);
            return maxReturnDate;
        }

        function syncReturnLimit(pickupDate) {
            returnPicker.set('minDate', pickupDate);
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
        }

        function showRentalValidationAlert(message) {
            alert(message);
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
                    console.log('[Flatpickr] pickupDatetime onChange fired:', selectedDates);
                if (selectedDates[0]) {
                    syncReturnLimit(selectedDates[0]);

                    if (returnInput.value && new Date(returnInput.value) < selectedDates[0]) {
                        returnInput.value = '';
                        returnPicker.clear();
                        localStorage.removeItem('returnDatetime');
                    } else if (returnInput.value && new Date(returnInput.value) > getMaxReturnDate(selectedDates[0])) {
                        returnInput.value = '';
                        returnPicker.clear();
                        localStorage.removeItem('returnDatetime');
                        showRentalValidationAlert('Online booking is limited to 31 days. For rentals longer than 31 days, please call us.');
                    }
                        // Modal logic: check cart and show modal if needed
                        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                        const newValue = flatpickr.formatDate(selectedDates[0], "Y-m-d H:i");
                            const prevValue = localStorage.getItem('pickupDatetime') || '';
                            if (cart.length > 0 && newValue !== prevValue) {
                            console.log('[Flatpickr] Cart not empty and pickup date changed, triggering modal...');
                            window.showCartDateChangeModal(
                                function onConfirm() {
                                    localStorage.setItem('cart', JSON.stringify([]));
                                    localStorage.setItem('pickupDatetime', newValue);
                                    pickupPicker.setDate(newValue, false);
                                    if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                                    if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                                    // location.reload();
                                },
                                function onCancel() {
                                    // Restore previous value
                                        pickupPicker.setDate(prevValue, false);
                                        localStorage.setItem('pickupDatetime', prevValue);
                                    if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                                    if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                                    // location.reload();
                                }
                            );
                        } else {
                            localStorage.setItem('pickupDatetime', newValue);
                            pickupPicker.setDate(newValue, false);
                            if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                            if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                            // location.reload();
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
                    console.log('[Flatpickr] returnDatetime onChange fired:', selectedDates);
                if (selectedDates[0]) {
                    // Show "call us" banner if return date is at the 31-day maximum
                    const longRentalBanner = document.getElementById('longRentalCallBanner');
                    if (longRentalBanner && pickupInput.value) {
                        const pickupDate = new Date(pickupInput.value);
                        const diffDays = Math.ceil((selectedDates[0] - pickupDate) / (1000 * 60 * 60 * 24));
                        if (diffDays >= MAX_RENTAL_DAYS) {
                            longRentalBanner.classList.remove('hidden');
                            longRentalBanner.classList.add('flex');
                        } else {
                            longRentalBanner.classList.add('hidden');
                            longRentalBanner.classList.remove('flex');
                        }
                    }
                    // If pickup is after new return, clear pickup
                    if (pickupInput.value && new Date(pickupInput.value) >= selectedDates[0]) {
                        showRentalValidationAlert('Return date/time must be after pickup date/time. Please select your dates again.');
                        clearDateSelection();
                        return;
                    }

                    const rentalCheck = validateRentalWindow(pickupInput.value, flatpickr.formatDate(selectedDates[0], 'Y-m-d H:i'));
                    if (!rentalCheck.valid) {
                        showRentalValidationAlert(rentalCheck.message);
                        clearDateSelection();
                        return;
                    }
                        // Modal logic: check cart and show modal if needed
                        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                        const newValue = flatpickr.formatDate(selectedDates[0], "Y-m-d H:i");
                            const prevValue = localStorage.getItem('returnDatetime') || '';
                            if (cart.length > 0 && newValue !== prevValue) {
                            console.log('[Flatpickr] Cart not empty and return date changed, triggering modal...');
                            window.showCartDateChangeModal(
                                function onConfirm() {
                                    localStorage.setItem('cart', JSON.stringify([]));
                                    localStorage.setItem('returnDatetime', newValue);
                                    returnPicker.setDate(newValue, false);
                                    if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                                    if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                                    // location.reload();
                                },
                                function onCancel() {
                                    // Restore previous value
                                        returnPicker.setDate(prevValue, false);
                                        localStorage.setItem('returnDatetime', prevValue);
                                    if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                                    if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                                    // location.reload();
                                }
                            );
                        } else {
                            localStorage.setItem('returnDatetime', newValue);
                            returnPicker.setDate(newValue, false);
                            if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                            if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                            // location.reload();
                        }
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

        syncReturnLimit(new Date(pickupInput.value));

        // Return: load saved, or set 1 day after pickup
        if (!savedReturn || savedReturn === 'null' || savedReturn === '') {
            const baseDate = new Date(savedPickup || pickupInput.value);
            const defaultReturn = new Date(baseDate);
            defaultReturn.setDate(defaultReturn.getDate() + 1);
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
            const pickupDate = new Date(pickupVal);
            const returnDate = new Date(returnVal);
            if (pickupDate > returnDate) {
                // Clear return
                returnInput.value = '';
                returnPicker.clear();
                localStorage.removeItem('returnDatetime');
            } else if (returnDate > getMaxReturnDate(pickupDate)) {
                returnInput.value = '';
                returnPicker.clear();
                localStorage.removeItem('returnDatetime');
            } else if (returnDate < pickupDate) {
                // Clear pickup (shouldn't happen, but for completeness)
                pickupInput.value = '';
                pickupPicker.clear();
                localStorage.removeItem('pickupDatetime');
            }
        }

        // Final guard: prevent submission when dates are invalid, then clear form.
        const searchForm = document.querySelector('form[action="/search"]');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                const rentalCheck = validateRentalWindow(pickupInput.value, returnInput.value);
                if (!rentalCheck.valid) {
                    e.preventDefault();
                    showRentalValidationAlert(rentalCheck.message);
                    clearDateSelection();
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
                location.reload();
            };
        }

        function handleDateInputChangeWithCartCheck(input, key, picker, getLastValue, setLastValue) {
            input.addEventListener('focus', function() {
                setLastValue(input.value);
            });
            input.addEventListener('change', function(e) {
                const cart = loadCart();
                const newValue = input.value || '';
                if (cart.length > 0 && newValue !== getLastValue()) {
                    console.log('Cart not empty and date changed, triggering modal...');
                    console.log('Modal should appear now. Cart:', cart, 'Input:', input.id, 'Old value:', getLastValue(), 'New value:', newValue);
                    // Save current values to restore on cancel
                    const prevPickup = document.getElementById('pickupDatetime').value;
                    const prevReturn = document.getElementById('returnDatetime').value;
                    showCartDateChangeModal(
                        function onConfirm() {
                            saveCart([]);
                            localStorage.setItem(key, newValue);
                            if (picker) picker.setDate(newValue, false);
                            if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                            if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                            if (input.form) {
                                input.form.submit();
                            } else {
                                location.reload();
                            }
                        },
                        function onCancel() {
                            input.value = getLastValue();
                            if (picker) picker.setDate(getLastValue(), false);
                            document.getElementById('pickupDatetime').value = prevPickup;
                            document.getElementById('returnDatetime').value = prevReturn;
                            if (picker && input.id === 'pickupDatetime') picker.setDate(prevPickup, false);
                            if (picker && input.id === 'returnDatetime') picker.setDate(prevReturn, false);
                            if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                            if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                        }
                    );
                } else {
                    localStorage.setItem(key, newValue);
                    if (picker) picker.setDate(newValue, false);
                    if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                    if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                    if (input.form) {
                        input.form.submit();
                    } else {
                        location.reload();
                    }
                }
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