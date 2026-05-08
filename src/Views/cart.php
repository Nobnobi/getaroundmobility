<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\cart.php -->
<?php
$isGuest = empty($_SESSION['user_id']);
?>

    
    <!-- PICKUP & RETURN DATE FORM -->
    <div class="mt-20 max-w-7xl mx-auto px-4">
        <?php include __DIR__ . '/partials/date-form.php'; ?>
    </div>
    <!-- TOTAL SUMMARY -->
    <div class="container mx-auto px-2 py-8 flex-1">
        <h1 class="text-2xl font-bold mb-6 font-[Barlow]">Cart</h1>
        <div class="flex flex-col lg:grid lg:grid-cols-3 gap-8">
            <!-- Left Column: Cart Items -->
            <div class="lg:col-span-2 space-y-4 order-2 lg:order-1" id="cartPageItems">
                <!-- JS will render cart items here -->
            </div>
            <!-- Right Column: Order Summary -->
            <div class="bg-white shadow rounded-lg p-6 h-fit order-1 lg:order-2 mb-6 lg:mb-0" id="cartSummary">
                <!-- JS will render summary here -->
            </div>
        </div>
    </div>

    <!-- Rent Now Modal (only for guests) -->
    <?php if ($isGuest): ?>
    <div id="rentNowModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
      <!-- Overlay -->
      <div class="absolute inset-0 bg-black opacity-50"></div>
      <!-- Modal content -->
      <div class="relative bg-white rounded-lg shadow-lg w-full max-w-xs sm:max-w-md p-8 z-10 font-[Barlow]">
        <button id="closeModalBtn" class="absolute top-3 right-3 text-gray-400 hover:text-black text-2xl font-bold cursor-pointer">&times;</button>
        <h2 class="text-xl font-bold mb-2 text-center">Would you like to continue as a guest or log in?</h2>
        <p class="text-gray-600 mb-6 text-center">If you have an account, log in for faster checkout.</p>
        <div class="flex gap-4">
          <a href="/checkout?guest" class="flex-1 py-2 rounded border border-gray-300 text-center bg-white text-gray-800 font-semibold hover:bg-gray-100">Continue as a Guest</a>
          <a href="/login" class="flex-1 py-2 rounded bg-[#0086C9] text-white text-center font-semibold hover:bg-blue-700">Log in</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    

<script>   
    
    
    function renderCartPage() {
        const cart = loadCart();
        const itemsContainer = document.getElementById('cartPageItems');
        const summaryContainer = document.getElementById('cartSummary');
        if (!itemsContainer || !summaryContainer) return;
        if (cart.length === 0) {
            itemsContainer.innerHTML = '<p class="text-gray-500 font-[Barlow]">Your cart is empty.</p>';
            summaryContainer.innerHTML = '';
            summaryContainer.classList.add('hidden');
            updateCartCountBadge();
            return;
        } else {
            summaryContainer.classList.remove('hidden');
        }
        // Render cart items (left column)
        let itemsHtml = '';
        let subtotal = 0;
        cart.forEach((item, idx) => {
            const lineTotal = item.price * item.qty;
            subtotal += lineTotal;
            // Extract variation from name if present (format: Product - Variation)
            let name = item.name;
            let variation = '';
            if (name.includes(' - ')) {
                const parts = name.split(' - ');
                name = parts[0];
                variation = parts.slice(1).join(' - ');
            }
            itemsHtml += `
            <div class="flex flex-col sm:flex-row items-center justify-between bg-white shadow rounded-lg p-4 gap-4">
                <div class="flex items-center space-x-4 w-full sm:w-auto">
                    <img src="${item.image_url && item.image_url.trim() !== '' ? item.image_url : '/img/placeholder.png'}"
                        alt="${name}"
                        class="w-24 h-24 object-cover rounded border border-gray-200 bg-gray-100">
                    <div class="w-56 overflow-hidden whitespace-nowrap text-ellipsis">
                        <h2 class="font-semibold text-lg" title="${name}">
                            ${name.length > 25 ? name.substring(0, 25) + '…' : name}
                        </h2>
                        ${variation ? `<span class='block text-xs text-blue-600 font-semibold mt-1 px-2 py-0.5 bg-blue-50 border border-blue-300 rounded-full w-fit mb-1'>${variation}</span>` : ''}
                    </div>
                </div>
                <div class="flex flex-row items-center gap-2">
                    <div class="flex items-center gap-1">
                        

                        <!-- QUANTITY -->
                        <input
                            type="text"
                            readonly
                            value="${item.qty}"
                            class="w-12 h-8 text-center 
                                focus:outline-none focus:ring-2 focus:ring-blue-500
                                bg-white text-gray-900 font-medium">

                       
                    </div>
                    <!-- DELETE BUTTON -->
                    <button class="remove-cart-page-item cursor-pointer ml-2" data-idx="${idx}" title="Remove">
                        <img src="/img/delete_grey.png" alt="Delete" class="w-6 h-6 inline-block align-middle">
                    </button>
                </div>
                <div class="text-right font-semibold w-full sm:w-auto">
                    $${(lineTotal).toFixed(2)}
                </div>
            </div>`;
        });
        itemsContainer.innerHTML = itemsHtml;

        // Remove item handler
        document.querySelectorAll('.remove-cart-page-item').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.dataset.idx);
                let cart = loadCart();
                cart.splice(idx, 1);
                saveCart(cart);
                renderCartPage();
                renderCart(); // update dropdown
                updateCartCountBadge();
            });
        });

        // Calculate summary (right column)
        const tax = subtotal * 0.12; // Example: 12% tax
        const total = subtotal + tax;

        summaryContainer.innerHTML = `
            <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
            <div class="flex justify-between mb-2">
                <span>Rental subtotal</span>
                <span>$${subtotal.toFixed(2)}</span>
            </div>
            <div class="flex justify-between mb-2">
                <span>Tax included</span>
                <span>$${tax.toFixed(2)}</span>
            </div>
            <div class="flex justify-between mb-4">
                <span>Security deposit included</span>
                <span>-</span>
            </div>
            <div class="flex justify-between font-bold text-lg mb-6">
                <span>Total</span>
                <span>$${total.toFixed(2)}</span>
            </div>
            <button type="button" class="bg-[#0086C9] text-white w-full py-3 cursor-pointer rounded-lg hover:bg-blue-600 rent-now-btn">
                Rent now
            </button>
        `;
        updateCartCountBadge();
    }

    // Quantity update handler
    function updateQty(idx, delta) {
        let cart = loadCart();
        const item = cart[idx];
        if (!item) return;

        // Only allow increment if under stock limit
        if (delta > 0 && item.qty >= item.scooter_count) {
            alert('You cannot add more than the available stock for this product.');
            return;
        }

        item.qty += delta;
        if (item.qty < 1) item.qty = 1;
        saveCart(cart);
        renderCartPage();
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderCartPage();
        renderCart();
        updateCartCountBadge();


        // Rent now button logic
        document.addEventListener('click', function(e) {
            const rentNowBtn = e.target.closest('.rent-now-btn');
            if (rentNowBtn) {
                // Date validation
                const pickup = document.getElementById('pickupDatetime')?.value;
                const ret = document.getElementById('returnDatetime')?.value;
                if (!pickup || !ret) {
                    const msg = document.getElementById('formMessage');
                    if (msg) {
                        msg.classList.remove('hidden');
                        setTimeout(() => msg.classList.add('hidden'), 3000);
                    } else {
                        alert('Please select both Pickup/Delivery and Return date & time before proceeding.');
                    }
                    if (typeof emphasizeRentalForm === 'function') emphasizeRentalForm();
                    return; // Prevent proceeding
                }

                <?php if ($isGuest): ?>
                    document.getElementById('rentNowModal').classList.remove('hidden');
                <?php else: ?>
                    window.location.href = '/checkout';
                <?php endif; ?>
            }
        });

        <?php if ($isGuest): ?>
        // Close modal on close button or clicking outside modal content
        document.getElementById('closeModalBtn').addEventListener('click', function() {
            document.getElementById('rentNowModal').classList.add('hidden');
        });
        document.getElementById('rentNowModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
        <?php endif; ?>
    });

    document.addEventListener('DOMContentLoaded', function() {
        const pickupInput = document.getElementById('pickupDatetime');
        const returnInput = document.getElementById('returnDatetime');
        if (pickupInput && localStorage.getItem('pickupDatetime')) {
            pickupInput.value = localStorage.getItem('pickupDatetime');
        }
        if (returnInput && localStorage.getItem('returnDatetime')) {
            returnInput.value = localStorage.getItem('returnDatetime');
        }
    });


</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    