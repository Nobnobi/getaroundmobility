<?php

use App\Controllers\HomeController;
use App\Controllers\AdminController;
use App\Controllers\UserController;
use App\Controllers\OrderController;
use App\Controllers\ScooterController;
use App\Controllers\ProductController;
use App\Controllers\CategoryController;
use App\Controllers\CustomerController;
use App\Controllers\RentalPriceController;
use App\Controllers\LocationsController;
use App\Router;

$router = new Router();

$router->get('/', HomeController::class, 'index');
$router->get('/product-list', HomeController::class, 'productList');
$router->get('/search', HomeController::class, 'search');
$router->get('/cart', HomeController::class, 'cart');
$router->get('/checkout', HomeController::class, 'checkout');
$router->get('/contact', HomeController::class, 'contact');
$router->get('/for-sale', ProductController::class, 'forSale');
// $router->get('/search-results', HomeController::class, 'searchResults');

// PHP BACKEND FOR CHECKOUT FORM SUBMISSION
$router->post('/checkout', OrderController::class, 'processCheckout');

$router->get('/login', UserController::class, 'login');
$router->post('/login', UserController::class, 'processLogin');
$router->get('/profile', UserController::class, 'profile');
$router->post('/profile', UserController::class, 'updateProfile');
$router->get('/logout', UserController::class, 'logout');
$router->get('/register', UserController::class, 'register');
$router->post('/register', UserController::class, 'processRegister');
$router->post('/contact-submit', UserController::class, 'contactSubmit');


// FORGOT PASSWORD
$router->get('/forgot-password', UserController::class, 'forgotPassword');
$router->post('/forgot-password', UserController::class, 'processForgotPassword');

// RESET PASSWORD
$router->get('/reset-password', UserController::class, 'resetPassword');
$router->post('/reset-password', UserController::class, 'processResetPassword');

// STRIPE CHECKOUT SESSION
$router->post('/create-checkout-session', OrderController::class, 'createCheckoutSession');
$router->post('/create-payment-intent', OrderController::class, 'createPaymentIntent');
$router->post('/stripe-finalize-payment', OrderController::class, 'finalizeStripePayment');
$router->post('/stripe-webhook', OrderController::class, 'stripeWebhook');
$router->get('/checkout-success', OrderController::class, 'checkoutSuccess');
$router->get('/checkout-cancel', OrderController::class, 'checkoutCancel');
$router->get('/stripe-return', OrderController::class, 'stripeReturn');

// PAYPAL ROUTES
$router->post('/api/orders', OrderController::class, 'createPaypalOrder');
$router->post('/api/orders/{orderId}/capture', OrderController::class, 'capturePaypalOrder');
$router->get('/paypal-return', OrderController::class, 'paypalReturn');

$router->post('/save-checkout-form', OrderController::class, 'saveCheckoutForm');

// FOR ADMIN DASHBOARD
$router->get('/admin/login', AdminController::class, 'login');
$router->post('/admin/login', AdminController::class, 'processLogin');
$router->get('/admin/orders', AdminController::class, 'orders');
$router->post('/admin/orders/approve', AdminController::class, 'approveOrder');
$router->post('/admin/orders/complete', \App\Controllers\OrderController::class, 'completeOrder');
$router->post('/admin/orders/cancel', OrderController::class, 'cancelOrder');
$router->get('/admin/orders/new', AdminController::class, 'newOrder');
$router->get('/admin/orders/availability', AdminController::class, 'newOrderAvailability');
$router->post('/admin/orders/new', AdminController::class, 'processNewOrder');
$router->post('/admin/orders/reject', AdminController::class, 'rejectOrder');
$router->post('/admin/orders/paid', AdminController::class, 'markAsPaid');
$router->get('/admin/logout', AdminController::class, 'logout');

// RESERVATIONS PAGE
$router->get('/admin/reservations', AdminController::class, 'reservations');

// LOCATIONS MANAGEMENT
$router->get('/admin/locations', LocationsController::class, 'index');
$router->post('/admin/locations', LocationsController::class, 'handlePost');

// FOR ADMIN DASHBOARD - SCOOTERS FOR SALE
$router->get('/admin/scooters-for-sale', ProductController::class, 'scootersForSale');
$router->post('/admin/scooters-for-sale/add', ProductController::class, 'addScooterForSale');
$router->get('/admin/scooters-for-sale/add', ProductController::class, 'addScooterForSale');
$router->post('/admin/scooters-for-sale/save', ProductController::class, 'saveScootersForSale');
$router->post('/admin/scooters-for-sale/update', ProductController::class, 'updateScooterForSale');
$router->post('/admin/scooters-for-sale/delete', ProductController::class, 'deleteScooterForSale');

// FEATURED PRODUCTS
$router->get('/admin/featured-products', AdminController::class, 'featuredProducts');
$router->post('/admin/featured-products', AdminController::class, 'featuredProducts');

// RENTAL PRICES MANAGEMENT
$router->get('/admin/rental-prices', RentalPriceController::class, 'index');
$router->post('/admin/rental-prices/save', RentalPriceController::class, 'save');

// INVENTORY MANAGEMENT - SCOOTERS
$router->get('/admin/scooters', ScooterController::class, 'index');      // List all scooters
$router->get('/admin/scooters/new', ScooterController::class, 'create'); // Show add form
$router->post('/admin/scooters/new', ScooterController::class, 'store'); // Handle add
$router->get('/admin/scooters/edit', ScooterController::class, 'edit');  // Show edit form
$router->post('/admin/scooters/edit', ScooterController::class, 'update'); // Handle edit
$router->post('/admin/scooters/delete', ScooterController::class, 'delete'); // Handle delete
$router->post('/admin/scooters/save', ScooterController::class, 'save');
$router->get('/admin/scooters/list', ScooterController::class, 'listByProduct'); // AJAX for modal

$router->get('/admin/products/new', ProductController::class, 'create');
$router->post('/admin/products/new', ProductController::class, 'store');
$router->get('/admin/products', ProductController::class, 'index');
$router->post('/admin/products/save', ProductController::class, 'save');
$router->post('/admin/products/delete', ProductController::class, 'delete');

// PRODUCT VARIATIONS
$router->get('/admin/product-variations/new', ProductController::class, 'addProductVariation');
$router->post('/admin/product-variations/new', ProductController::class, 'addProductVariation');
$router->get('/admin/product-variations', ProductController::class, 'listProductVariations');
$router->post('/admin/product-variations/save', ProductController::class, 'saveProductVariations');
$router->get('/admin/api/product-variations', ProductController::class, 'apiProductVariations');

// ADMIN TESTIMONIALS ROUTES
$router->get('/admin/testimonials', AdminController::class, 'testimonials');
$router->get('/admin/testimonials/add', AdminController::class, 'addTestimonial');
$router->post('/admin/testimonials/add', AdminController::class, 'addTestimonial');
$router->get('/admin/testimonials/edit', AdminController::class, 'editTestimonial');
$router->post('/admin/testimonials/edit', AdminController::class, 'editTestimonial');
$router->post('/admin/testimonials/delete', AdminController::class, 'deleteTestimonial');

// ADMIN TIPS & TROUBLESHOOTING ROUTES
$router->get('/admin/tips-troubleshooting', AdminController::class, 'tipsTroubleshooting');
$router->post('/admin/tips-troubleshooting/section', AdminController::class, 'saveTipsTroubleshootingSection');
$router->post('/admin/tips-troubleshooting/articles/add', AdminController::class, 'addTipsTroubleshootingArticle');
$router->post('/admin/tips-troubleshooting/articles/update', AdminController::class, 'updateTipsTroubleshootingArticle');
$router->post('/admin/tips-troubleshooting/articles/delete', AdminController::class, 'deleteTipsTroubleshootingArticle');

// CUSTOMERS
$router->get('/admin/customers', CustomerController::class, 'index');

// ADMINS AND ROLES
$router->get('/admin/admins', AdminController::class, 'admins');
$router->get('/admin/admins/add', AdminController::class, 'addAdmin');
$router->post('/admin/admins/add', AdminController::class, 'addAdmin');
$router->get('/admin/admins/edit', AdminController::class, 'editAdmin');
$router->post('/admin/admins/edit', AdminController::class, 'editAdmin');
$router->post('/admin/admins/delete', AdminController::class, 'deleteAdmin');


$router->get('/admin/categories', CategoryController::class, 'index');
$router->post('/admin/categories/save', CategoryController::class, 'save');

$router->get('/admin/orders/details', OrderController::class, 'ajaxOrderDetails');

$router->dispatch();