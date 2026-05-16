<?php

use App\Router;
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

$router = new Router();

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
$router->get('/', HomeController::class, 'index');
$router->get('/product-list', HomeController::class, 'productList');
$router->get('/search', HomeController::class, 'search');
$router->get('/cart', HomeController::class, 'cart');
$router->get('/checkout', HomeController::class, 'checkout');
$router->get('/contact', HomeController::class, 'contact');

$router->get('/for-sale', ProductController::class, 'forSale');

$router->post('/checkout', OrderController::class, 'processCheckout');
$router->post('/save-checkout-form', OrderController::class, 'saveCheckoutForm');

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
$router->get('/login', UserController::class, 'login');
$router->post('/login', UserController::class, 'processLogin');

$router->get('/register', UserController::class, 'register');
$router->post('/register', UserController::class, 'processRegister');

$router->get('/logout', UserController::class, 'logout');

$router->get('/profile', UserController::class, 'profile');
$router->post('/profile', UserController::class, 'updateProfile');

$router->post('/contact-submit', UserController::class, 'contactSubmit');

/*
|--------------------------------------------------------------------------
| PASSWORD RESET
|--------------------------------------------------------------------------
*/
$router->get('/forgot-password', UserController::class, 'forgotPassword');
$router->post('/forgot-password', UserController::class, 'processForgotPassword');

$router->get('/reset-password', UserController::class, 'resetPassword');
$router->post('/reset-password', UserController::class, 'processResetPassword');

/*
|--------------------------------------------------------------------------
| PAYMENT ROUTES
|--------------------------------------------------------------------------
*/
$router->post('/create-checkout-session', OrderController::class, 'createCheckoutSession');
$router->post('/create-payment-intent', OrderController::class, 'createPaymentIntent');
$router->post('/stripe-finalize-payment', OrderController::class, 'finalizeStripePayment');
$router->post('/stripe-webhook', OrderController::class, 'stripeWebhook');

$router->get('/checkout-success', OrderController::class, 'checkoutSuccess');
$router->get('/checkout-cancel', OrderController::class, 'checkoutCancel');
$router->get('/stripe-return', OrderController::class, 'stripeReturn');

$router->post('/api/orders', OrderController::class, 'createPaypalOrder');
$router->post('/api/orders/{orderId}/capture', OrderController::class, 'capturePaypalOrder');
$router->get('/paypal-return', OrderController::class, 'paypalReturn');

/*
|--------------------------------------------------------------------------
| ADMIN AUTH
|--------------------------------------------------------------------------
*/
$router->get('/admin/login', AdminController::class, 'login');
$router->post('/admin/login', AdminController::class, 'processLogin');
$router->get('/admin/logout', AdminController::class, 'logout');

/*
|--------------------------------------------------------------------------
| ADMIN ORDERS
|--------------------------------------------------------------------------
*/
$router->get('/admin/orders', AdminController::class, 'orders');
$router->get('/admin/orders/new', AdminController::class, 'newOrder');
$router->get('/admin/orders/availability', AdminController::class, 'newOrderAvailability');
$router->get('/admin/orders/details', OrderController::class, 'ajaxOrderDetails');

$router->post('/admin/orders/approve', AdminController::class, 'approveOrder');
$router->post('/admin/orders/reject', AdminController::class, 'rejectOrder');
$router->post('/admin/orders/paid', AdminController::class, 'markAsPaid');
$router->post('/admin/orders/complete', OrderController::class, 'completeOrder');
$router->post('/admin/orders/cancel', OrderController::class, 'cancelOrder');

$router->post('/admin/orders/new', AdminController::class, 'processNewOrder');

/*
|--------------------------------------------------------------------------
| ADMIN MODULES
|--------------------------------------------------------------------------
*/
$router->get('/admin/reservations', AdminController::class, 'reservations');

$router->get('/admin/locations', LocationsController::class, 'index');
$router->post('/admin/locations', LocationsController::class, 'handlePost');

$router->get('/admin/customers', CustomerController::class, 'index');

$router->get('/admin/categories', CategoryController::class, 'index');
$router->post('/admin/categories/save', CategoryController::class, 'save');

$router->get('/admin/rental-prices', RentalPriceController::class, 'index');
$router->post('/admin/rental-prices/save', RentalPriceController::class, 'save');
$router->get('/admin/scooters-for-sale', ProductController::class, 'scootersForSale');
$router->post('/admin/scooters-for-sale/save', ProductController::class, 'saveScootersForSale');

/*
|--------------------------------------------------------------------------
| PRODUCTS / SCOOTERS
|--------------------------------------------------------------------------
*/
$router->get('/admin/products', ProductController::class, 'index');
$router->get('/admin/products/new', ProductController::class, 'create');
$router->post('/admin/products/new', ProductController::class, 'store');
$router->post('/admin/products/save', ProductController::class, 'save');
$router->post('/admin/products/delete', ProductController::class, 'delete');

$router->get('/admin/scooters', ScooterController::class, 'index');
$router->get('/admin/scooters/new', ScooterController::class, 'create');
$router->post('/admin/scooters/new', ScooterController::class, 'store');
$router->get('/admin/scooters/edit', ScooterController::class, 'edit');
$router->post('/admin/scooters/edit', ScooterController::class, 'update');
$router->post('/admin/scooters/delete', ScooterController::class, 'delete');
$router->post('/admin/scooters/save', ScooterController::class, 'save');
$router->get('/admin/scooters/list', ScooterController::class, 'listByProduct');

/*
|--------------------------------------------------------------------------
| PRODUCT VARIATIONS
|--------------------------------------------------------------------------
*/
$router->get('/admin/product-variations', ProductController::class, 'listProductVariations');
$router->get('/admin/product-variations/new', ProductController::class, 'addProductVariation');
$router->post('/admin/product-variations/new', ProductController::class, 'addProductVariation');
$router->post('/admin/product-variations/save', ProductController::class, 'saveProductVariations');
$router->get('/admin/api/product-variations', ProductController::class, 'apiProductVariations');

/*
|--------------------------------------------------------------------------
| TESTIMONIALS
|--------------------------------------------------------------------------
*/
$router->get('/admin/testimonials', AdminController::class, 'testimonials');
$router->get('/admin/testimonials/add', AdminController::class, 'addTestimonial');
$router->post('/admin/testimonials/add', AdminController::class, 'addTestimonial');
$router->get('/admin/testimonials/edit', AdminController::class, 'editTestimonial');
$router->post('/admin/testimonials/edit', AdminController::class, 'editTestimonial');
$router->post('/admin/testimonials/delete', AdminController::class, 'deleteTestimonial');

/*
|--------------------------------------------------------------------------
| TIPS & TROUBLESHOOTING
|--------------------------------------------------------------------------
*/
$router->get('/admin/tips-troubleshooting', AdminController::class, 'tipsTroubleshooting');
$router->post('/admin/tips-troubleshooting/section', AdminController::class, 'saveTipsTroubleshootingSection');
$router->post('/admin/tips-troubleshooting/articles/add', AdminController::class, 'addTipsTroubleshootingArticle');
$router->post('/admin/tips-troubleshooting/articles/update', AdminController::class, 'updateTipsTroubleshootingArticle');
$router->post('/admin/tips-troubleshooting/articles/delete', AdminController::class, 'deleteTipsTroubleshootingArticle');

/*
|--------------------------------------------------------------------------
| ADMIN USERS
|--------------------------------------------------------------------------
*/
$router->get('/admin/admins', AdminController::class, 'admins');
$router->get('/admin/admins/add', AdminController::class, 'addAdmin');
$router->post('/admin/admins/add', AdminController::class, 'addAdmin');
$router->get('/admin/admins/edit', AdminController::class, 'editAdmin');
$router->post('/admin/admins/edit', AdminController::class, 'editAdmin');
$router->post('/admin/admins/delete', AdminController::class, 'deleteAdmin');

/*
|--------------------------------------------------------------------------
| ADMIN DASHBOARD
|--------------------------------------------------------------------------
*/
$router->get('/admin/featured-products', AdminController::class, 'featuredProducts');
$router->post('/admin/featured-products', AdminController::class, 'featuredProducts');

/*
|--------------------------------------------------------------------------
| RUN ROUTER
|--------------------------------------------------------------------------
*/


$router->dispatch();

