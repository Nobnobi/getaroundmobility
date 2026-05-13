</div>

<!-- Mobile Search Modal Overlay & Bar -->
<div id="mobileSearchOverlay" class="fixed inset-0 z-[100] bg-black/40 hidden opacity-0 transition-opacity duration-300 md:hidden"></div>
<div id="mobileSearchBarWrap" class="fixed top-0 left-0 w-full z-[110] flex items-center px-4 py-3 bg-white shadow-lg hidden opacity-0 -translate-y-8 transition-all duration-300 md:hidden">
    <form id="mobileSearchForm" action="/search" method="get" class="flex items-center w-full gap-2">
        <input type="text" name="q" id="mobileSearchInput" class="flex-1 border border-[#0086C9] rounded-full px-4 py-2 text-base font-[Barlow] focus:ring-2 focus:ring-blue-200 focus:border-blue-400 outline-none bg-white placeholder-gray-400" placeholder="Search products..." autocomplete="off" />
        <button type="button" id="mobileSearchCancel" class="ml-2 text-[#0086C9] font-semibold text-base">Cancel</button>
    </form>
</div>

<!-- MOBILE NAVBAR TOP BAR -->
<div class="md:hidden fixed top-0 left-0 w-full z-[80] bg-[#0086C9] shadow-sm flex items-center h-14">
    <div class="flex-1 flex justify-start">
        <button id="burgerBtn" class="ml-2 p-2 rounded cursor-pointer">
            <img src="/img/burgerNav.svg" class="w-7 h-7" alt="Menu" />
        </button>
    </div>
    <div class="flex-none flex justify-center absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-fit">
        <a href="/">
            <img src="/img/Original logo.png" alt="Logo" class="h-8" style="max-height:2.5rem;" />
        </a>
    </div>
    <div class="flex-1 flex justify-end items-center gap-1 pr-2">
        <button id="mobileSearchIcon" class="relative p-2">
            <img src="/img/search-white.svg" alt="Search" class="w-7 h-7" />
        </button>
        <button id="mobileCartIcon" class="relative p-2">
            <img src="/img/shoppingcart_white2.svg" alt="Cart" class="w-7 h-7" />
            <span id="mobileCartCount" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1.5 min-w-[20px] text-center hidden"></span>
        </button>
    </div>
</div>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$sessionFirstName = trim((string)($_SESSION['first_name'] ?? ''));
$sessionLastName = trim((string)($_SESSION['last_name'] ?? ''));
$sessionFullName = trim((string)($_SESSION['name'] ?? ''));

if (!empty($_SESSION['user_id']) && $sessionFirstName === '' && $sessionLastName === '' && $sessionFullName === '') {
    try {
        $pdo = \App\Utils\Database::getInstance();
        $stmt = $pdo->prepare('SELECT first_name, last_name FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $sessionFirstName = trim((string)($row['first_name'] ?? ''));
        $sessionLastName = trim((string)($row['last_name'] ?? ''));
        $sessionFullName = trim($sessionFirstName . ' ' . $sessionLastName);

        $_SESSION['first_name'] = $sessionFirstName;
        $_SESSION['last_name'] = $sessionLastName;
        $_SESSION['name'] = $sessionFullName;
    } catch (\Throwable $e) {
        // Keep navbar render resilient if DB lookup fails.
    }
}

$displayFirstName = $sessionFirstName !== ''
    ? $sessionFirstName
    : (($sessionFullName !== '' && strpos($sessionFullName, ' ') !== false)
        ? strtok($sessionFullName, ' ')
        : $sessionFullName);

$displayFullName = trim($sessionFirstName . ' ' . $sessionLastName);
if ($displayFullName === '') {
    $displayFullName = $sessionFullName;
}
?>

<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\navbar.php -->



<!-- Mobile Cart List -->
<div id="mobileCartList" class="fixed top-16 right-4 z-[90] bg-white shadow-lg rounded-lg p-4 w-80 border border-gray-200 md:hidden hidden">
    
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold text-lg">Cart</h3>
        <button id="closeMobileCart" class="cursor-pointer text-gray-500 hover:text-black" title="Close">
            <img src="/img/close_grey.png" alt="Close" class="w-5 h-5" />
        </button>
    </div>
    <ul id="mobileCartItems" class="mb-2 max-h-80 overflow-y-auto"></ul>
    <div id="mobileCartTotalRow" class="flex justify-between font-bold border-t pt-2 mt-2"></div>
    <div class="flex gap-2">
        <a href="/cart" class="flex-1 text-center bg-[#0086C9] text-white rounded py-2">Go To Cart</a>
        <a href="/checkout" class="flex-1 text-center bg-blue-600 text-white rounded py-2">Checkout</a>
    </div>
</div>

<div id="mobileMenu" class="fixed top-0 left-0 h-full w-64 bg-white shadow-xl z-[80] flex flex-col justify-start -translate-x-full transition-transform duration-300 md:hidden">
    <div class="p-6 flex flex-col space-y-4 font-[Barlow] text-lg">
        <!-- Logo and Home Button -->
        <div class="flex items-center mb-4">
            <a href="/" class="mr-2">
                <img src="/img/Original logo.png" alt="Logo" class="h-8">
            </a>
        </div>
        <a href="/" class="text-[#0086C9] font-bold">Home</a>
        <a href="/product-list" class="text-gray-700 hover:text-[#0086C9]">Products</a>
        <a href="/for-sale" class="text-gray-700 hover:text-[#0086C9]">For Sale</a>
        <a href="/#rentalForm" class="text-gray-700 hover:text-[#0086C9]">Rent Now</a>
        <a href="/contact" class="text-gray-700 hover:text-[#0086C9]">Contact Us</a>

        <hr class="my-4">

        <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="/profile" class="text-blue-600 font-semibold">
                Hi, <?= htmlspecialchars($displayFullName !== '' ? $displayFullName : 'User') ?>
            </a>
            <a href="/logout" class="text-red-600 font-semibold">Logout</a>
        <?php else: ?>
            <a href="/login" class="text-blue-600 font-semibold">Login</a>
            <a href="javascript:void(0);" onclick="openRegisterModal()" class="h-9 w-22 text-sm font-medium px-4 py-1.5 border border-[#A4A7AE] rounded bg-[#0086C9] text-white shadow-md flex-wrap">Sign Up</a>
        <?php endif; ?>
    </div>
</div>
            
<nav class="fixed top-0 left-0 w-full z-50 bg-white shadow-sm hidden md:block">
  <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between flex-wrap md:flex-nowrap">
    <!-- LOGO + MENU LINK -->
    <div class="flex items-center space-x-6">
        <div class="flex items-center">
            <a href="/" class="hover:text-[#0086C9] text-[#535862]">
                <img src="/img/Original logo.png" alt="Logo" class="h-6 sm:h-7 md:h-8 mr-1 sm:mr-2 min-w-[24px] min-h-[24px]">
            </a>
        </div>
        <div class="flex space-x-1 sm:space-x-2 md:space-x-3 text-xs sm:text-sm md:text-base font-medium font-[Barlow]">
            <a href="/product-list" class="hover:text-[#0086C9] text-[#535862] text-xs sm:text-sm md:text-base px-1 sm:px-2 md:px-3 py-0.5 sm:py-1 transition-all">Products</a>
            <a href="/for-sale" class="hover:text-[#0086C9] text-[#535862] text-xs sm:text-sm md:text-base px-1 sm:px-2 md:px-3 py-0.5 sm:py-1 transition-all">For Sale</a>
            <a href="/#rentalForm" id="rentNowBtn" class="hover:text-[#0086C9] text-[#535862] text-xs sm:text-sm md:text-base px-1 sm:px-2 md:px-3 py-0.5 sm:py-1 transition-all">Rent Now</a>
            <a href="/contact" class="hover:text-[#0086C9] text-[#535862] text-xs sm:text-sm md:text-base px-1 sm:px-2 md:px-3 py-0.5 sm:py-1 transition-all">Contact Us</a>
        </div>
    </div>
    <!-- LOGIN SIGNUP SEARCH -->
    <div class="flex items-center space-x-2 mt-4 md:mt-0">
        <!-- <a href="#" class="h-9 w-22 text-sm font-medium py-1.5 border border-[#A4A7AE] rounded text-center items-center shadow-md">Shop Now</a> -->
        <a href="javascript:void(0);" onclick="openRegisterModal()" class="h-7 sm:h-8 w-16 sm:w-20 text-xs sm:text-sm font-medium px-1.5 sm:px-2 py-0.5 sm:py-1 border border-[#A4A7AE] rounded bg-[#0086C9] text-white shadow-md flex-wrap transition-all text-center">Sign Up</a>
                <form action="/search" method="get" class="flex items-center">
                        <input type="text" name="q" placeholder="Search products..." class="border border-[#0086C9] rounded-full px-1 sm:px-2 md:px-3 py-0.5 sm:py-1 text-xs sm:text-sm font-[Barlow] shadow-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition-all duration-200 outline-none bg-white placeholder-gray-400" />
                        <button type="submit" class="ml-0.5 sm:ml-1 cursor-pointer">
                            <img src="/img/search_grey.png" alt="Search" class="w-4 sm:w-5 h-4 sm:h-5" />
                        </button>
                </form>
        <!-- Navbar Cart Icon and Dropdown -->
        <div class="relative">
            <button id="cartIconBtn" type="button" 
                class="relative flex items-center justify-center h-9 w-9 bg-[#0086C9] rounded text-white shadow-md focus:outline-none cursor-pointer transition-all">
                <img src="/img/shoppingcart_white2.svg" alt="Cart" class="w-5 h-5" />
                <span id="cartCountBadge" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full px-1.5 min-w-[20px] text-center hidden"></span>
            </button>
            <div id="cartList"
                class="absolute right-0 mt-2 z-50 bg-white shadow-lg rounded-lg p-4 w-80 border border-gray-200 hidden">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-lg">Cart</h3>
                    <button id="closeCart" class="cursor-pointer text-gray-500 hover:text-black" title="Close">
                        <img src="/img/close_grey.png" alt="Close" class="w-3.5 sm:w-4 h-3.5 sm:h-4" />
                    </button>
                </div>
                <ul id="cartItems" class="mb-2 max-h-80 overflow-y-auto"></ul>
                <div id="cartTotalRow" class="flex justify-between font-bold border-t pt-2 mt-2"></div>
                <div class="flex gap-2">
                    <a href="/cart" class="flex-1 text-center bg-[#0086C9] text-white rounded py-2">Go To Cart</a>
                    <a href="/checkout" class="flex-1 text-center bg-blue-600 text-white rounded py-2">Checkout</a>
                </div>
            </div>
        </div>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="/profile" class="text-blue-600 font-semibold hover:underline text-[10px] md:text-xs">
                Welcome, <?= htmlspecialchars($displayFirstName !== '' ? $displayFirstName : 'User') ?>
            </a>
            <a href="/logout" class="ml-4 text-red-600 font-semibold hover:underline text-[10px] md:text-xs">Logout</a>
        <?php else: ?>
            <a href="/login?return=/" class="text-blue-600 font-semibold hover:underline">Login</a>
        <?php endif; ?>
    </div>
  </div>
</nav>

<div id="cartToast"
     class="fixed bottom-6 right-6 z-50 bg-green-600 text-white px-4 py-2 rounded shadow-lg opacity-0 pointer-events-none transform translate-y-8 transition-all duration-500">
</div>

<!-- Product Description Modal -->
<div id="productModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-xs sm:max-w-sm md:max-w-2xl p-2 sm:p-4 md:p-8 relative">    
        <button onclick="closeProductModal()" class="cursor-pointer absolute top-2 right-2 text-gray-500 hover:text-black text-2xl">&times;</button>
        <div class="flex flex-col md:flex-row gap-4 md:gap-6">            
            <!-- Image Left -->
            <div class="flex-shrink-0 w-full sm:w-48 md:w-64 flex items-center justify-center">
                <img id="modalProductImage" src="" alt="" class="w-full h-36 sm:h-48 md:h-64 object-contain rounded mb-4 md:mb-0">                
            </div>
            <!-- Info Right -->
            <div class="flex-1 flex flex-col justify-between">
                <div>
                    <h2 id="modalProductName" class="text-lg sm:text-xl md:text-2xl font-bold mb-2 font-[Barlow]"></h2>
                    <span id="modalProductCategory" class="inline-block text-xs sm:text-sm px-2 py-1 mb-2 bg-gray-200 rounded-full text-gray-700 font-[Barlow]"></span>
                    <p id="modalProductDescription" class="text-gray-700 mb-2 sm:mb-3 font-[Barlow] text-xs sm:text-sm md:text-base leading-snug"></p>
                </div>
                <div>
                    <?php if ((!isset($isSearchResultsModal) || !$isSearchResultsModal) && (!isset($isProductListModal) || !$isProductListModal)): ?>
                    <div class="mb-2">
                        <div class="flex flex-col w-full">
                            <span class="text-xs sm:text-sm md:text-base font-semibold tracking-wide text-gray-600 mb-1 uppercase letter-spacing-1">Select Rental Duration</span>
                            <div class="relative">
                                <select id="modalDaysDropdown" class="w-full px-3 py-2 sm:px-4 sm:py-2 rounded-xl border border-gray-300 text-sm sm:text-base font-semibold bg-white focus:outline-none focus:ring-2 focus:ring-[#0086C9] shadow transition-all duration-150 cursor-pointer appearance-none pr-10">
                                    <?php for ($d = 1; $d <= 31; $d++): ?>
                                        <option value="<?= $d ?>">For <?= $d ?> Day<?= $d > 1 ? 's' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="pointer-events-none absolute right-2 sm:right-3 top-1/2 transform -translate-y-1/2">
                                    <img src="/img/arrow-down.svg" alt="▼" class="w-4 h-4 sm:w-5 sm:h-5" />
                                </span>
                            </div>
                        </div>
                    </div>
                    <p id="modalProductPrice" class="text-blue-600 font-bold text-lg mb-4 font-[Barlow]"></p>
                    <p id="modalProductStock" class="text-gray-500 text-xs mb-4 font-[Barlow]"></p>
                    <?php endif; ?>
                    
                    <?php if (!isset($isSearchResultsModal) || !$isSearchResultsModal): ?>
                    <button id="modalRentNowBtn" class="w-full bg-[#0086C9] text-white py-2 rounded hover:bg-blue-700 font-[Barlow] cursor-pointer font-semibold text-base transition-all duration-150">Rent Now</button>
                    <?php endif; ?>
                    <?php if (isset($isSearchResultsModal) && $isSearchResultsModal): ?>
                    <button id="modalAddToCartBtn" class="w-full mt-2 bg-[#0086C9] text-white py-2 rounded hover:bg-blue-700 font-[Barlow] cursor-pointer font-semibold text-base transition-all duration-150">Add to Cart</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PRODUCT LIST MODAL -->
<div id="productListModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-xs sm:max-w-sm md:max-w-2xl p-2 sm:p-4 md:p-8 relative">    
        <button onclick="closeProductListModal()" class="cursor-pointer absolute top-2 right-2 text-gray-500 hover:text-black text-2xl">&times;</button>
        <div class="flex flex-col md:flex-row gap-4 md:gap-6">            
            <!-- Image Left -->
            <div class="flex-shrink-0 w-full sm:w-48 md:w-64 flex items-center justify-center">
                <img id="productListModalImage" src="" alt="" class="w-full h-36 sm:h-48 md:h-64 object-contain rounded mb-4 md:mb-0">                
            </div>
            <!-- Info Right -->
            <div class="flex-1 flex flex-col justify-between">
                <div>
                    <h2 id="productListModalName" class="text-lg sm:text-xl md:text-2xl font-bold mb-2 font-[Barlow]"></h2>
                    <span id="productListModalCategory" class="inline-block text-xs sm:text-sm px-2 py-1 mb-2 bg-gray-200 rounded-full text-gray-700 font-[Barlow]"></span>
                    <p id="productListModalDescription" class="text-gray-700 mb-2 sm:mb-3 font-[Barlow] text-xs sm:text-sm md:text-base leading-snug"></p>                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- For-Sale Product Modal -->
<div id="forSaleProductModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-4 md:max-w-2xl md:p-6 relative">
        <button onclick="closeForSaleProductModal()" class="cursor-pointer absolute top-2 right-2 text-gray-500 hover:text-black text-2xl">&times;</button>
        <div class="flex flex-col md:flex-row gap-4 md:gap-6">
            <!-- Image Left -->
            <div class="flex-shrink-0 w-full md:w-64 flex items-center justify-center">
                <img id="forSaleModalProductImage" src="" alt="Scooter Image" class="w-full h-48 object-contain rounded" />
            </div>
            <!-- Info Right -->
            <div class="flex-1 flex flex-col justify-between">
                <h2 id="forSaleModalProductName" class="font-bold text-xl mb-2 font-[Barlow]"></h2>
                <span id="forSaleModalProductCategory" class="text-xs px-2 py-1 bg-gray-200 rounded-full font-[Barlow] mb-2"></span>
                <p id="forSaleModalProductDescription" class="text-sm text-gray-600 mb-3 font-[SFPro]"></p>
                <span id="forSaleModalProductPrice" class="text-blue-600 font-semibold text-lg mb-2"></span>
                <div class="flex items-center gap-2 mb-4">
                    <label for="forSaleModalProductQuantity" class="text-sm font-medium">Quantity:</label>
                    <input type="number" id="forSaleModalProductQuantity" min="1" value="1" class="w-16 border rounded p-1" />
                </div>
                <button id="forSaleModalBuyBtn" class="w-full bg-[#0086C9] text-white py-2 rounded hover:bg-blue-700 cursor-pointer font-bold">Add to Cart</button>
            </div>
        </div>
    </div>
</div>

<!-- Register Modal -->
    <div id="registerModal" style="background: rgba(0,0,0,0.6);" class="fixed inset-0 hidden flex items-start justify-center z-50 backdrop-blur-sm px-3 pb-4 overflow-y-auto">
        <div id="registerModalContent" class="relative bg-white p-3 sm:p-4 md:p-6 rounded-lg shadow-xl border border-gray-200 w-full max-w-[90vw] sm:max-w-xs md:max-w-md mx-1 mt-32 md:mt-0 max-h-[calc(100vh-8rem)] overflow-y-auto">
                <button onclick="closeRegisterModal()" class="absolute top-2 right-2 sm:top-2 sm:right-2 text-gray-500 hover:text-black text-2xl cursor-pointer" style="right:0.5rem;top:0.5rem;">&times;</button>
        <h2 class="text-lg sm:text-xl md:text-2xl font-bold mb-3 md:mb-4 text-center font-[Barlow] pr-6">Sign Up</h2>
        <form method="post" class="font-sans" action="/register" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="mb-2.5 md:mb-4">
                <label for="reg_first_name" class="block mb-1 text-sm">First Name</label>
                <input type="text" name="first_name" id="reg_first_name" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div class="mb-2.5 md:mb-4">
                <label for="reg_last_name" class="block mb-1 text-sm">Last Name</label>
                <input type="text" name="last_name" id="reg_last_name" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div class="mb-2.5 md:mb-4">
                <label for="reg_email" class="block mb-1 text-sm">Email</label>
                <input type="email" name="email" id="reg_email" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div class="mb-2.5 md:mb-4">
                <label for="reg_phone" class="block mb-1 text-sm">Phone</label>
                <input type="tel" name="phone" id="reg_phone" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div class="mb-2.5 md:mb-4">
                <label for="reg_address" class="block mb-1 text-sm">Address</label>
                <input type="text" name="address" id="reg_address" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div class="mb-2.5 md:mb-4">
                <label for="reg_password" class="block mb-1 text-sm">Password</label>
                <input type="password" name="password" id="reg_password" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div class="mb-2.5 md:mb-4">
                <label for="reg_confirm_password" class="block mb-1 text-sm">Confirm Password</label>
                <input type="password" name="confirm_password" id="reg_confirm_password" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div id="registerPasswordError" class="text-red-600 text-xs mb-2"></div>
            <div id="registerConfirmError" class="text-red-600 text-xs mb-2"></div>
            <button type="submit" class="w-full bg-[#0086C9] text-white py-2 rounded font-bold cursor-pointer text-sm" id="registerSubmitBtn">Register</button>
        </form>
    </div>
</div>

<script>
// Close modal when clicking outside modal content
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('registerModal');
    var content = document.getElementById('registerModalContent');
    if (modal && content) {
        modal.addEventListener('mousedown', function(e) {
            if (!content.contains(e.target)) {
                closeRegisterModal();
            }
        });
    }
});
// Lower z-index of burger and cart icon when register modal is open
function setNavZLow(isLow) {
    var burger = document.getElementById('burgerBtn');
    var cart = document.getElementById('mobileCartIcon');
    if (burger) burger.classList.toggle('z-low', isLow);
    if (cart) cart.classList.toggle('z-low', isLow);
}

// Patch open/close register modal to adjust z-index
function openRegisterModal() {
    document.getElementById('registerModal').classList.remove('hidden');
    setNavZLow(true);
}

function closeRegisterModal() {
    document.getElementById('registerModal').classList.add('hidden');
    setNavZLow(false);
}
function loadCart() {
    return JSON.parse(localStorage.getItem('cart') || '[]');
}
function saveCart(cart) {
    localStorage.setItem('cart', JSON.stringify(cart));
}
function updateCartCountBadge() {
    const cart = loadCart();
    const badge = document.getElementById('cartCountBadge');
    const mobileBadge = document.getElementById('mobileCartCount');
    const count = cart.reduce((sum, item) => sum + item.qty, 0);
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            badge.textContent = '';
            badge.classList.add('hidden');
        }
    }
    if (mobileBadge) {
        if (count > 0) {
            mobileBadge.textContent = count;
            mobileBadge.classList.remove('hidden');
        } else {
            mobileBadge.textContent = '';
            mobileBadge.classList.add('hidden');
        }
    }
}
function renderCart() {
    const cart = loadCart();
    const cartItems = document.getElementById('cartItems');
    const cartTotalRow = document.getElementById('cartTotalRow');
    cartItems.innerHTML = '';
    let total = 0;

    if (cart.length === 0) {
        cartItems.innerHTML = '<li class="text-gray-500">Cart is empty.</li>';
        if (cartTotalRow) cartTotalRow.innerHTML = '';
    } else {
        cart.forEach(item => {
            const lineTotal = item.price * item.qty;
            total += lineTotal;
            // Extract variation from name if present (format: Product - Variation)
            let name = item.name;
            let variation = '';
            if (name.includes(' - ')) {
                const parts = name.split(' - ');
                name = parts[0];
                variation = parts.slice(1).join(' - ');
            }
            cartItems.innerHTML += `
                <li class="flex items-center justify-between mb-2 border-b pb-2">
                    <div class="flex items-center font-[Barlow]">
                        <img src="${item.image_url || '/img/default-scooter.png'}" alt="${name}" class="w-16 h-16 object-cover mr-2">
                        <div>
                            <span class="block font-semibold text-sm text-gray-900">${name}</span>
                            ${variation ? `<span class='block text-xs text-blue-600 font-semibold mt-1 px-2 py-0.5 bg-blue-50 border border-blue-300 rounded-full w-fit mb-1'>${variation}</span>` : ''}
                            <div class="flex items-center mt-1">
                                <span class="text-blue-600 mr-2">$${Number(item.price).toFixed(2)}</span>
                                <input type="number" value="${item.qty}" min="1" max="${item.scooter_count}" class="w-12 p-1 border rounded" data-id="${item.id}" data-variation-id="${item.variation_id !== undefined ? item.variation_id : ''}">
                                <button class="cursor-pointer ml-2 remove-cart-item" data-id="${item.id}" data-variation-id="${item.variation_id !== undefined ? item.variation_id : ''}" title="Remove">
                                    <img src="/img/delete_grey.png" alt="Delete" class="w-5 h-5 inline-block align-middle">
                                </button>
                            </div>
                        </div>
                    </div>
                    <span class="text-blue-600 font-semibold">$${lineTotal.toFixed(2)}</span>
                </li>`;
        });
        // Render total outside the scrollable list
        if (cartTotalRow) {
            cartTotalRow.innerHTML = `<span>Total</span><span class="text-blue-600">$${total.toFixed(2)}</span>`;
        }
    }

    // Attach remove handlers
    document.querySelectorAll('.remove-cart-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const variationId = this.dataset.variationId;
            let cart = loadCart();
            cart = cart.filter(item => {
                // Remove only if both id and variation_id match
                if (variationId !== undefined && variationId !== null && variationId !== "") {
                    return !(String(item.id) === String(id) && String(item.variation_id) === String(variationId));
                } else {
                    return !(String(item.id) === String(id) && (!item.variation_id || item.variation_id === null || item.variation_id === ""));
                }
            });
            saveCart(cart);
            setTimeout(() => {
                renderCart();
                updateCartCountBadge();
            }, 0);
        });
    });

    // Attach quantity change handlers
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('change', function() {
            const id = this.dataset.id;
            const variationId = this.dataset.variationId;
            let cart = loadCart();
            const item = cart.find(item => 
                String(item.id) === String(id) && ((variationId && String(item.variation_id) === String(variationId)) || (!variationId && (!item.variation_id || item.variation_id === null || item.variation_id === "")))
            );
            if (item) {
                let newQty = parseInt(this.value) || 1;
                if (newQty > item.scooter_count) newQty = item.scooter_count;
                if (newQty < 1) newQty = 1;
                item.qty = newQty;
                saveCart(cart);
                renderCart();
                updateCartCountBadge();
            }
        });
    });

    updateCartCountBadge();
}

// Cart open/close logic
document.addEventListener('DOMContentLoaded', function() {
    const cartList = document.getElementById('cartList');
    const cartIconBtn = document.getElementById('cartIconBtn');
    const closeCart = document.getElementById('closeCart');

    renderCart();
    updateCartCountBadge();

    // Cart button toggles cartList (always renders items)
    cartIconBtn.addEventListener('click', function() {
        renderCart();
        cartList.classList.toggle('hidden');
    });

    // Hide cart when clicking close
    if (closeCart) {
        closeCart.addEventListener('click', function() {
            cartList.classList.add('hidden');
        });
    }

    // Hide cart when clicking outside
    document.addEventListener('click', function(e) {
        if (!cartList.contains(e.target) && e.target !== cartIconBtn && !cartIconBtn.contains(e.target)) {
            cartList.classList.add('hidden');
        }
    });
});


// Add to Cart function (make sure this is global)
function addToCart(name, id, price, image_url, scooter_count, variation_id = null, variation_name = null) {
    // Date validation
    const pickup = document.getElementById('pickupDatetime')?.value;
    const ret = document.getElementById('returnDatetime')?.value;
    if (!pickup || !ret) {
        // Show message
        const msg = document.getElementById('formMessage');
        if (msg) {
            msg.classList.remove('hidden');
            setTimeout(() => msg.classList.add('hidden'), 3000);
        } else {
            alert('Please select a date and time for Pickup and Return.');
        }
        // Emphasize rental form
        if (typeof emphasizeRentalForm === 'function') emphasizeRentalForm();
        return;
    }

    let cart = loadCart();
    let added = false;
    let existing = cart.find(item => {
        // If variation_id is provided, match both id and variation_id
        if (variation_id !== undefined && variation_id !== null) {
            return String(item.id) === String(id) && String(item.variation_id) === String(variation_id);
        } else {
            // No variation, match only id and no variation_id
            return String(item.id) === String(id) && (!item.variation_id || item.variation_id === null);
        }
    });
    if (existing) {
        if (existing.qty < existing.scooter_count) {
            existing.qty += 1;
            added = true;
        } else {
            alert('You cannot add more than the available stock.');
        }
    } else {
        added = true;
        cart.push({
            id,
            name,
            price: Number(price),
            qty: 1,
            image_url,
            scooter_count,
            variation_id: variation_id !== undefined ? variation_id : null,
            variation_name: variation_name !== undefined ? variation_name : null
        });
    }
    saveCart(cart);
    renderCart();
    updateCartCountBadge();
    if (added && typeof showCartToast === 'function') {
        showCartToast(name);
    }
}
window.addToCart = addToCart; // Make it accessible globally

function showCartToast(productName) {
    const toast = document.getElementById('cartToast');
    toast.textContent = `"${productName}" has been added to your cart.`;
    toast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-8');
    toast.classList.add('opacity-100', 'pointer-events-auto', 'translate-y-0');
    setTimeout(() => {
        toast.classList.remove('opacity-100', 'pointer-events-auto', 'translate-y-0');
        toast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-8');
    }, 2000);
}

function openProductModal(product) {
    // Get modal elements early to avoid TDZ errors
    const stockElem = document.getElementById('modalProductStock');
    const rentNowBtn = document.getElementById('modalRentNowBtn');

    // Hide and disable quantity label if in product-list.php
    if (window.isProductListModal) {
        const qtyLabel = document.querySelector('#productModal label[for="modalProductQuantity"]') || document.querySelector('#productModal .flex.items-center.mb-4 label');
        if (qtyLabel) qtyLabel.style.display = 'none';
    } else {
        const qtyLabel = document.querySelector('#productModal label[for="modalProductQuantity"]') || document.querySelector('#productModal .flex.items-center.mb-4 label');
        if (qtyLabel) qtyLabel.style.display = '';
    }
    document.getElementById('modalProductImage').src = product.image_url || '';
    document.getElementById('modalProductName').textContent = product.name || '';
    document.getElementById('modalProductCategory').textContent = product.category || '';
    document.getElementById('modalProductCategory').style.display = product.category ? '' : 'none';

    // Stock logic (context-aware, single block)
    let stock = 0;
    if (typeof product.total_stock !== 'undefined') {
        stock = parseInt(product.total_stock);
    } else if (typeof product.scooter_count !== 'undefined') {
        stock = parseInt(product.scooter_count);
    }
    if (isNaN(stock)) stock = 0;
    if (stockElem) {
        if (stock > 0) {
            stockElem.textContent = stock + ' in stock';
            stockElem.classList.remove('text-red-600');
        } else {
            stockElem.textContent = 'Out of stock';
            stockElem.classList.add('text-red-600');
        }
    }
    if (rentNowBtn) {
        if (stock > 0) {
            rentNowBtn.disabled = false;
            rentNowBtn.classList.remove('opacity-60', 'cursor-not-allowed', 'bg-gray-400');
            rentNowBtn.classList.add('bg-[#0086C9]', 'hover:bg-blue-700', 'cursor-pointer');
            rentNowBtn.textContent = 'Rent Now';
        } else {
            rentNowBtn.disabled = true;
            rentNowBtn.classList.add('opacity-60', 'cursor-not-allowed', 'bg-gray-400');
            rentNowBtn.classList.remove('bg-[#0086C9]', 'hover:bg-blue-700', 'cursor-pointer');
            rentNowBtn.textContent = 'Out of Stock';
        }
    }
    // Show variation if available
    let variationElem = document.getElementById('modalProductVariation');
    if (!variationElem) {
        variationElem = document.createElement('span');
        variationElem.id = 'modalProductVariation';
        variationElem.className = 'inline-block text-xs px-2 py-1 mb-2 bg-blue-100 rounded-full text-blue-700 font-[Barlow] ml-2';
        const catElem = document.getElementById('modalProductCategory');
        if (catElem && catElem.parentNode) {
            catElem.parentNode.insertBefore(variationElem, catElem.nextSibling);
        }
    }
    if (product.variation_name) {
        variationElem.textContent = product.variation_name;
        variationElem.style.display = '';
    } else {
        variationElem.textContent = '';
        variationElem.style.display = 'none';
    }
    document.getElementById('modalProductDescription').textContent = product.description && product.description.trim() !== '' ? product.description : 'No description available.';

    // Only enable dropdown and price logic on homepage
    var isHomePageModal = (!window.isSearchResultsModal && !window.isProductListModal && (window.location.pathname === '/' || window.location.pathname === '/index.php'));
    if (isHomePageModal) {
        // --- Dynamic Price Calculation Based on Date Selection ---
        function getDaysDiff(pickup, ret) {
            if (!pickup || !ret) return 1;
            const start = new Date(pickup);
            const end = new Date(ret);
            if (isNaN(start) || isNaN(end)) return 1;
            let diff = (end - start) / (1000 * 60 * 60 * 24);
            return diff > 0 ? Math.ceil(diff) : 1;
        }
        function getTieredPrice(productId, variationId, days) {
            if (!window.rentalPrices || !window.rentalPrices[productId] || !window.rentalPrices[productId][variationId]) return null;
            const tiers = window.rentalPrices[productId][variationId];
            days = Math.min(Math.max(parseInt(days, 10) || 1, 1), 31);
            const key = String(days);
            if (!Object.prototype.hasOwnProperty.call(tiers, key)) return null;
            return Number(tiers[key]);
        }
        // --- Dropdown Synchronization Logic ---
        function getCardDropdown() {
            // Find the card dropdown for this product/variation
            const selector = `.instant-days-dropdown[data-product-id='${product.id}'][data-variation-id='${product.variation_id}']`;
            return document.querySelector(selector);
        }
        function updateModalPrice(syncCard) {
            let price = product.price;
            let days = 1;
            const daysDropdown = document.getElementById('modalDaysDropdown');
            if (daysDropdown) {
                days = parseInt(daysDropdown.value) || 1;
            }
            if (window.rentalPrices && window.rentalPrices[product.id] && window.rentalPrices[product.id][product.variation_id]) {
                const tierPrice = getTieredPrice(product.id, product.variation_id, days);
                if (tierPrice !== null) price = parseFloat(tierPrice);
            }
            const priceElem = document.getElementById('modalProductPrice');
            if (priceElem) priceElem.textContent = '$' + Number(price).toFixed(2);
            // If syncCard is true, update the card dropdown
            if (syncCard) {
                const cardDropdown = getCardDropdown();
                if (cardDropdown && parseInt(cardDropdown.value) !== days) {
                    cardDropdown.value = days;
                    // Optionally, trigger card price update if needed
                    if (typeof window.updateInstantMobilityPrices === 'function') window.updateInstantMobilityPrices();
                }
            }
        }
        // Attach dropdown listener
        setTimeout(function() {
            const daysDropdown = document.getElementById('modalDaysDropdown');
            if (daysDropdown) {
                // Set modal dropdown to match card dropdown if present
                const cardDropdown = getCardDropdown();
                if (cardDropdown) {
                    daysDropdown.value = cardDropdown.value;
                }
                daysDropdown.addEventListener('change', function() {
                    updateModalPrice(true); // sync card
                });
            }
        }, 0);
        updateModalPrice();
    }
    // Attach Rent Now button handler
    if (rentNowBtn) {
        rentNowBtn.onclick = function(e) {
            e.preventDefault();
            var isHomePageModal = (!window.isSearchResultsModal && !window.isProductListModal && (window.location.pathname === '/' || window.location.pathname === '/index.php'));
            let price = product.price;
            if (isHomePageModal) {
                // Use the selected days from dropdown
                let days = 1;
                const daysDropdown = document.getElementById('modalDaysDropdown');
                if (daysDropdown) {
                    days = parseInt(daysDropdown.value) || 1;
                }
                // Calculate correct tiered price for selected days
                if (window.rentalPrices && window.rentalPrices[product.id] && window.rentalPrices[product.id][product.variation_id]) {
                    const tierPrice = getTieredPrice(product.id, product.variation_id, days);
                    if (tierPrice !== null) price = Number(tierPrice);
                }
            }
            // Prepare cart with only this product, including variation info if present
            const cartItem = {
                id: product.id,
                name: product.variation_name ? product.name + ' - ' + product.variation_name : product.name,
                price: price,
                qty: 1,
                image_url: product.image_url,
                scooter_count: product.scooter_count
            };
            if (product.variation_id) cartItem.variation_id = product.variation_id;
            if (product.variation_name) cartItem.variation_name = product.variation_name;
            const cart = [cartItem];
            localStorage.setItem('cart', JSON.stringify(cart));
            // Redirect to checkout
            window.location.href = '/checkout';
        };
    }

    // Attach Add to Cart button handler
    const addToCartBtn = document.getElementById('modalAddToCartBtn');
    if (addToCartBtn) {
        addToCartBtn.onclick = function(e) {
            e.preventDefault();
            let price = product.price;
            let days = 1;
            const daysDropdown = document.getElementById('modalDaysDropdown');
            if (daysDropdown) {
                days = parseInt(daysDropdown.value) || 1;
            }
            if (window.rentalPrices && window.rentalPrices[product.id] && window.rentalPrices[product.id][product.variation_id]) {
                const tierPrice = getTieredPrice(product.id, product.variation_id, days);
                if (tierPrice !== null) price = Number(tierPrice);
            }
            window.addToCart(
                product.variation_name ? product.name + ' - ' + product.variation_name : product.name,
                product.id,
                price,
                product.image_url,
                product.scooter_count,
                product.variation_id,
                product.variation_name
            );
        };
    }
    // Listen for date changes to update price in modal
    const pickupInput = document.getElementById('pickupDatetime');
    const returnInput = document.getElementById('returnDatetime');
    if (pickupInput) {
        pickupInput.addEventListener('change', updateModalPrice);
        pickupInput.addEventListener('input', updateModalPrice);
    }
    if (returnInput) {
        returnInput.addEventListener('change', updateModalPrice);
        returnInput.addEventListener('input', updateModalPrice);
    }

    // Hide and disable quantity and add to cart if in product-list.php
    if (window.isProductListModal) {
        const qtyInput = document.getElementById('modalProductQuantity');
        if (qtyInput) {
            qtyInput.disabled = true;
            qtyInput.style.display = 'none';
        }
        const qtyDec = document.getElementById('modalQtyDecrease');
        if (qtyDec) qtyDec.style.display = 'none';
        const qtyInc = document.getElementById('modalQtyIncrease');
        if (qtyInc) qtyInc.style.display = 'none';
        const addBtn = document.getElementById('modalAddToCartBtn');
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.style.display = 'none';
        }
    } else {
        const qtyInput = document.getElementById('modalProductQuantity');
        if (qtyInput) {
            qtyInput.disabled = false;
            qtyInput.style.display = '';
        }
        const qtyDec = document.getElementById('modalQtyDecrease');
        if (qtyDec) qtyDec.style.display = '';
        const qtyInc = document.getElementById('modalQtyIncrease');
        if (qtyInc) qtyInc.style.display = '';
        const addBtn = document.getElementById('modalAddToCartBtn');
        if (addBtn) {
            addBtn.disabled = false;
            addBtn.style.display = '';
            addBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    document.getElementById('productModal').classList.remove('hidden');
    // --- Close modal when clicking outside ---
    setTimeout(function() { // Delay to avoid immediate close on open
        document.addEventListener('mousedown', outsideModalClick);
    }, 10);
    function outsideModalClick(e) {
        const modal = document.querySelector('#productModal > div');
        if (modal && !modal.contains(e.target)) {
            closeProductModal();
        }
    }
    // Remove event listener on close
    window._closeProductModal = function() {
        document.removeEventListener('mousedown', outsideModalClick);
        closeProductModal();
    };
    // Override close button to also remove listener
    const closeBtn = document.querySelector('#productModal button[onclick="closeProductModal()"]');
    if (closeBtn) closeBtn.onclick = window._closeProductModal;
}
function closeProductModal() {
    // Remove outside click listener if present
    if (window._closeProductModal) {
        document.removeEventListener('mousedown', window._closeProductModal);
        window._closeProductModal = null;
    }
    document.getElementById('productModal').classList.add('hidden');
    // Reset context flag for search-results modal
    window.isSearchResultsModal = false;
    // Remove outside click listener if present
    if (window._closeProductModal) {
        document.removeEventListener('mousedown', window._closeProductModal);
        window._closeProductModal = null;
    }
}

function openProductListModal(product) {
    const modal = document.getElementById('productListModal');
    const imageElem = document.getElementById('productListModalImage');
    const nameElem = document.getElementById('productListModalName');
    const categoryElem = document.getElementById('productListModalCategory');
    const descriptionElem = document.getElementById('productListModalDescription');

    if (!modal || !imageElem || !nameElem || !categoryElem || !descriptionElem) return;

    imageElem.src = product.image_url || '';
    imageElem.alt = product.name || 'Product image';
    nameElem.textContent = product.name || '';
    categoryElem.textContent = product.category || '';
    categoryElem.style.display = product.category ? '' : 'none';
    descriptionElem.textContent = (product.description && product.description.trim() !== '') ? product.description : 'No description available.';

    modal.classList.remove('hidden');

    function outsideProductListModalClick(e) {
        const modalContent = document.querySelector('#productListModal > div');
        if (modalContent && !modalContent.contains(e.target)) {
            closeProductListModal();
        }
    }

    window._outsideProductListModalClick = outsideProductListModalClick;

    setTimeout(function() {
        document.addEventListener('mousedown', window._outsideProductListModalClick);
    }, 10);

    window._closeProductListModal = function() {
        document.removeEventListener('mousedown', outsideProductListModalClick);
        closeProductListModal();
    };

    const closeBtn = document.querySelector('#productListModal button[onclick="closeProductListModal()"]');
    if (closeBtn) closeBtn.onclick = window._closeProductListModal;
}

function closeProductListModal() {
    const modal = document.getElementById('productListModal');
    if (modal) modal.classList.add('hidden');

    if (window._outsideProductListModalClick) {
        document.removeEventListener('mousedown', window._outsideProductListModalClick);
        window._outsideProductListModalClick = null;
    }

    window._closeProductListModal = null;

    window.isProductListModal = false;
}

window.openProductListModal = openProductListModal;
window.closeProductListModal = closeProductListModal;

document.addEventListener('DOMContentLoaded', function() {
    const burgerBtn = document.getElementById('burgerBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    let menuOverlay = document.getElementById('menuOverlay');
    const mobileCartIcon = document.getElementById('mobileCartIcon');
    const mobileCartList = document.getElementById('mobileCartList');
    const closeMobileCart = document.getElementById('closeMobileCart');

    // Ensure menuOverlay exists for mobile menu overlay
    if (!menuOverlay) {
        menuOverlay = document.createElement('div');
        menuOverlay.id = 'menuOverlay';
        menuOverlay.className = 'fixed inset-0 bg-black/40 z-[70] md:hidden hidden';
        document.body.appendChild(menuOverlay);
    }

    if (burgerBtn && mobileMenu && menuOverlay) {
        burgerBtn.addEventListener('click', function() {
            mobileMenu.classList.remove('-translate-x-full');
            mobileMenu.classList.add('translate-x-0');
            menuOverlay.classList.remove('hidden');
        });

        menuOverlay.addEventListener('click', function() {
            mobileMenu.classList.remove('translate-x-0');
            mobileMenu.classList.add('-translate-x-full');
            menuOverlay.classList.add('hidden');
        });

        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.remove('translate-x-0');
                mobileMenu.classList.add('-translate-x-full');
                menuOverlay.classList.add('hidden');
            });
        });
    }

    // Mobile cart icon toggles mobile cart list
    if (mobileCartIcon && mobileCartList) {
        mobileCartIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            renderMobileCart();
            mobileCartList.classList.toggle('hidden');
        });
    }
    if (closeMobileCart && mobileCartList) {
        closeMobileCart.addEventListener('click', function() {
            mobileCartList.classList.add('hidden');
        });
    }
    // Hide mobile cart when clicking outside
    document.addEventListener('click', function(e) {
        if (mobileCartList && !mobileCartList.contains(e.target) && e.target !== mobileCartIcon && !mobileCartIcon.contains(e.target)) {
            mobileCartList.classList.add('hidden');
        }
    });

});

// Render mobile cart
function renderMobileCart() {
    const cart = loadCart();
    const cartItems = document.getElementById('mobileCartItems');
    const cartTotalRow = document.getElementById('mobileCartTotalRow');
    cartItems.innerHTML = '';
    let total = 0;

    if (cart.length === 0) {
        cartItems.innerHTML = '<li class="text-gray-500">Cart is empty.</li>';
        if (cartTotalRow) cartTotalRow.innerHTML = '';
    } else {
        cart.forEach(item => {
            const lineTotal = item.price * item.qty;
            total += lineTotal;
            // Extract variation from name if present (format: Product - Variation)
            let name = item.name;
            let variation = '';
            if (name.includes(' - ')) {
                const parts = name.split(' - ');
                name = parts[0];
                variation = parts.slice(1).join(' - ');
            }
            cartItems.innerHTML += `
                <li class="flex items-center justify-between mb-2 border-b pb-2">
                    <div class="flex items-center font-[Barlow]">
                        <img src="${item.image_url || '/img/default-scooter.png'}" alt="${name}" class="w-16 h-16 object-cover mr-2">
                        <div>
                            <span class="block font-semibold text-sm text-gray-900">${name}</span>
                            ${variation ? `<span class='block text-xs text-blue-600 font-semibold mt-1 px-2 py-0.5 bg-blue-50 border border-blue-300 rounded-full w-fit mb-1'>${variation}</span>` : ''}
                            <div class="flex items-center mt-1">
                                <span class="text-blue-600 mr-2">$${Number(item.price).toFixed(2)}</span>
                                <input type="number" value="${item.qty}" min="1" max="${item.scooter_count}" class="w-12 p-1 border rounded" data-id="${item.id}" data-variation-id="${item.variation_id !== undefined ? item.variation_id : ''}">
                                <button class="cursor-pointer ml-2 remove-mobile-cart-item" data-id="${item.id}" data-variation-id="${item.variation_id !== undefined ? item.variation_id : ''}" title="Remove">
                                    <img src="/img/delete_grey.png" alt="Delete" class="w-5 h-5 inline-block align-middle">
                                </button>
                            </div>
                        </div>
                    </div>
                    <span class="text-blue-600 font-semibold">$${lineTotal.toFixed(2)}</span>
                </li>`;
        });
        if (cartTotalRow) {
            cartTotalRow.innerHTML = `<span>Total</span><span class="text-blue-600">$${total.toFixed(2)}</span>`;
        }
    }

    // Remove handlers for mobile
    document.querySelectorAll('.remove-mobile-cart-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            let cart = loadCart();
            cart = cart.filter(item => {
                if (btn.dataset.scooterId) {
                    return !(String(item.id) === String(id) && String(item.scooter_id) === String(btn.dataset.scooterId));
                }
                return String(item.id) !== String(id);
            });
            saveCart(cart);
            setTimeout(() => {
                renderMobileCart();
                updateCartCountBadge();
            }, 0);
        });
    });

    // Quantity change handlers for mobile
    document.querySelectorAll('#mobileCartItems input[type="number"]').forEach(input => {
        input.addEventListener('change', function() {
            const id = this.dataset.id;
            const scooterId = this.dataset.scooterId;
            let cart = loadCart();
            const item = cart.find(item =>
                String(item.id) === String(id) && (!scooterId || String(item.scooter_id) === String(scooterId))
            );
            if (item) {
                let newQty = parseInt(this.value) || 1;
                if (newQty > item.scooter_count) newQty = item.scooter_count;
                if (newQty < 1) newQty = 1;
                item.qty = newQty;
                saveCart(cart);
                renderMobileCart();
                updateCartCountBadge();
            }
        });
    });

    updateCartCountBadge();
}

// For-Sale Product Modal
function openForSaleProductModal(product) {
    document.getElementById('forSaleModalProductImage').src = product.image_url || '';
    document.getElementById('forSaleModalProductName').textContent = product.product_name || '';
    document.getElementById('forSaleModalProductCategory').textContent = product.category_name || '';
    document.getElementById('forSaleModalProductCategory').style.display = product.category_name ? '' : 'none';
    document.getElementById('forSaleModalProductDescription').textContent = product.description || 'No description available.';
    document.getElementById('forSaleModalProductPrice').textContent = '$' + Number(product.price).toFixed(2);
    document.getElementById('forSaleModalProductQuantity').value = 1;
    document.getElementById('forSaleProductModal').classList.remove('hidden');
    // Attach Buy Now handler
    document.getElementById('forSaleModalBuyBtn').onclick = function() {
        const qty = parseInt(document.getElementById('forSaleModalProductQuantity').value) || 1;
        addForSaleToCart(product, qty);
        closeForSaleProductModal();
    };
    // Add outside click handler
    setTimeout(function() {
        document.addEventListener('mousedown', outsideForSaleModalClick);
    }, 10);
    function outsideForSaleModalClick(e) {
        const modal = document.querySelector('#forSaleProductModal > div');
        if (modal && !modal.contains(e.target)) {
            closeForSaleProductModal();
        }
    }
    window._closeForSaleProductModal = function() {
        document.removeEventListener('mousedown', outsideForSaleModalClick);
        closeForSaleProductModal();
    };
    // Override close button to also remove listener
    const closeBtn = document.querySelector('#forSaleProductModal button[onclick="closeForSaleProductModal()"]');
    if (closeBtn) closeBtn.onclick = window._closeForSaleProductModal;
}
function closeForSaleProductModal() {
    document.getElementById('forSaleProductModal').classList.add('hidden');
    // Remove outside click listener if present
    if (window._closeForSaleProductModal) {
        document.removeEventListener('mousedown', window._closeForSaleProductModal);
        window._closeForSaleProductModal = null;
    }
}
function addForSaleToCart(product, qty) {
    let cart = loadCart ? loadCart() : [];
    let existing = cart.find(item => item.id === product.product_id && item.type === 'for-sale');
    const stock = parseInt(product.available_scooter_count) || 0;
    qty = parseInt(qty) || 1;
    if (existing) {
        if (existing.qty + qty > stock) {
            alert('You cannot add more than the available stock.');
            return;
        }
        existing.qty += qty;
    } else {
        if (qty > stock) {
            alert('You cannot add more than the available stock.');
            return;
        }
        cart.push({
            id: product.product_id,
            name: product.product_name,
            price: product.price,
            image_url: product.image_url,
            qty: qty,
            type: 'for-sale',
            sale_type: 'sale',
            category: product.category_name
        });
    }
    if (typeof saveCart === 'function') saveCart(cart);
    if (typeof renderCart === 'function') renderCart();
    if (typeof updateCartCountBadge === 'function') updateCartCountBadge();
    if (typeof showCartToast === 'function') {
        showCartToast(product.product_name);
    }
}
window.openForSaleProductModal = openForSaleProductModal;
window.closeForSaleProductModal = closeForSaleProductModal;
window.addForSaleToCart = addForSaleToCart;

</script>


<!-- FOR REGISTRATION MODAL -->
<script>


    // ------- Password Validation -------
    const regPasswordInput = document.getElementById('reg_password');
    const regConfirmInput = document.getElementById('reg_confirm_password');
    const regSubmitBtn = document.getElementById('registerSubmitBtn');
    const regPasswordError = document.getElementById('registerPasswordError');
    const regConfirmError = document.getElementById('registerConfirmError');

    function validateRegPassword() {
        const password = regPasswordInput.value;
        const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
        if (!regex.test(password)) {
            regPasswordError.textContent = "Your password must contain at least 8 characters, one uppercase, one number, one special character.";
            return false;
        }
        regPasswordError.textContent = "";
        return true;
    }

    function validateRegConfirm() {
        if (regConfirmInput.value !== regPasswordInput.value) {
            regConfirmError.textContent = "Passwords do not match.";
            return false;
        }
        regConfirmError.textContent = "";
        return true;
    }

    function updateRegSubmitState() {
        regSubmitBtn.disabled = !(validateRegPassword() && validateRegConfirm());
    }

    regPasswordInput.addEventListener('input', updateRegSubmitState);
    regConfirmInput.addEventListener('input', updateRegSubmitState);

    document.getElementById('registerForm').addEventListener('submit', function(e) {
        if (!(validateRegPassword() && validateRegConfirm())) {
            e.preventDefault();
        }
    });

    updateRegSubmitState();

    document.addEventListener('DOMContentLoaded', function() {
        // Mobile date pickers
        const mobilePickupInput = document.getElementById('mobilePickupDatetime');
        const mobileReturnInput = document.getElementById('mobileReturnDatetime');

        if (mobilePickupInput && mobileReturnInput && typeof flatpickr === 'function') {
            const savedPickup = localStorage.getItem('pickupDatetime');
            const savedReturn = localStorage.getItem('returnDatetime');

            const mobilePickupPicker = flatpickr(mobilePickupInput, {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: new Date(),
                time_24hr: true,
                minuteIncrement: 15,
                onChange: function(selectedDates) {
                    if (selectedDates[0]) {
                        mobileReturnPicker.set('minDate', selectedDates[0]);
                        if (mobileReturnInput.value && new Date(mobileReturnInput.value) < selectedDates[0]) {
                            mobileReturnInput.value = '';
                            mobileReturnPicker.clear();
                        }
                    }
                }
            });

            const mobileReturnPicker = flatpickr(mobileReturnInput, {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: new Date(),
                time_24hr: true,
                minuteIncrement: 15
            });

            // Set initial values from localStorage
            if (savedPickup) {
                mobilePickupInput.value = savedPickup;
                mobilePickupPicker.setDate(savedPickup, false);
            }
            if (savedReturn) {
                mobileReturnInput.value = savedReturn;
                mobileReturnPicker.setDate(savedReturn, false);
            }

            // Save to localStorage on change
            mobilePickupInput.addEventListener('change', () => {
                localStorage.setItem('pickupDatetime', mobilePickupInput.value || '');
            });
            mobileReturnInput.addEventListener('change', () => {
                localStorage.setItem('returnDatetime', mobileReturnInput.value || '');
            });
        }
    });
</script>

<script>
    // Navbar 'Rent now' button: if not on homepage, go to /#rentalForm; if on homepage, scroll as normal
    document.addEventListener('DOMContentLoaded', function() {
        var rentNowBtn = document.getElementById('rentNowBtn');
        if (rentNowBtn) {
            rentNowBtn.addEventListener('click', function(e) {
                // Check if already on homepage (index.php or /)
                var isHome = window.location.pathname === '/' || window.location.pathname.match(/index\.php$/);
                if (!isHome) {
                    e.preventDefault();
                    window.location.href = '/#rentalForm';
                }
                // else, let default anchor behavior scroll to section
            });
        }
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchIcon = document.getElementById('mobileSearchIcon');
    const searchOverlay = document.getElementById('mobileSearchOverlay');
    const searchBarWrap = document.getElementById('mobileSearchBarWrap');
    const searchCancel = document.getElementById('mobileSearchCancel');
    const searchInput = document.getElementById('mobileSearchInput');

    function showMobileSearch() {
        searchOverlay.classList.remove('hidden');
        searchBarWrap.classList.remove('hidden');
        // Animate in
        setTimeout(() => {
            searchOverlay.classList.add('opacity-100');
            searchOverlay.classList.remove('opacity-0');
            searchBarWrap.classList.add('opacity-100');
            searchBarWrap.classList.remove('opacity-0', '-translate-y-8');
            if (searchInput) searchInput.focus();
        }, 10);
    }
    function hideMobileSearch() {
        // Animate out
        searchOverlay.classList.remove('opacity-100');
        searchOverlay.classList.add('opacity-0');
        searchBarWrap.classList.remove('opacity-100');
        searchBarWrap.classList.add('opacity-0', '-translate-y-8');
        setTimeout(() => {
            searchOverlay.classList.add('hidden');
            searchBarWrap.classList.add('hidden');
        }, 300);
    }
    if (searchIcon && searchOverlay && searchBarWrap && searchCancel) {
        searchIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            showMobileSearch();
        });
        searchCancel.addEventListener('click', function() {
            hideMobileSearch();
        });
        searchOverlay.addEventListener('click', function() {
            hideMobileSearch();
        });
    }
});
</script>