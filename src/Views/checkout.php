<?php
require_once __DIR__ . '/../../vendor/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}
?>
    
    <!-- Loading overlay (hidden by default) -->
    <div id="loadingOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0,0,0,0.5);">
         <div class="bg-white rounded-lg p-6 flex items-center gap-4">
             <svg class="animate-spin h-6 w-6 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
             <div class="text-gray-700 font-medium">Processing your order…</div>
         </div>
     </div>

    <!-- CHECKOUT SUMMARY -->
    <div class="container mx-auto px-2 py-8 mt-15">
        <a href="/cart" class="inline-flex items-center gap-1.5 text-[#0086C9] font-medium hover:underline focus:outline-none focus:underline">
            <span class="text-lg leading-none">←</span>
            <span>Back to Cart</span>
        </a>
        <h1 class="text-2xl font-bold mt-4 mb-6 font-[Barlow]">Checkout</h1>

        <div class="flex flex-col lg:flex-row gap-8 w-full">

            <!-- MOBILE ORDER SUMMARY BUTTON -->
            <button id="mobileOrderSummaryBtn" type="button"
                class="fixed z-40 bottom-6 right-6 bg-[#0086C9] text-white rounded-full shadow-lg flex items-center justify-center w-14 h-14 md:hidden focus:outline-none focus:ring-4 focus:ring-blue-300 transition-all duration-200"
                aria-label="Show order summary"
                style="box-shadow: 0 4px 16px rgba(0,134,201,0.18);">
                <img src="/img/checklist-white.svg" alt="Order Summary" class="w-7 h-7">
            </button>

            <!-- MOBILE ORDER SUMMARY PANEL -->
            <div id="mobileOrderSummaryOverlay" class="fixed inset-0 z-50 bg-black/40 hidden md:hidden"></div>
            <aside id="mobileOrderSummaryPanel" class="fixed bottom-0 left-0 w-full max-h-[80vh] bg-white rounded-t-2xl shadow-2xl z-50 p-6 pt-4 flex flex-col gap-4 translate-y-full transition-transform duration-300 md:hidden">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg font-semibold">Order Summary</h2>
                    <button id="closeMobileOrderSummary" class="text-2xl text-gray-400 hover:text-black font-bold">&times;</button>
                </div>
                <div id="mobileCheckoutSummary"></div>
            </aside>
            <!-- LEFT COLUMN: Checkout Form -->
            <div class="w-full lg:w-2/3 flex-shrink-0 mb-8 lg:mb-0">
                
                <form id="checkoutForm" class="h-full flex flex-col <?php echo $showConfirmation ? 'hidden' : ''; ?>" method="post" action="/checkout">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="bg-white rounded-lg shadow p-6 flex flex-col gap-6 h-full">
                        <!-- PROMPT USER -->
                        <?php if (!$user): ?>
                        <div>
                            <p class="mb-2 text-[#535862] text-xl">Already have an account?
                                <a href="/login" class="text-blue-500 hover:underline">Log In</a>
                            </p>
                        </div>
                        <?php endif; ?>

                        <!-- Contact Info -->
                        <div class="text-[#535862]" id="contactInfoSection">
                            <div class="mb-4 flex flex-col md:flex-row md:items-center gap-2">
                                <label class="block text-sm font-medium mb-1 md:mb-0 md:w-40">First Name</label>
                                <input type="text" name="first_name"
                                    value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                                    class="border rounded p-2 border-[#535862] focus:outline-none focus:ring-2 focus:ring-[#535862] max-w-md w-full md:w-64"
                                    required <?= $user ? 'readonly' : '' ?>
                                    placeholder="e.g. John">
                            </div>
                            <div class="mb-4 flex flex-col md:flex-row md:items-center gap-2">
                                <label class="block text-sm font-medium mb-1 md:mb-0 md:w-40">Last Name</label>
                                <input type="text" name="last_name"
                                    value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                                    class="border rounded p-2 border-[#535862] focus:outline-none focus:ring-2 focus:ring-[#535862] max-w-md w-full md:w-64"
                                    required <?= $user ? 'readonly' : '' ?>
                                    placeholder="e.g. Smith">
                            </div>
                            
                            <!-- CONTACT NUMBER -->
                            <div class="mb-4 flex flex-col md:flex-row md:items-center gap-2">
                                <label class="block text-sm font-medium mb-1 md:mb-0 md:w-40">Contact Number</label>
                                <input type="tel" name="phone"
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                    class="border rounded p-2 border-[#535862] focus:outline-none focus:ring-2 focus:ring-[#535862] max-w-md w-full md:w-64"
                                    required 
                                    placeholder="e.g. 7021234567"
                                    <?= $user ? 'readonly data-original-phone=\"' . htmlspecialchars($user['phone'] ?? '') . '\"' : '' ?>
                                    id="phoneInput">
                                <span id="phoneWarning" class="text-red-500 text-xs mt-1 hidden md:ml-2">Please enter numbers only.</span>
                            </div>

                            <div class="mb-4 flex flex-col md:flex-row md:items-center gap-2">
                                <label class="block text-sm font-medium mb-1 md:mb-0 md:w-40">Email Address</label>
                                <input type="email" name="email"
                                    value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                    class="border rounded p-2 border-[#535862] focus:outline-none focus:ring-2 focus:ring-[#535862] max-w-md w-full md:w-72"
                                    required <?= $user ? 'readonly' : '' ?>
                                    placeholder="e.g. john@email.com">
                            </div>
                            <?php if (!$user): ?>
                            <div class="flex justify-end">
                                <button type="button" id="clearContactBtn" class="mt-2 px-4 py-2 bg-white text-gray-700 rounded cursor-pointer hover:bg-gray-300 text-sm border font-[Barlow] border-[#535862] focus:outline-none focus:ring-2 focus:ring-[#535862] transition-colors duration-200">
                                    Clear
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Delivery Address -->
                        <div class="text-[#535862]" id="deliveryAddressSection">
                            <h2 class="text-lg font-semibold mb-2 text-black">Delivery Address</h2>
                            <div class="mb-4">
                                <div class="flex gap-4 mb-2">
                                    <label>
                                        <input type="radio" name="delivery_type" value="hotel" id="deliveryHotel" checked>
                                        Deliver to partner hotel
                                    </label>
                                    <label>
                                        <input type="radio" name="delivery_type" value="pickup" id="deliveryPickup">
                                        Pickup at store
                                    </label>
                                </div>
                                <div id="hotelDropdown" class="mb-4">
                                    <label class="block text-sm font-medium mb-1">Select Partner Hotel</label>
                                    <select name="hotel_id" class="w-full border rounded p-2 border-[#535862]">
                                        <option value="">Select a hotel</option>
                                        <?php foreach ($partnerHotels as $hotel): ?>
                                            <option value="<?= $hotel['id'] ?>"
                                                data-address1="<?= htmlspecialchars($hotel['address1']) ?>"
                                                data-address2="<?= htmlspecialchars($hotel['address2']) ?>"
                                                data-state="<?= htmlspecialchars($hotel['state']) ?>"
                                                data-zip="<?= htmlspecialchars($hotel['zip']) ?>"
                                            ><?= htmlspecialchars($hotel['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="addressFields">
                                    <input type="text" name="address1" class="w-full border rounded p-2 mb-2" required placeholder="Address Line 1">
                                    <input type="text" name="address2" class="w-full border rounded p-2 mb-2" placeholder="Address Line 2">
                                    <div class="flex flex-col md:flex-row gap-2">
                                        <input type="text" name="state" class="border rounded p-2 mb-2 md:mb-0 border-[#535862] focus:outline-none focus:ring-2 focus:ring-[#535862] w-full md:w-32" required placeholder="e.g. NV">
                                        <input type="text" name="zip" class="border rounded p-2 border-[#535862] focus:outline-none focus:ring-2 focus:ring-[#535862] w-full md:w-32" required placeholder="e.g. 89109">
                                    </div>
                                </div>
                                <div class="mb-4" id="pickupLocationSection" style="display:none;">
                                    <label class="block text-sm font-medium mb-1">Pickup Location</label>
                                    <select name="pickup_location" class="w-full border rounded p-2 border-[#535862]">
                                        <option value="">Select a pickup location</option>
                                        <?php foreach ($pickupLocations as $location): ?>
                                            <option value="<?= $location['id'] ?>"><?= htmlspecialchars($location['name']) ?> - <?= htmlspecialchars($location['address']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                               
                            </div>
                        </div>

                        <!-- Payment Options -->
                        <div class="text-[#535862]">
                            <h2 class="text-lg font-semibold mb-2 text-black">Payment Options</h2>
                            <div class="flex flex-col gap-2">
                                <label class="flex items-center space-x-2">
                                    <input type="radio" name="payment" value="card" id="paymentCard" class="form-radio" required>
                                    <span>Debit/Credit Card powered by Stripe</span>
                                </label>

                                <div id="stripe-payment-wrapper" class="hidden border border-[#d1d5db] rounded-lg p-4">
                                    <div id="stripe-payment-element"></div>
                                    <p id="stripePaymentError" class="text-red-600 text-sm mt-2 hidden"></p>
                                </div>

                                <label class="flex items-center space-x-2">
                                    <input type="radio" name="payment" value="paypal" id="paymentPaypal" class="form-radio" required>
                                    <span>Paypal</span>
                                </label>
                                <!-- Move the policy agreement checkbox here -->
                                <div class="flex items-center mt-2">
                                    <input type="checkbox" name="agree_policy" required class="mr-2">
                                    <span>I agree to the&nbsp;</span>
                                    <a onclick="openPolicyModal()" class="text-blue-600 underline cursor-pointer">rental policy and terms</a>
                                </div>
                            </div>
                        </div>

                        

                        <div id="paypal-button-container" style="display:none;"></div>
                        <p id="result-message"></p>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full bg-[#0086C9] text-white py-4 rounded-lg text-lg hover:bg-blue-600 cursor-pointer font-[Barlow]">
                            Confirm Order
                        </button>

                        <input type="hidden" name="pickup_datetime" id="pickupDatetimeCheckout" value="">
                        <input type="hidden" name="return_datetime" id="returnDatetimeCheckout" value="">
                    </div>
                </form>
                
                
                <!-- ORDER CONFIRMATION (WILL ONLY APPEAR AFTER SUCCESS) -->
                <div id="orderConfirmation" class="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4 <?php echo $showConfirmation ? '' : 'hidden'; ?>">
                    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl p-8 md:p-12">

                        <h2 class="text-3xl font-bold text-center text-gray-800 mb-10">
                            Thank you for renting with us!
                        </h2>

                        <div class="space-y-8">

                            <!-- PERSONAL INFO + DATES -->
                            <div class="bg-gray-50 rounded-xl p-6 space-y-5">
                                <div class="flex justify-between items-center py-3 border-b border-gray-200">
                                    <span class="font-medium text-gray-700">Name:</span>
                                    <span id="confName" class="font-bold text-[#0086C9] bg-blue-50 px-5 py-2 rounded-lg">—</span>
                                </div>
                                <div class="flex justify-between items-center py-3 border-b border-gray-200">
                                    <span class="font-medium text-gray-700">Contact Number:</span>
                                    <span id="confPhone" class="font-bold text-[#0086C9] bg-blue-50 px-5 py-2 rounded-lg">—</span>
                                </div>
                                <div class="flex justify-between items-center py-3 border-b border-gray-200">
                                    <span class="font-medium text-gray-700">Email Address:</span>
                                    <span id="confEmail" class="font-bold text-[#0086C9] bg-blue-50 px-5 py-2 rounded-lg">—</span>
                                </div>
                                <div class="flex justify-between items-center py-3 border-b border-gray-200">
                                    <span class="font-medium text-gray-700">Pickup Date & Time:</span>
                                    <span id="confPickup" class="font-bold text-[#0086C9] bg-blue-50 px-5 py-2 rounded-lg">—</span>
                                </div>
                                <div class="flex justify-between items-center py-3">
                                    <span class="font-medium text-gray-700">Return Date & Time:</span>
                                    <span id="confReturn" class="font-bold text-[#0086C9] bg-blue-50 px-5 py-2 rounded-lg">—</span>
                                </div>
                            </div>

                            <!-- DELIVERY ADDRESS -->
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="font-semibold text-gray-800 mb-4 text-lg">Delivery Address</h3>
                                <div class="space-y-3 text-gray-700">
                                    <div class="flex justify-between">
                                        <span class="font-medium">Type:</span>
                                        <span id="confDeliveryType" class="font-bold text-[#0086C9]">—</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="font-medium">Address Line 1:</span>
                                        <span id="confAddress1" class="font-bold text-[#0086C9]">—</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="font-medium">Address Line 2:</span>
                                        <span id="confAddress2" class="font-bold text-[#0086C9]">—</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="font-medium">State:</span>
                                        <span id="confState" class="font-bold text-[#0086C9]">—</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="font-medium">Zip Code:</span>
                                        <span id="confZip" class="font-bold text-[#0086C9]">—</span>
                                    </div>
                                    <div class="flex/CVE flex justify-between" id="confHotelRow" style="display:none;">
                                        <span class="font-medium">Hotel:</span>
                                        <span id="confHotelName" class="font-bold text-[#0086C9]">—</span>
                                    </div>
                                    <div class="flex justify-between" id="confPickupLocationRow" style="display:none;">
                                        <span class="font-medium">Pickup Location:</span>
                                        <span id="confPickupLocation" class="font-bold text-[#0086C9]">—</span>
                                    </div>
                                </div>
                            </div>

                            <!-- PAYMENT METHOD -->
                            <div class="bg-gray-50 rounded-xl p-6">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold text-gray-800 text-lg">Payment Option:</span>
                                    <span id="confPayment" class="font-bold text-[#0086C9] bg-green-100 px-6 py-3 rounded-lg text-lg">—</span>
                                </div>
                            </div>

                        </div>

                        <div class="mt-12 text-center">
                            <a href="/" class="text-blue-600 hover:underline text-lg font-medium">
                                ← Back to Homepage
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Order Summary -->
            <div class="w-full lg:w-1/3 flex-shrink-0 hidden lg:flex">
                <div class="bg-white p-6 rounded-lg shadow h-fit flex flex-col">
                    <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                    <div class="space-y-3 text-sm mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <span class="font-medium text-gray-600">Pickup</span>
                            <span id="summaryPickupDateTime" class="text-right font-semibold text-gray-900">Not selected</span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="font-medium text-gray-600">Return</span>
                            <span id="summaryReturnDateTime" class="text-right font-semibold text-gray-900">Not selected</span>
                        </div>
                    </div>
                    <div id="checkoutSummary"></div>
                </div>
            </div>

        </div>
    </div>

    <script>
    // --- Mobile Order Summary Button & Panel Logic ---
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('mobileOrderSummaryBtn');
        const panel = document.getElementById('mobileOrderSummaryPanel');
        const overlay = document.getElementById('mobileOrderSummaryOverlay');
        const closeBtn = document.getElementById('closeMobileOrderSummary');
        const mobileSummary = document.getElementById('mobileCheckoutSummary');
        const desktopSummary = document.getElementById('checkoutSummary');

        function openPanel() {
            panel.classList.remove('translate-y-full');
            overlay.classList.remove('hidden');
            // Copy summary content
            if (desktopSummary && mobileSummary) {
                mobileSummary.innerHTML = desktopSummary.innerHTML;
            }
        }
        function closePanel() {
            panel.classList.add('translate-y-full');
            overlay.classList.add('hidden');
        }
        if (btn) btn.addEventListener('click', openPanel);
        if (closeBtn) closeBtn.addEventListener('click', closePanel);
        if (overlay) overlay.addEventListener('click', closePanel);

        // Keep mobile summary in sync when cart changes
        const observer = new MutationObserver(() => {
            if (!panel.classList.contains('translate-y-full')) {
                mobileSummary.innerHTML = desktopSummary.innerHTML;
            }
        });
        if (desktopSummary) {
            observer.observe(desktopSummary, { childList: true, subtree: true });
        }
    });
    </script>

    <!-- Rental Policy Modal -->
    <div id="policyModal" style="background: rgba(0,0,0,0.6);" class="fixed inset-0 flex items-center justify-center z-50 hidden backdrop-blur-sm font-sans">
        <div class="bg-[#fefbea] w-full max-w-3xl max-h-[90vh] overflow-hidden rounded-md shadow-2xl border border-gray-400 relative">

            <!-- Close Button -->
            <button onclick="closePolicyModal()" class="absolute top-3 right-3 text-gray-600 text-3xl leading-none hover:text-black cursor-pointer">&times;</button>

            <!-- Header -->
            <div class="px-6 pt-6 text-center border-b border-gray-400 pb-3">
                <img src="/img/Original logo.svg" alt="Logo" class="w-28 mx-auto mb-2">
                <h2 class="text-xl font-bold tracking-wide">RENTAL AGREEMENT</h2>
            </div>

            <!-- Grey Section Header -->
            <div class="bg-gray-300 text-center py-2 border-y border-gray-400">
                <span class="font-semibold tracking-wider">TERMS & CONDITIONS</span>
            </div>

            <!-- Scrollable Content -->
            <div class="p-6 overflow-y-auto max-h-[65vh] leading-relaxed text-[15px]">

                <p class="mb-4">
                    I understand that I will be held personally responsible for all damage to the equipment other than normal wear.
                </p>

                <p class="font-bold underline">LOSS OR THEFT.</p>
                <p class="mb-4">
                    Customer is responsible for the replacement value of the equipment if lost or stolen.
                </p>

                <p class="font-bold underline">POSSESSION.</p>
                <p class="mb-4">
                    Customer will not give or transfer possession of the equipment to anyone else and the equipment shall not be transported out of Clark County, Nevada, without express written consent from Get Around Mobility. The equipment shall be returned to Rental Location unless arrangements have been made directly with Get Around Mobility.
                </p>

                <p class="font-bold underline">LATE FEE.</p>
                <p class="mb-4">                    
                    If scooter is NOT returned by designated date AND time additional fees will automatically be incurred.
                </p>

                <p class="font-bold underline">OPERATION</p>
                <p class="mb-4">
                    Read Operating Manual and all warning labels before operating equipment. Use Extreme caution and reduce speed when approaching, entering or exiting an elevator. Never attempt to negotiate stairs with equipment.
                </p>

                <p class="font-bold underline">WARNING</p>
                <p class="mb-4">
                    Forfeiture of equipment without refund may result if the following rules are not obeyed.
                </p>

                <p class="font-bold underline">NO SPEEDING</p>
                <p class="mb-4">
                    Customer is to set the speed control knob to Turtle mode while indoors or in crowded areas. NO PASSENGERS. Customer shall not allow any passengers at any time.
                </p>

                <p class="font-bold underline">SOBRIETY</p>
                <p class="mb-4">
                    Equipment should never be operated by intoxicated person or persons. I will not operate my scooter while under the influence of Marijuana, Alcohol, or Drugs whether prescription or recreational or when my mental or physical condition may otherwise be impaired. UTMOST COURTESY. The operator should always yield to pedestrians and operate the equipment in a reasonable manner without threat to the general public.
                </p>

                <p class="font-bold underline">ENTIRE AGREEMENT</p>
                <p class="mb-4">
                    This Agreement contains the entire understanding between and among the parties and supersedes any prior understandings and agreements among them respecting the subject matter of this Agreement.
                </p>

                <p class="font-bold underline">LIABILITY</p>
                <p class="mb-4">
                    Customer expressly assumes all liability arising out of operation of the equipment. Customer agrees to indemnify and hold harmless Get Around Mobility from any and all liability resulting from customer's acts or omissions including, but not limited to, claims and/or lawsuits for personal injury, property damage, legal fees, costs, lawsuits, claims and judgments that may arise from customer's use of equipment.
                </p>

                <p class="font-bold underline">AGE</p>
                <p class="mb-4">
                    Person or persons must be at least 21 years old to operate scooter.
                </p>

                <p class="font-bold underline">DISPUTES</p>
                <p class="mb-4">
                    Any dispute arising out of this agreement shall be brought in the Eighth Judicial District, Clark County, Nevada. In the event suit or action is brought by any party under this Agreement to enforce any of its terms, or in any appeal there- from, it is agreed that the prevailing party shall be entitled to reasonable attorneys fees and costs.
                </p>

                <p class="mb-4">
                    If equipment is not returned, customer or renter is responsible for full replacement cost of equipment plus any legal fees to recover or replace equipment. Customer is legally responsible for equipment. If not returned, legal action will be administered.
                </p>

                <p class="mb-4">
                    If equipment has to be recovered because of negligence, a recovery fee to be determined by Get Around Mobility will apply.
                </p>

                <p class="mb-4">
                    I have read and understood the forgoing.
                </p>

            </div>
            
            <div class="flex items-center justify-center mb-4">
                <button onclick="agreeAndClosePolicyModal()" class="bg-[#0086C9] text-white px-6 py-2 rounded font-bold mt-2 cursor-pointer">I Agree</button>
            </div>
            
        </div>

        
    </div>

    <script src="https://js.stripe.com/v3/"></script>
    <!-- Initialize the JS-SDK -->
    <script
        src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars(getenv('PAYPAL_CLIENT_ID') ?: ($_ENV['PAYPAL_CLIENT_ID'] ?? '')) ?>&currency=USD&components=buttons&enable-funding=card"
    ></script>

    <script>
    
    function validateDeliverySelection() {
        const deliveryType = document.querySelector('input[name="delivery_type"]:checked')?.value;
        const hotelDropdown = document.querySelector('select[name="hotel_id"]');
        const pickupDropdown = document.querySelector('select[name="pickup_location"]');

        if (deliveryType === 'hotel') {
            if (!hotelDropdown || !hotelDropdown.value) {
                alert('Please select a partner hotel for delivery.');
                return false;
            }
        }

        if (deliveryType === 'pickup') {
            if (!pickupDropdown || !pickupDropdown.value) {
                alert('Please select a store for pickup.');
                return false;
            }
        }

        return true;
    }

    function loadCart() {
        const keysToTry = ['cart', 'getaround_cart', 'cart_items'];
        for (const key of keysToTry) {
            const raw = localStorage.getItem(key);
            if (!raw) continue;
            try {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) return parsed;
                // If it's an object with items array, try common shapes:
                if (parsed && Array.isArray(parsed.items)) return parsed.items;
            } catch (err) {
                console.warn('Failed to parse cart from localStorage key:', key, err);
            }
        }

        // Try sessionStorage as a fallback
        try {
            const sessRaw = sessionStorage.getItem('cart') || sessionStorage.getItem('getaround_cart');
            if (sessRaw) {
                const parsed = JSON.parse(sessRaw);
                if (Array.isArray(parsed)) return parsed;
                if (parsed && Array.isArray(parsed.items)) return parsed.items;
            }
        } catch (err) {
            console.warn('Failed to parse cart from sessionStorage', err);
        }

        // nothing found — return empty array
        console.info('Cart appears empty in checkout view (no local/session storage keys matched)');
        return [];
    }

    function renderCheckoutSummary() {
        const cart = loadCart();
        const summaryContainer = document.getElementById('checkoutSummary');
        if (!summaryContainer) return;
        if (cart.length === 0) {
            summaryContainer.innerHTML = '<p class="text-gray-500">Your cart is empty.</p>';
            return;
        }
        let subtotal = 0;
        let itemsHtml = '<ul class="divide-y mb-4">';
        cart.forEach(item => {
            const qty = Number(item.qty || item.quantity || 1);
            const price = Number(item.price || item.unit_price || 0);
            const lineTotal = price * qty;
            subtotal += lineTotal;
            const image = (item.image_url && item.image_url.trim() !== '') ? item.image_url : '/img/placeholder.png';
            itemsHtml += `
            <li class="flex items-center py-4 gap-4">
                <img src="${image}"
                    alt="${(item.name || '')}"
                    class="w-16 h-16 object-cover rounded border border-gray-200 bg-gray-100 flex-shrink-0">
                <div class="flex-1 flex flex-col sm:flex-row sm:items-center gap-2">
                    <div class="font-semibold text-base">${(item.name || '')}</div>
                    <div class="sm:ml-auto flex flex-col items-end">
                        <span class="text-[#0086C9] font-bold text-base">$${price.toFixed(2)}</span>
                        <span class="text-xs text-gray-500">Qty: ${qty}</span>
                    </div>
                </div>
            </li>`;
        });
        itemsHtml += '</ul>';
        const tax = subtotal * 0.12;
        const total = subtotal + tax;
        summaryContainer.innerHTML = `
            ${itemsHtml}
            <div class="flex justify-between mb-2">
                <span>Rental subtotal</span>
                <span>$${subtotal.toFixed(2)}</span>
            </div>
            <div class="flex justify-between mb-2">
                <span>Tax included</span>
                <span>$${tax.toFixed(2)}</span>
            </div>
            <div class="flex justify-between font-bold text-lg mb-6">
                <span>Total</span>
                <span>$${total.toFixed(2)}</span>
            </div>
        `;
    }

    function formatCheckoutDateTime(value) {
        if (!value) return 'Not selected';
        const parsed = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return value;
        return parsed.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function renderSelectedDatesSummary() {
        const pickupDisplay = document.getElementById('summaryPickupDateTime');
        const returnDisplay = document.getElementById('summaryReturnDateTime');
        if (!pickupDisplay || !returnDisplay) return;

        const pickup = localStorage.getItem('pickupDatetime') || document.getElementById('pickupDatetimeCheckout')?.value || '';
        const ret = localStorage.getItem('returnDatetime') || document.getElementById('returnDatetimeCheckout')?.value || '';

        pickupDisplay.textContent = formatCheckoutDateTime(pickup);
        returnDisplay.textContent = formatCheckoutDateTime(ret);
    }

// update UI when other tabs/scripts change localStorage
window.addEventListener('storage', function(e) {
    if (!e.key) return;
    if (['cart', 'getaround_cart', 'cart_items'].includes(e.key)) {
        console.info('Storage event for cart key:', e.key);
        renderCheckoutSummary();
    }
    if (['pickupDatetime', 'returnDatetime'].includes(e.key)) {
        renderSelectedDatesSummary();
    }
});

// call on load
document.addEventListener('DOMContentLoaded', renderCheckoutSummary);
document.addEventListener('DOMContentLoaded', renderSelectedDatesSummary);

const stripePk = "<?= $_ENV['STRIPE_PUBLISHABLE'] ?>";
const stripeClient = stripePk ? Stripe(stripePk) : null;
let stripeElements = null;
let stripePaymentElement = null;
let stripeInitializing = false;

async function initializeStripePaymentElement(form) {
    if (!stripeClient) {
        throw new Error('Stripe publishable key is not configured.');
    }
    if (stripeElements || stripeInitializing) return;

    stripeInitializing = true;
    try {
        const formData = new FormData(form);
        formData.append('cart', localStorage.getItem('cart') || '[]');

        const response = await fetch('/create-payment-intent', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (!response.ok || !data || data.error || !data.clientSecret) {
            throw new Error(data?.error || 'Unable to initialize Stripe payment options.');
        }

        stripeElements = stripeClient.elements({
            clientSecret: data.clientSecret,
            appearance: { theme: 'stripe' }
        });
        stripePaymentElement = stripeElements.create('payment', {
            layout: { type: 'tabs' }
        });
        stripePaymentElement.mount('#stripe-payment-element');
    } finally {
        stripeInitializing = false;
    }
}

function showStripeError(message) {
    const stripeError = document.getElementById('stripePaymentError');
    if (!stripeError) return;
    stripeError.textContent = message;
    stripeError.classList.remove('hidden');
}

function clearStripeError() {
    const stripeError = document.getElementById('stripePaymentError');
    if (!stripeError) return;
    stripeError.textContent = '';
    stripeError.classList.add('hidden');
}

document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const phoneInput = this.querySelector('input[name="phone"]');
    if (phoneInput && !/^\d+$/.test(phoneInput.value)) {
        alert('Please enter a valid contact number (numbers only).');
        phoneInput.focus();
        e.preventDefault();
        return false;
    }
    
    // prevent double submit
    if (form.dataset.submitting === '1') return;

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    if (!validateDeliverySelection()) {
        return;
    }

    const paymentChecked = document.querySelector('input[name="payment"]:checked');
    if (!paymentChecked) {
        alert('Please select a payment option.');
        return;
    }

    if (paymentChecked.value === 'card') {
        clearStripeError();
        const submitBtn = form.querySelector('button[type="submit"]');
        const overlay = document.getElementById('loadingOverlay');

        try {
            if (form.dataset.submitting === '1') return;
            form.dataset.submitting = '1';
            if (submitBtn) {
                submitBtn.setAttribute('disabled', 'disabled');
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = 'Processing payment…';
            }
            if (overlay) overlay.classList.remove('hidden');

            await initializeStripePaymentElement(form);

            if (!stripeElements) {
                throw new Error('Stripe payment form is not ready yet.');
            }

            const result = await stripeClient.confirmPayment({
                elements: stripeElements,
                confirmParams: {
                    return_url: `${window.location.origin}/stripe-return`
                },
                redirect: 'if_required'
            });

            if (result.error) {
                throw new Error(result.error.message || 'Stripe payment failed.');
            }

            if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                const finalizeResponse = await fetch('/stripe-finalize-payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ payment_intent_id: result.paymentIntent.id })
                });
                const finalizeData = await finalizeResponse.json();
                if (!finalizeResponse.ok || !finalizeData || finalizeData.error) {
                    throw new Error(finalizeData?.error || 'Payment succeeded, but order finalization failed.');
                }
                window.location.href = finalizeData.redirectUrl || '/stripe-return';
                return;
            }
        } catch (err) {
            console.error('Stripe payment failed', err);
            showStripeError(err.message || 'Stripe payment failed.');
            alert(err.message || 'Stripe payment failed.');
        } finally {
            form.dataset.submitting = '0';
            if (submitBtn) {
                submitBtn.removeAttribute('disabled');
                submitBtn.innerHTML = submitBtn.dataset.originalText || 'Confirm Order';
            }
            if (overlay) overlay.classList.add('hidden');
        }

        return;
    }


    // Validate pickup/return datetime
    const pickupVal = document.getElementById('pickupDatetimeCheckout').value;
    const returnVal = document.getElementById('returnDatetimeCheckout').value;
    if (!pickupVal || !returnVal) {
        alert('Please select both pickup and return date & time.');
        return;
    }

    const pickupDate = new Date(String(pickupVal).replace(' ', 'T'));
    const returnDate = new Date(String(returnVal).replace(' ', 'T'));
    const now = new Date();
    now.setSeconds(0, 0);
    const minutes = now.getMinutes();
    const remainder = minutes % 15;
    if (remainder !== 0) {
        now.setMinutes(minutes + (15 - remainder));
    }

    if (isNaN(pickupDate) || isNaN(returnDate)) {
        alert('Invalid pickup/return date selected. Please select your dates again.');
        localStorage.removeItem('pickupDatetime');
        localStorage.removeItem('returnDatetime');
        document.getElementById('pickupDatetimeCheckout').value = '';
        document.getElementById('returnDatetimeCheckout').value = '';
        return;
    }

    if (pickupDate < now || returnDate < now) {
        alert('Past dates are not allowed. Please select valid pickup and return dates again.');
        localStorage.removeItem('pickupDatetime');
        localStorage.removeItem('returnDatetime');
        document.getElementById('pickupDatetimeCheckout').value = '';
        document.getElementById('returnDatetimeCheckout').value = '';
        return;
    }

    if (returnDate <= pickupDate) {
        alert('Return date/time must be after pickup date/time. Please select your dates again.');
        localStorage.removeItem('pickupDatetime');
        localStorage.removeItem('returnDatetime');
        document.getElementById('pickupDatetimeCheckout').value = '';
        document.getElementById('returnDatetimeCheckout').value = '';
        return;
    }

    const rentalDays = Math.ceil((returnDate - pickupDate) / (1000 * 60 * 60 * 24));
    if (rentalDays > 31) {
        alert('Online booking is limited to 31 days. For rentals longer than 31 days, please call us.');
        localStorage.removeItem('pickupDatetime');
        localStorage.removeItem('returnDatetime');
        document.getElementById('pickupDatetimeCheckout').value = '';
        document.getElementById('returnDatetimeCheckout').value = '';
        return;
    }

    // mark submitting and show overlay + disable submit button
    form.dataset.submitting = '1';
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.setAttribute('disabled', 'disabled');
        submitBtn.dataset.originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = 'Processing…';
    }
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.remove('hidden');

    const formData = new FormData(form);
    formData.append('cart', localStorage.getItem('cart') || '[]');
    formData.append('customer_type', <?= $user ? "'user'" : "'guest'" ?>);
    formData.append('sale_type', 'rental');
    formData.append("csrf_token", document.querySelector('input[name="csrf_token"]').value);


    fetch('/checkout', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(async res => {
        if (overlay) overlay.classList.add('hidden');
        form.dataset.submitting = '0';
        if (submitBtn) {
            submitBtn.removeAttribute('disabled');
            submitBtn.innerHTML = submitBtn.dataset.originalText || 'Confirm Order';
        }
        if (res.headers.get('content-type')?.includes('application/json')) {
            const data = await res.json();
            
            console.log('[DEBUG] COD order response:', data);
            if (data.success && data.cod && data.order) {
                // Show confirmation dynamically
                localStorage.removeItem('cart');
                localStorage.removeItem('pickupDatetime');
                localStorage.removeItem('returnDatetime');
                localStorage.removeItem('rentalDatesInitialized');
                console.log('[DEBUG] Cart cleared after order.');
                // Fill confirmation fields
                const o = data.order;
                let confName = '';
                if (o.guest_first_name || o.guest_last_name) {
                    confName = (o.guest_first_name || '') + (o.guest_last_name ? ' ' + o.guest_last_name : '');
                } else if (o.guest_name) {
                    confName = o.guest_name;
                } else {
                    confName = '—';
                }
                document.getElementById('confName').textContent = confName;
                document.getElementById('confPhone').textContent = o.guest_phone || '—';
                document.getElementById('confEmail').textContent = o.guest_email || '—';
                document.getElementById('confPickup').textContent = o.pickup_datetime 
                    ? new Date(o.pickup_datetime).toLocaleString() : '—';
                document.getElementById('confReturn').textContent = o.return_datetime 
                    ? new Date(o.return_datetime).toLocaleString() : '—';
                let deliveryLabel = 'Deliver to Preferred Address';
                document.getElementById('confHotelRow').style.display = 'none';
                document.getElementById('confPickupLocationRow').style.display = 'none';
                if (o.delivery_type === 'hotel') {
                    deliveryLabel = 'Deliver to Partner Hotel';
                    document.getElementById('confHotelRow').style.display = 'flex';
                    document.getElementById('confHotelName').textContent = o.hotel_name || 'Selected Hotel';
                } else if (o.delivery_type === 'pickup') {
                    deliveryLabel = 'Pickup at Store';
                    document.getElementById('confPickupLocationRow').style.display = 'flex';
                    document.getElementById('confPickupLocation').textContent = o.pickup_location_name || 'Selected Store';
                }
                document.getElementById('confDeliveryType').textContent = deliveryLabel;
                document.getElementById('confAddress1').textContent = o.address1 || '—';
                document.getElementById('confAddress2').textContent = o.address2 || '—';
                document.getElementById('confState').textContent = o.state || '—';
                document.getElementById('confZip').textContent = o.zip || '—';
                const paymentLabels = { cod: 'Cash on Delivery', card: 'Card', paypal: 'PayPal' };
                document.getElementById('confPayment').textContent = paymentLabels[o.payment_method] || o.payment_method;
                document.getElementById('checkoutForm')?.classList.add('hidden');
                document.getElementById('orderConfirmation')?.classList.remove('hidden');
                console.log('[DEBUG] orderConfirmation section displayed.');
                if (data.emailSent !== undefined) {
                    console.log('[DEBUG] Email sent status:', data.emailSent);
                }
                window.scrollTo(0, 0);
                return;
            }
        }
        // fallback: redirect if not JSON (e.g. Stripe/PayPal)
        if (res.status === 200 || res.redirected) {
            window.location.href = res.url || window.location.href;
        } else {
            alert('Order failed. Please try again.');
        }
    })
    .catch(err => {
        console.error(err);
        if (overlay) overlay.classList.add('hidden');
        form.dataset.submitting = '0';
        alert('Network error. Please try again.');
    });
    // .catch((err) => {
    //     console.error(err);
    //     if (overlay) overlay.classList.add('hidden');
    //     form.dataset.submitting = '0';
    //     alert('Order failed. Please try again.');
    //     if (submitBtn) {
    //         submitBtn.removeAttribute('disabled');
    //         submitBtn.innerHTML = submitBtn.dataset.originalText || 'Confirm Order';
    //     }
    // });
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="payment"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const paypalContainer = document.getElementById('paypal-button-container');
            const stripeWrapper = document.getElementById('stripe-payment-wrapper');
            const submitBtn = document.getElementById('checkoutForm').querySelector('button[type="submit"]');
            
            if (this.value === 'paypal') {
                if (stripeWrapper) stripeWrapper.classList.add('hidden');
                paypalContainer.style.display = 'block';
                submitBtn.style.display = 'none';
                if (!paypalContainer.dataset.rendered) {
                    renderPayPalButton();
                    paypalContainer.dataset.rendered = 'true';
                }
            } else if (this.value === 'card') {
                paypalContainer.style.display = 'none';
                submitBtn.style.display = 'block';
                if (stripeWrapper) stripeWrapper.classList.remove('hidden');
                initializeStripePaymentElement(document.getElementById('checkoutForm')).catch(err => {
                    console.error('Stripe initialization failed', err);
                    showStripeError(err.message || 'Unable to initialize Stripe payment options.');
                });
            } else {
                paypalContainer.style.display = 'none';
                submitBtn.style.display = 'block';
                if (stripeWrapper) stripeWrapper.classList.add('hidden');
            }
        });
    });
});

<?php if (!$user): ?>
document.getElementById('clearContactBtn').addEventListener('click', function() {
    const section = document.getElementById('contactInfoSection');
    section.querySelectorAll('input').forEach(input => input.value = '');
});
<?php endif; ?>


document.addEventListener('DOMContentLoaded', function() {
    // Set hidden fields for pickup/return datetime from localStorage
    const pickup = localStorage.getItem('pickupDatetime') || '';
    const ret = localStorage.getItem('returnDatetime') || '';
    document.getElementById('pickupDatetimeCheckout').value = pickup;
    document.getElementById('returnDatetimeCheckout').value = ret;
    renderSelectedDatesSummary();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    

// Example function to show a result to the user. Your site's UI library can be used instead.
function resultMessage(message) {
    const container = document.querySelector("#result-message");
    container.innerHTML = message;
}


// DETECT IF PAYPAL IS SELECTED
document.querySelectorAll('input[name="payment"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const paypalContainer = document.getElementById('paypal-button-container');
        const stripeWrapper = document.getElementById('stripe-payment-wrapper');
        const submitBtn = document.getElementById('checkoutForm').querySelector('button[type="submit"]');
        
        if (this.value === 'paypal') {
            if (stripeWrapper) stripeWrapper.classList.add('hidden');
            paypalContainer.style.display = 'block';
            submitBtn.style.display = 'none';
            if (!paypalContainer.dataset.rendered) {
                renderPayPalButton();
                paypalContainer.dataset.rendered = 'true';
            }
        } else if (this.value === 'card') {
            paypalContainer.style.display = 'none';
            submitBtn.style.display = 'block';
            if (stripeWrapper) stripeWrapper.classList.remove('hidden');
        } else {
            paypalContainer.style.display = 'none';
            submitBtn.style.display = 'block';
            if (stripeWrapper) stripeWrapper.classList.add('hidden');
        }
    });
});

function renderPayPalButton() {
    window.paypal.Buttons({
        style: {
            shape: 'rect',
            layout: 'vertical',
            color: 'gold',
            label: 'paypal',
        },
        
        async createOrder() {

            // Validate delivery selection (hotel or pickup store required)
            if (!validateDeliverySelection()) {
                throw new Error('Delivery selection invalid');
            }

            // Check if policy checkbox is ticked
            const policyCheckbox = document.querySelector('input[name="agree_policy"]');
            if (!policyCheckbox || !policyCheckbox.checked) {
                alert('You must agree to the rental policy and terms before proceeding.');
                throw new Error('Policy not agreed');
            }

            const form = document.getElementById('checkoutForm');
            const formData = new FormData(form);

            // Save form data to session before creating PayPal order
            const saveFormResponse = await fetch('/save-checkout-form', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            if (!saveFormResponse.ok) {
                alert('Could not save checkout form data. Please try again.');
                throw new Error('Failed to save checkout form data');
            }
            // IMPLEMENT TO SAVE A USER'S CART WHEN I LEAVE MY CHECKOUT TEMPORARILY.
            
            // Create PayPal order
            try {
                const response = await fetch('/api/orders', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cart: loadCart().map(item => ({
                            id: item.id,
                            name: item.name,
                            quantity: item.qty,
                            price: item.price,
                            variation_id: (typeof item.variation_id !== 'undefined') ? item.variation_id : null,
                            variation_name: (typeof item.variation_name !== 'undefined') ? item.variation_name : null,
                            image_url: (typeof item.image_url !== 'undefined') ? item.image_url : null,
                            type: item.type,
                            sale_type: item.sale_type
                        }))
                    })
                });

                const orderData = await response.json();
                if (orderData.id) return orderData.id;
                
                throw new Error(orderData.error || 'Could not create order');
            } catch (error) {
                console.error(error);
                alert('Could not initiate PayPal Checkout: ' + error.message);
            }
        },
        
    async onApprove(data, actions) {
        try {
            const response = await fetch(`/api/orders/${data.orderID}/capture`, { method: 'POST' });
            const result = await response.json();
            
            if (result.status === 'COMPLETED' || result.status === 'APPROVED') {
                window.location.href = '/paypal-return'; // This triggers our new secure redirect
            }
        } catch (err) {
            alert('Payment failed: ' + err.message);
        }
    }
    }).render('#paypal-button-container');
}

// PHONE VALIDATION — FORCED TO RUN AND WORK
document.addEventListener('DOMContentLoaded', function () {
    console.log('%cPHONE VALIDATION SCRIPT IS LOADED AND RUNNING', 'color: lime; font-size: 16px; font-weight: bold;');

    const phoneInput = document.querySelector('input[name="phone"]');
    const phoneWarning = document.getElementById('phoneWarning');

    if (!phoneInput) {
        console.error('Phone input not found!');
        return;
    }

    // Clean input as user types
    phoneInput.addEventListener('input', function(e) {
        const original = this.value;
        const cleaned = original.replace(/\D/g, ''); // remove all non-digits

        if (original !== cleaned) {
            this.value = cleaned;
            console.log('Auto-cleaned phone input:', original, '→', cleaned);
        }

        // Show/hide warning
        if (cleaned === '' || /^\d+$/.test(cleaned)) {
            phoneWarning.classList.add('hidden');
        } else {
            phoneWarning.classList.remove('hidden');
        }
    });

    // Also handle paste
    phoneInput.addEventListener('paste', function(e) {
        setTimeout(() => {
            this.value = this.value.replace(/\D/g, '');
        }, 10);
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const hotelRadio = document.getElementById('deliveryHotel');
    const pickupRadio = document.getElementById('deliveryPickup');
    const hotelDropdown = document.getElementById('hotelDropdown');
    const hotelSelect = hotelDropdown.querySelector('select[name="hotel_id"]');
    const addressFields = document.getElementById('addressFields');
    const address1 = addressFields.querySelector('input[name="address1"]');
    const address2 = addressFields.querySelector('input[name="address2"]');
    const state = addressFields.querySelector('input[name="state"]');
    const zip = addressFields.querySelector('input[name="zip"]');
    const pickupSection = document.getElementById('pickupLocationSection');
    const pickupSelect = pickupSection.querySelector('select[name="pickup_location"]');

    function clearAddressFields() {
        address1.value = '';
        address2.value = '';
        state.value = '';
        zip.value = '';
    }

    function setAddressFieldsReadonly(isReadonly) {
        address1.readOnly = isReadonly;
        address2.readOnly = isReadonly;
        state.readOnly = isReadonly;
        zip.readOnly = isReadonly;
    }

    function toggleDeliveryOptions() {
        if (hotelRadio.checked) {
            hotelDropdown.classList.remove('hidden');
            addressFields.classList.remove('hidden');
            setAddressFieldsReadonly(true);
            pickupSection.style.display = 'none';
            if (hotelSelect) hotelSelect.required = true;
            if (pickupSelect) {
                pickupSelect.required = false;
                pickupSelect.value = '';
            }
        } else if (pickupRadio.checked) {
            hotelDropdown.classList.add('hidden');
            addressFields.classList.add('hidden');
            pickupSection.style.display = 'block';
            clearAddressFields();
            if (hotelSelect) {
                hotelSelect.required = false;
                hotelSelect.value = '';
            }
            if (pickupSelect) pickupSelect.required = true;
        }
    }

    hotelRadio.addEventListener('change', toggleDeliveryOptions);
    pickupRadio.addEventListener('change', toggleDeliveryOptions);

    // Populate address fields when hotel is selected
    hotelSelect.addEventListener('change', function() {
        const selected = hotelSelect.options[hotelSelect.selectedIndex];
        address1.value = selected.getAttribute('data-address1') || '';
        address2.value = selected.getAttribute('data-address2') || '';
        zip.value = selected.getAttribute('data-zip') || '';
        state.value = selected.getAttribute('data-state') || '';
    });

    // Initial state
    toggleDeliveryOptions();

    function updateAddressRequired() {
        const deliveryType = document.querySelector('input[name="delivery_type"]:checked')?.value;
        const address1 = document.querySelector('input[name="address1"]');
        const state = document.querySelector('input[name="state"]');
        const zip = document.querySelector('input[name="zip"]');
        if (deliveryType === 'pickup') {
            if (address1) address1.required = false;
            if (state) state.required = false;
            if (zip) zip.required = false;
        } else {
            if (address1) address1.required = true;
            if (state) state.required = true;
            if (zip) zip.required = true;
        }

        if (deliveryType === 'hotel') {
            if (hotelSelect) hotelSelect.required = true;
            if (pickupSelect) pickupSelect.required = false;
        } else if (deliveryType === 'pickup') {
            if (hotelSelect) hotelSelect.required = false;
            if (pickupSelect) pickupSelect.required = true;
        }
    }

    // Run on page load
    updateAddressRequired();

    // Run whenever delivery type changes
    document.querySelectorAll('input[name="delivery_type"]').forEach(function(radio) {
        radio.addEventListener('change', updateAddressRequired);
    });
});

</script>

<script>
    function openPolicyModal() {
        document.getElementById('policyModal').classList.remove('hidden');
        // Sync modal checkbox with main checkbox
        document.getElementById('modalAgreeCheckbox').checked = document.querySelector('input[name="agree_policy"]').checked;
    }

    function closePolicyModal() {
        document.getElementById('policyModal').classList.add('hidden');
    }

    // When "I Agree" button is clicked in modal, tick main checkbox and close modal
    function agreeAndClosePolicyModal() {
        document.querySelector('input[name="agree_policy"]').checked = true;
        document.getElementById('policyModal').classList.add('hidden');
    }

    // Also, if modal checkbox is ticked, tick main checkbox
    // document.getElementById('modalAgreeCheckbox').addEventListener('change', function() {
    //     document.querySelector('input[name="agree_policy"]').checked = this.checked;
    // });

    document.getElementById('policyModal').addEventListener('click', function(e) {
    // Only close if the click is on the overlay, not inside the modal content
    if (e.target === this) {
        closePolicyModal();
    }
});
</script>

<script>
    <?php if ($showConfirmation && $order): ?>
        document.addEventListener('DOMContentLoaded', () => {
            const o = <?= json_encode($order, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
            console.log('Order confirmation data:', o);

            let confName = '';
            if (o.guest_first_name || o.guest_last_name) {
                confName = (o.guest_first_name || '') + (o.guest_last_name ? ' ' + o.guest_last_name : '');
            } else if (o.guest_name) {
                confName = o.guest_name;
            } else {
                confName = '—';
            }
            document.getElementById('confName').textContent = confName;
            document.getElementById('confPhone').textContent = o.guest_phone || '—';
            document.getElementById('confEmail').textContent = o.guest_email || '—';
            document.getElementById('confPickup').textContent = o.pickup_datetime 
                ? new Date(o.pickup_datetime).toLocaleString() : '—';
            document.getElementById('confReturn').textContent = o.return_datetime 
                ? new Date(o.return_datetime).toLocaleString() : '—';

            let deliveryLabel = 'Deliver to Preferred Address';
            document.getElementById('confHotelRow').style.display = 'none';
            document.getElementById('confPickupLocationRow').style.display = 'none';

            if (o.delivery_type === 'hotel') {
                deliveryLabel = 'Deliver to Partner Hotel';
                document.getElementById('confHotelRow').style.display = 'flex';
                document.getElementById('confHotelName').textContent = o.hotel_name || 'Selected Hotel';
            } else if (o.delivery_type === 'pickup') {
                deliveryLabel = 'Pickup at Store';
                document.getElementById('confPickupLocationRow').style.display = 'flex';
                document.getElementById('confPickupLocation').textContent = o.pickup_location_name || 'Selected Store';
            }

            document.getElementById('confDeliveryType').textContent = deliveryLabel;
            document.getElementById('confAddress1').textContent = o.address1 || '—';
            document.getElementById('confAddress2').textContent = o.address2 || '—';
            document.getElementById('confState').textContent = o.state || '—';
            document.getElementById('confZip').textContent = o.zip || '—';

            const paymentLabels = { cod: 'Cash on Delivery', card: 'Card', paypal: 'PayPal' };
            document.getElementById('confPayment').textContent = paymentLabels[o.payment_method] || o.payment_method;

            document.getElementById('checkoutForm')?.classList.add('hidden');
            document.getElementById('orderConfirmation')?.classList.remove('hidden');
            document.getElementById('loadingOverlay')?.classList.add('hidden');

            localStorage.removeItem('cart');
            localStorage.removeItem('pickupDatetime');
            localStorage.removeItem('returnDatetime');
            localStorage.removeItem('rentalDatesInitialized');

            window.scrollTo(0, 0);
        });
    <?php endif; ?>
    

</script>

<script>
// FINAL FIX: Allow editing even for logged-in users + force number-only
document.addEventListener('DOMContentLoaded', function () {
    const phoneInput = document.getElementById('phoneInput');
    if (!phoneInput) return;

    // If user is logged in and field was readonly, make it editable but pre-filled
    if (phoneInput.hasAttribute('readonly')) {
        phoneInput.removeAttribute('readonly');
        phoneInput.style.backgroundColor = '#fff'; // optional: make it look editable
    }

    // Force numbers only + auto-clean
    phoneInput.addEventListener('input', function () {
        const cleaned = this.value.replace(/\D/g, '');
        if (this.value !== cleaned) {
            this.value = cleaned;
        }
    });

    phoneInput.addEventListener('paste', function () {
        setTimeout(() => {
            this.value = this.value.replace(/\D/g, '');
        }, 0);
    });
});
</script>


