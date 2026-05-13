<?php
// admin/testimonials.php
// $testimonials is passed from the controller

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = strtolower($_SESSION['admin_role'] ?? '');
$isStaff = ($role === 'staff');
?>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Testimonials</h1>
        <?php if (!$isStaff): ?>
            <a href="/admin/testimonials/add" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Testimonial</a>
        <?php else: ?>
            <span class="text-sm text-gray-500">Staff account: view only</span>
        <?php endif; ?>
    </div>
    <table class="min-w-full bg-white border border-gray-200 rounded shadow">
        <thead>
            <tr>
                <th class="py-2 px-4 border-b">Reviewer</th>
                <th class="py-2 px-4 border-b">Review</th>
                <th class="py-2 px-4 border-b">Stars</th>
                <th class="py-2 px-4 border-b">Date</th>
                        <th class="py-2 px-4 border-b">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($testimonials)): ?>
                <?php foreach ($testimonials as $t): ?>
                    <tr>
                        <td class="py-2 px-4 border-b font-semibold"><?= htmlspecialchars($t['reviewer_name']) ?></td>
                        <td class="py-2 px-4 border-b max-w-xs break-words"><?= nl2br(htmlspecialchars($t['review_text'])) ?></td>
                        <td class="py-2 px-4 border-b">
                            <?php for ($i = 0; $i < $t['star_rating']; $i++): ?>
                                <span class="text-yellow-400">&#9733;</span>
                            <?php endfor; ?>
                            <?php for ($i = $t['star_rating']; $i < 5; $i++): ?>
                                <span class="text-gray-300">&#9733;</span>
                            <?php endfor; ?>
                        </td>
                        <td class="py-2 px-4 border-b text-sm text-gray-500">
                            <?= isset($t['created_at']) ? htmlspecialchars($t['created_at']) : '' ?>
                        </td>
                        <td class="py-2 px-4 border-b">
                            <?php if (!$isStaff): ?>
                                <a href="/admin/testimonials/edit?id=<?= $t['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                                <form action="/admin/testimonials/delete" method="POST" style="display:inline;" onsubmit="return confirm('Delete this testimonial?');">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">View only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="py-4 text-center text-gray-500">No testimonials found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
