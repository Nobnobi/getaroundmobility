
<div class="flex flex-col md:flex-row flex-1 min-h-[500px] bg-white mt-32 md:mt-0">
    <!-- CONTACT PICTURE -->
    <div class="w-full md:w-1/2 flex items-center justify-center bg-gray-100 p-6 md:p-0 hidden md:flex">
        <img src="/img/contact-us.JPG" alt="Contact Illustration" class="w-3/4 max-w-xs md:max-w-full md:max-h-[1000px] object-contain mx-auto">
    </div>

    <!-- Contact Form -->
    <div class="w-full md:w-1/2 flex items-center justify-center bg-white p-4 md:p-0">
        <div class="w-full max-w-md p-4 md:p-8 bg-white rounded-lg shadow-lg mx-auto mt-4 md:mt-0">
            <div class="text-center mb-6">
                <img src="/img/Original logo.svg" alt="Your Logo" class="mx-auto max-h-16 h-12">
            </div>
            <h1 class="text-2xl font-bold text-center mb-4 font-[Barlow]">Contact Us</h1>
            <p class="text-center text-gray-500 mb-6 font-sans">Our friendly team would love to hear from you</p>

            <!-- Display success message if exists -->
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 text-green-700 p-2 rounded mb-4 text-center break-words"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <!-- Display error message if exists -->
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 text-red-700 p-2 rounded mb-4 text-center break-words"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/contact-submit" class="space-y-4 md:space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Your Name</label>
                    <input type="text" name="name" required placeholder="Your name"
                        class="h-12 px-3 mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Contact Number <span class="text-gray-400 text-xs">(optional)</span></label>
                    <input type="tel" name="contact_number" placeholder="Your contact number"
                        class="h-12 px-3 mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Your Email</label>
                    <input type="email" name="email" required placeholder="Your email"
                        class="h-12 px-3 mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Subject</label>
                    <input type="text" name="subject" required placeholder="Subject"
                        class="h-12 px-3 mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea name="message" required rows="6" placeholder="Type your message here..."
                        style="resize: none;" 
                        class="px-3 py-3 mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                <button type="submit" class="w-full bg-[#0086C9] cursor-pointer text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Send Message</button>
            </form>
        </div>
    </div>
</div>