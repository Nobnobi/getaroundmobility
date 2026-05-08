<?php
// No session_start() here; handled by controller
$error = $error ?? '';
?>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center mt-26">
        <div class="w-full max-w-md mx-auto">
            <div class="bg-white p-8 rounded shadow-md w-full">
                <h2 class="text-2xl font-bold mb-6 text-center">Create an Account</h2>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4 text-center"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/register" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">First Name</label>
                        <input type="text" name="first_name" class="border rounded px-3 py-2 w-full" required>
                    </div>

                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Last Name</label>
                        <input type="text" name="last_name" class="border rounded px-3 py-2 w-full" required>
                    </div>

                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Email</label>
                        <input type="email" name="email" class="border rounded px-3 py-2 w-full" required>
                    </div>

                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Phone</label>
                        <input type="text" name="phone" class="border rounded px-3 py-2 w-full" required>
                    </div>

                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Address</label>
                        <input type="text" name="address" class="border rounded px-3 py-2 w-full" required>
                    </div>

                    <!-- PASSWORD FIELD -->
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Password</label>
                        <input type="password" name="password" class="border rounded px-3 py-2 w-full" required>
                    </div>

                    <!-- CONFIRM PASSWORD -->
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Confirm Password</label>
                        <input type="password" name="confirm_password" class="border rounded px-3 py-2 w-full" required>
                    </div>

                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 cursor-pointer rounded hover:bg-blue-700 w-full">Register</button>
                </form>

                <div class="mt-4 text-center">
                    Already have an account? <a href="/login" class="text-blue-500 hover:underline">Log in</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        const passwordInput = document.querySelector('input[name="password"]');
        const confirmInput = document.querySelector('input[name="confirm_password"]');
        const submitBtn = document.querySelector('button[type="submit"]');
        const form = document.getElementById('registerForm');

        // Create error elements
        const passwordError = document.createElement('div');
        passwordError.className = "text-red-600 text-sm mt-1";
        passwordError.style.display = "none"; // HIDDEN ON LOAD
        passwordInput.parentNode.appendChild(passwordError);

        const confirmError = document.createElement('div');
        confirmError.className = "text-red-600 text-sm mt-1";
        confirmError.style.display = "none"; // HIDDEN ON LOAD
        confirmInput.parentNode.appendChild(confirmError);

        // Regex rule
        const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

        // VALIDATE PASSWORD
        function validatePassword(showMessage = true) {
            const password = passwordInput.value;

            if (!regex.test(password)) {
                if (showMessage) {
                    passwordError.style.display = "block";
                    passwordError.textContent = "At least 8 characters, one uppercase, one number, one special character.";
                }
                return false;
            }

            passwordError.style.display = "none";
            return true;
        }

        // VALIDATE CONFIRM
        function validateConfirm(showMessage = true) {
            if (confirmInput.value !== passwordInput.value) {
                if (showMessage) {
                    confirmError.style.display = "block";
                    confirmError.textContent = "Passwords do not match.";
                }
                return false;
            }

            confirmError.style.display = "none";
            return true;
        }

        // Show message only when user clicks password field
        passwordInput.addEventListener("focus", () => {
            if (!regex.test(passwordInput.value)) {
                passwordError.style.display = "block";
                passwordError.textContent = "At least 8 characters, one uppercase, one number, one special character.";
            }
        });

        // Hide message when unfocused & empty
        passwordInput.addEventListener("blur", () => {
            if (passwordInput.value.trim() === "") {
                passwordError.style.display = "none";
            }
        });

        // LIVE CHECKING
        passwordInput.addEventListener("input", () => validatePassword(true));
        confirmInput.addEventListener("input", () => validateConfirm(true));

        // FORM SUBMIT
        form.addEventListener('submit', function (e) {
            const validPass = validatePassword(true);
            const validConfirm = validateConfirm(true);

            if (!validPass || !validConfirm) {
                e.preventDefault();

                // Scroll to error
                passwordInput.scrollIntoView({ behavior: "smooth", block: "center" });
            }
        });
    </script>