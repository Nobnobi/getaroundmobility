<?php
// admin/add_testimonial.php
// $error is passed from the controller
?>
<div class="container mx-auto px-4 py-8 max-w-lg">
    <h1 class="text-2xl font-bold mb-6">Add Testimonial</h1>
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <form action="/admin/testimonials/add" method="POST" class="space-y-4">
        <div>
            <label class="block font-semibold mb-1">Reviewer Name</label>
            <input type="text" name="reviewer_name" class="w-full border rounded px-3 py-2" required>
        </div>
        <div>
            <label class="block font-semibold mb-1">Review Text</label>
            <textarea name="review_text" class="w-full border rounded px-3 py-2" rows="4" required></textarea>
        </div>
        <div>
            <label class="block font-semibold mb-1">Star Rating</label>
            <select name="star_rating" class="w-full border rounded px-3 py-2" required>
                <option value="5">5 Stars</option>
                <option value="4">4 Stars</option>
                <option value="3">3 Stars</option>
                <option value="2">2 Stars</option>
                <option value="1">1 Star</option>
            </select>
        </div>
        <div class="flex justify-end gap-2">
            <a href="/admin/testimonials" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Cancel</a>
            <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Add</button>
        </div>
    </form>
</div>
