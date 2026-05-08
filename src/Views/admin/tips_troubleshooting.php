<?php
// admin/tips_troubleshooting.php
?>
<div class="container bg-gray-50 px-6 py-10">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-4xl font-bold text-[#062B41] tracking-tight">Tips & Troubleshooting</h1>
        </div>

        <?php if (!empty($success)): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-r-lg mb-6 shadow-sm">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <?= htmlspecialchars($success) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-r-lg mb-6 shadow-sm">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-2 h-8 bg-[#0086C9] rounded-full"></div>
                <h2 class="text-2xl font-bold text-[#062B41]">Section Content</h2>
            </div>
            <form action="/admin/tips-troubleshooting/section" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="current_image_path" value="<?= htmlspecialchars($section['image_path'] ?? '/img/pwd1.svg') ?>">
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Heading</label>
                    <input type="text" name="heading" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" value="<?= htmlspecialchars($section['heading'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Description</label>
                    <textarea name="description" rows="5" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" required><?= htmlspecialchars($section['description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Image Alt Text</label>
                    <input type="text" name="image_alt" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" value="<?= htmlspecialchars($section['image_alt'] ?? '') ?>">
                </div>
                <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                    <label class="block text-sm font-semibold text-[#062B41] mb-4">Upload New Image</label>
                    <input type="file" id="tipsImageInput" name="image" accept=".jpg,.jpeg,.png,.webp,.svg" class="sr-only">
                    <div class="flex items-center gap-3">
                        <button type="button" id="tipsImageBrowseBtn" class="px-6 py-2 rounded-lg bg-[#0086C9] text-white font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md">
                            Choose Image
                        </button>
                        <span id="tipsImageFileName" class="text-sm text-gray-600">No file chosen.</span>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                    <p class="block text-sm font-semibold text-[#062B41] mb-4">Current Image</p>
                    <div class="inline-flex border-2 border-gray-300 rounded-lg p-4 bg-white">
                        <img src="<?= htmlspecialchars($section['image_path'] ?? '/img/pwd1.svg') ?>" alt="<?= htmlspecialchars($section['image_alt'] ?? '') ?>" class="max-h-56 w-auto object-contain">
                    </div>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" class="bg-[#0086C9] text-white px-8 py-3 rounded-lg font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md">Save Section</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-2 h-8 bg-[#0086C9] rounded-full"></div>
                <h2 class="text-2xl font-bold text-[#062B41]">Add Article</h2>
            </div>
            <form action="/admin/tips-troubleshooting/articles/add" method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Title</label>
                    <input type="text" name="title" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Description</label>
                    <textarea name="description" rows="5" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" required></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Link URL</label>
                    <input type="text" name="link_url" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" placeholder="https://example.com/article" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Link Label</label>
                    <input type="text" name="link_label" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" value="Learn more">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Sort Order</label>
                    <input type="number" name="sort_order" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" value="0">
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" class="bg-[#0086C9] text-white px-8 py-3 rounded-lg font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md">Add Article</button>
                </div>
            </form>
        </div>
        </div>

        <div class="mt-10">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-2 h-8 bg-[#0086C9] rounded-full"></div>
            <h2 class="text-2xl font-bold text-[#062B41]">Existing Articles</h2>
        </div>

        <?php if (!empty($articles)): ?>
            <div class="space-y-4">
                <?php foreach ($articles as $article): ?>
                    <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden hover:shadow-lg transition">
                        <div class="border-l-4 border-[#0086C9] p-6">
                            <form action="/admin/tips-troubleshooting/articles/update" method="POST" class="space-y-5">
                                <input type="hidden" name="id" value="<?= (int) $article['id'] ?>">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-semibold text-[#062B41] mb-2">Title</label>
                                        <input type="text" name="title" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" value="<?= htmlspecialchars($article['title']) ?>" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-[#062B41] mb-2">Sort Order</label>
                                        <input type="number" name="sort_order" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" value="<?= (int) $article['sort_order'] ?>">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Description</label>
                                    <textarea name="description" rows="4" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" required><?= htmlspecialchars($article['description']) ?></textarea>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-semibold text-[#062B41] mb-2">Link URL</label>
                                        <input type="text" name="link_url" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" value="<?= htmlspecialchars($article['link_url']) ?>" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-[#062B41] mb-2">Link Label</label>
                                        <input type="text" name="link_label" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" value="<?= htmlspecialchars($article['link_label']) ?>">
                                    </div>
                                </div>
                                <div class="flex justify-between items-center gap-3 pt-3 border-t border-gray-200">
                                    <button
                                        type="submit"
                                        formaction="/admin/tips-troubleshooting/articles/delete"
                                        formmethod="POST"
                                        onclick="return confirm('Delete this article?');"
                                        class="px-6 py-2 rounded-lg bg-red-500 text-white font-semibold hover:bg-red-600 hover:cursor-pointer transition shadow-md"
                                    >Delete</button>
                                    <button type="submit" class="px-6 py-2 rounded-lg bg-[#0086C9] text-white font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-gray-50 rounded-xl border-2 border-dashed border-gray-300 p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="text-gray-600 text-lg font-semibold">No articles yet</p>
                <p class="text-gray-500 text-sm">Articles you create will appear here</p>
            </div>
        <?php endif; ?>
        </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('tipsImageInput');
    const browseBtn = document.getElementById('tipsImageBrowseBtn');
    const fileName = document.getElementById('tipsImageFileName');

    if (!input || !browseBtn || !fileName) {
        return;
    }

    browseBtn.addEventListener('click', function() {
        input.click();
    });

    input.addEventListener('change', function() {
        if (input.files && input.files.length > 0) {
            fileName.textContent = input.files[0].name;
            return;
        }

        fileName.textContent = 'No file chosen.';
    });
});
</script>
