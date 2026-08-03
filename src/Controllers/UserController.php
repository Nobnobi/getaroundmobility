<?php
namespace App\Controllers;
use App\Controller;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class UserController extends Controller{
    private const PASSWORD_POLICY_PATTERN = '/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]).{8,}$/';

    private function appBaseUrl(): string
    {
        $envUrl = getenv('APP_URL');
        if ($envUrl === false || trim((string)$envUrl) === '') {
            $envUrl = $_ENV['APP_URL'] ?? '';
        }

        $base = trim((string)$envUrl);
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $base = $scheme . '://' . $host;
        }

        return rtrim($base, '/');
    }

    private function isStrongPassword(string $password): bool
    {
        return (bool)preg_match(self::PASSWORD_POLICY_PATTERN, $password);
    }

    private function sanitizeReturnUrl($returnUrl): string
    {
        $value = trim((string)$returnUrl);
        if ($value === '') {
            return '/profile';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '//')) {
            return '/profile';
        }

        if (!str_starts_with($value, '/')) {
            return '/profile';
        }

        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            return '/profile';
        }

        return $value;
    }

    public function login() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $returnUrl = $this->sanitizeReturnUrl($_GET['return'] ?? '/profile');
        $error = $_SESSION['login_error'] ?? '';
        unset($_SESSION['login_error']);
        $success = $_SESSION['logout_success'] ?? '';
        unset($_SESSION['logout_success']);

        // Use your layout system to render the view
        $this->render('login', [
            'returnUrl' => $returnUrl,
            'error' => $error,
            'success' => $success,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }

    public function processLogin() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $returnUrl = $this->sanitizeReturnUrl($_POST['return'] ?? '/profile');

        if (!$email) {
            $_SESSION['login_error'] = "Invalid email address.";
            header('Location: /login?return=' . urlencode($returnUrl));
            exit;
        }

        // Use UserModel for DB logic
        $userModel = new \App\Models\UserModel();
        $user = $userModel->getUserByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            $_SESSION['name'] = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
            header('Location: ' . $returnUrl);
            exit;
        } else {
            $_SESSION['login_error'] = "Invalid email or password.";
            header('Location: /login?return=' . urlencode($returnUrl));
            exit;
        }
    }

    public function profile() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->requireLogin();

        $userId = $_SESSION['user_id'];
        $userModel = new \App\Models\UserModel();
        $user = $userModel->getUserById($userId);

        // Fetch paginated orders for this user
        $orderModel = new \App\Models\OrderModel();
        $perPage = 5;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $orders_count = $orderModel->countOrdersByUserId($userId);
        $totalPages = (int)ceil($orders_count / $perPage);
        $page = min($page, max(1, $totalPages));
        $orders = $orderModel->getOrdersByUserIdPaginated($userId, $page, $perPage);

        $orderItems = [];
        foreach ($orders as $order) {
            $orderItems[$order['order_id']] = $orderModel->getOrderItems($order['order_id']);
        }

        $is_own_profile = true;

        // Determine if in edit mode
        $editing = isset($_GET['edit']) && $_GET['edit'] == '1';

        $this->render('profile', [
            'user' => $user,
            'orders' => $orders,
            'orders_count' => $orders_count,
            'orderItems' => $orderItems,
            'is_own_profile' => $is_own_profile,
            'editing' => $editing,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }

        if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            $_SESSION['login_error'] = 'Invalid request.';
            header('Location: /login');
            exit;
        }

        session_unset();
        session_destroy();
        // Optionally set a success message
        session_start(); // Start a new session to store the message
        $_SESSION['logout_success'] = 'You have been logged out successfully.';
        header('Location: /login');
        exit;
    }

    public function register() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $error = $_SESSION['register_error'] ?? '';
        unset($_SESSION['register_error']);

        // Use your layout system to render the view
        $this->render('register', [
            'error' => $error,
            'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32))
        ]);
    }

    public function processRegister() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // 1. CSRF PROTECTION
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }

        // 2. SANITIZE INPUTS
        $first_name = htmlspecialchars(trim($_POST['first_name'] ?? ''));
        $last_name = htmlspecialchars(trim($_POST['last_name'] ?? ''));
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $address = htmlspecialchars(trim($_POST['address'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // 3. BASIC VALIDATION
        if (!$email) {
            $_SESSION['register_error'] = "Invalid email address.";
            header("Location: /register");
            exit;
        }

        // 4. PASSWORD REQUIREMENTS (BACKEND SECURITY)
        if (!$this->isStrongPassword($password)) {
            $_SESSION['register_error'] = "Password must be at least 8 characters, include an uppercase letter, a number, and a special character.";
            header("Location: /register");
            exit;
        }

        // 5. CONFIRM PASSWORD MATCH
        if ($password !== $confirm) {
            $_SESSION['register_error'] = "Passwords do not match.";
            header("Location: /register");
            exit;
        }

        // 6. HASH PASSWORD BEFORE SAVE
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $userModel = new \App\Models\UserModel();

        // 7. CHECK IF EMAIL ALREADY EXISTS
        if ($userModel->emailExists($email)) {
            $_SESSION['register_error'] = "Email already exists. Please use another.";
            header("Location: /register");
            exit;
        }

        // 8. CREATE USER
        $userId = $userModel->createUser($first_name, $last_name, $email, $phone, $address, $hashedPassword);

        // 9. SEND WELCOME EMAIL (your existing code)
        // Send welcome email using PHPMailer (same as your current code)
        require_once __DIR__ . '/../../vendor/autoload.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? null);
            $mail->Password = getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? null);
            $mail->SMTPSecure = 'tls';
            $mail->Port = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587);

            $fromEmail = getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? ($mail->Username));
            $fromName = getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'Get Around Mobility');
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email, $first_name . ' ' . $last_name);

            $mail->Subject = "Welcome to Get Around Mobility! You're Ready to Roll!";
            $templatePath = __DIR__ . '/../Views/emails/welcome_email.html';
            $template = file_get_contents($templatePath);
            $baseUrl = $this->appBaseUrl();
            $dashboardUrl = $baseUrl . "/profile";
            $verificationUrl = $baseUrl . "/verify-email?email=" . urlencode($email);
            $howItWorksUrl = $baseUrl . "/how-it-works";
            $helpUrl = $baseUrl . "/help";
            $unsubscribeUrl = $baseUrl . "/unsubscribe?email=" . urlencode($email);
            $userName = htmlspecialchars($first_name . ' ' . $last_name);

            $search = ['{{name}}','{{verification_link}}','{{promo_code}}','{{logo_url}}','{{dashboard_link}}','{{how_it_works_link}}','{{help_link}}','{{unsubscribe_link}}','{{year}}'];
            $replace = [$userName, $verificationUrl, 'WELCOME10', 'https://example.com/assets/logo.png', $dashboardUrl, $howItWorksUrl, $helpUrl, $unsubscribeUrl, date('Y')];
            $bodyHtml = str_replace($search, $replace, $template);

            $mail->Body = $bodyHtml;
            $mail->isHTML(true);
            $mail->AltBody = "Welcome to Get Around Mobility, $userName!\n\nConfirm your account: $verificationUrl\nPromo: WELCOME10\n\nSupport: support@getaroundmobility.com\n";

            $mail->send();
        } catch (\Exception $e) {
            error_log("Registration Mailer Error: {$mail->ErrorInfo}");
        }
        // (Your PHPMailer block stays the same)

        // 10. AUTO LOGIN
        $_SESSION['user_id'] = $userId;
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;

        header("Location: /profile");
        exit;
    }

    public function forgotPassword() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $error = $_SESSION['forgot_error'] ?? '';
        unset($_SESSION['forgot_error']);
        $success = $_SESSION['forgot_success'] ?? '';
        unset($_SESSION['forgot_success']);

        $this->render('forgot-password', [
            'csrf_token' => $_SESSION['csrf_token'],
            'error' => $error,
            'success' => $success
        ]);
    }

    public function processForgotPassword() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                $error = 'Invalid CSRF token';
            } else {
                $limitStatus = $this->checkPublicRateLimit('forgot_password', 5, 30);
                if (!($limitStatus['allowed'] ?? true)) {
                    $error = $limitStatus['message'] ?? 'Too many password reset requests. Please try again later.';
                } else {
                    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                    if (!$email) {
                        $error = 'Please enter a valid email address.';
                    } else {
                        $userModel = new \App\Models\UserModel();
                        $userExists = $userModel->emailExists($email);

                        if ($userExists) {
                            $token = bin2hex(random_bytes(32));
                            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                            $userModel->createPasswordReset($email, $token, $expiry);

                            $resetLink = $this->appBaseUrl() . "/reset-password?token=" . urlencode($token);

                            require_once __DIR__ . '/../../vendor/autoload.php';
                            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
                                $mail->SMTPAuth = true;
                                $mail->Username = getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? null);
                                $mail->Password = getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? null);
                                $mail->SMTPSecure = 'tls';
                                $mail->Port = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587);

                                $fromEmail = getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? ($mail->Username));
                                $fromName = getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'Get Around Mobility');
                                $mail->setFrom($fromEmail, $fromName);
                                $mail->addAddress($email);

                                $mail->Subject = 'Password Reset Request';
                                $mail->Body = "Hello,\n\nTo reset your password, click the link below:\n$resetLink\n\nThis link will expire in 1 hour.\n\nIf you did not request a password reset, kindly contact our support team.";

                                $mail->send();
                            } catch (\Exception $e) {
                                error_log("Password Reset Mailer Error: {$mail->ErrorInfo}");
                            }

                            $success = 'If the email is registered, a password reset link has been sent.';
                        } else {
                            $success = 'If the email is registered, a password reset link has been sent.';
                        }
                    }
                }
            }
        }

        $this->render('forgot-password', [
            'csrf_token' => $_SESSION['csrf_token'],
            'error' => $error,
            'success' => $success
        ]);
    }

    public function resetPassword() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $error = $_SESSION['reset_error'] ?? '';
        unset($_SESSION['reset_error']);
        $success = $_SESSION['reset_success'] ?? '';
        unset($_SESSION['reset_success']);

        $this->render('reset-password', [
            'csrf_token' => $_SESSION['csrf_token'],
            'error' => $error,
            'success' => $success
        ]);
    }

    public function processResetPassword() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                $error = 'Invalid CSRF token';
            } else {
                $token = $_POST['token'] ?? '';
                $password = $_POST['password'] ?? '';
                $userModel = new \App\Models\UserModel();

                if (!$this->isStrongPassword($password)) {
                    $error = 'Password must be at least 8 characters, include an uppercase letter, a number, and a special character.';
                    $this->render('reset-password', [
                        'csrf_token' => $_SESSION['csrf_token'],
                        'error' => $error,
                        'success' => $success
                    ]);
                    return;
                }

                // Clean up expired tokens
                $userModel->cleanExpiredPasswordResets();

                $row = $userModel->getPasswordResetByToken($token);
                if (!$row || strtotime($row['expires_at']) < time()) {
                    $error = 'Invalid or expired token.';
                } else {
                    // Update user's password
                    $userModel->updateUserPassword($row['email'], $password);
                    // Delete the token
                    $userModel->deletePasswordResetToken($token);
                    $success = 'Your password has been reset. You can now log in.';
                }
            }
        }

        $this->render('reset-password', [
            'csrf_token' => $_SESSION['csrf_token'],
            'error' => $error,
            'success' => $success
        ]);
    }

    // private function getPDO() {
    //     return new \PDO('mysql:host=localhost;dbname=getaround_db', 'getaroundmobility', 'itup420');
    // }

    private function requireLogin() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }

    public function contactSubmit(){
        if (session_status() === PHP_SESSION_NONE) session_start();


        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                $error = 'Invalid CSRF token.';
            } else {
                $limitStatus = $this->checkPublicRateLimit('contact_submit', 5, 30);
                if (!($limitStatus['allowed'] ?? true)) {
                    $error = $limitStatus['message'] ?? 'Too many contact form submissions. Please try again later.';
                } else {
                    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
                    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
                    $contact_number = htmlspecialchars(trim($_POST['contact_number'] ?? ''));
                    $subject = htmlspecialchars(trim($_POST['subject'] ?? ''));
                    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

                    if (!$name || !$email || !$subject || !$message) {
                        $error = 'Please fill in all fields.';
                    } else {
                        require_once __DIR__ . '/../../vendor/autoload.php';
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
                            $mail->SMTPAuth = true;
                            $mail->Username = getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? null);
                            $mail->Password = getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? null);
                            $mail->SMTPSecure = 'tls';
                            $mail->Port = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587);

                            // Correct contact form behavior
                            $mail->setFrom($email, 'Get Around Mobility Contact Form');
                            $mail->addAddress(getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? null), 'Site Admin');
                            $mail->addReplyTo($email, $name);

                            $mail->Subject = $subject;
                            $body = '';
                            $body .= '<strong>Name:</strong> ' . htmlspecialchars($name) . '<br>';
                            if ($contact_number) {
                                $body .= '<strong>Contact Number:</strong> ' . htmlspecialchars($contact_number) . '<br>';
                            }
                            $body .= '<strong>Email:</strong> ' . htmlspecialchars($email) . '<br>';
                            $body .= '<strong>Message:</strong><br>' . nl2br($message);
                            $mail->Body = $body;
                            $mail->isHTML(true);

                            $mail->send();
                            $success = 'Thank you for contacting us! We will get back to you soon.';
                        } catch (\Exception $e) {
                            $error = 'Failed to send message. Please try again later.';
                            error_log("Contact Mailer Error: {$mail->ErrorInfo}");
                        }
                    }
                }
            }
        }

        $this->render('contact', [
            'csrf_token' => $_SESSION['csrf_token'],
            'error' => $error,
            'success' => $success
        ]);
    }

    public function updateProfile() {
        $this->requireLogin();
        $userId = $_SESSION['user_id'];
        $userModel = new \App\Models\UserModel();
        $data = [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? ''
        ];
        $userModel->updateUser($userId, $data);
        // Update session values for immediate UI feedback
        $_SESSION['first_name'] = $data['first_name'];
        $_SESSION['last_name'] = $data['last_name'];
        header('Location: /profile');
        exit;
    }
}

