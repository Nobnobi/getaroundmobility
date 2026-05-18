<?php
// admin/tips_troubleshooting.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = strtolower($_SESSION['admin_role'] ?? '');
$isStaff = ($role === 'staff');
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
                <?php
                $sectionImagePaths = $section['image_paths'] ?? [];
                if (empty($sectionImagePaths)) {
                    $fallbackImage = $section['image_path'] ?? '/img/pwd1.svg';
                    $sectionImagePaths = [$fallbackImage];
                }
                $sectionImagePaths = array_slice(array_values($sectionImagePaths), 0, 5);
                ?>
                <input type="hidden" name="current_image_paths" value="<?= htmlspecialchars(json_encode($sectionImagePaths), ENT_QUOTES, 'UTF-8') ?>">
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
                    <label class="block text-sm font-semibold text-[#062B41] mb-4">Upload New Images (up to 3)</label>
                    <input type="file" id="tipsImageInput" name="images[]" accept=".jpg,.jpeg,.png,.webp,.svg" class="sr-only" multiple>
                    <div class="flex items-center gap-3">
                        <button type="button" id="tipsImageBrowseBtn" class="px-6 py-2 rounded-lg bg-[#0086C9] text-white font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md">
                            Choose Images
                        </button>
                        <span id="tipsImageFileName" class="text-sm text-gray-600">No files chosen.</span>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Selecting new files replaces the current slideshow images.</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                    <p class="block text-sm font-semibold text-[#062B41] mb-4">Current Images</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                        <?php foreach ($sectionImagePaths as $path): ?>
                            <div class="border-2 border-gray-300 rounded-lg p-2 bg-white flex items-center justify-center min-h-[90px]">
                                <img src="<?= htmlspecialchars($path) ?>" alt="<?= htmlspecialchars($section['image_alt'] ?? '') ?>" class="max-h-24 w-auto object-contain">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex justify-end pt-2">
                    <?php if (!$isStaff): ?>
                        <button type="submit" class="bg-[#0086C9] text-white px-8 py-3 rounded-lg font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md">Save Section</button>
                    <?php else: ?>
                        <span class="text-sm text-gray-500">Staff account: section is view only</span>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-2 h-8 bg-[#0086C9] rounded-full"></div>
                <h2 class="text-2xl font-bold text-[#062B41]">Add Article</h2>
            </div>
            <form action="/admin/tips-troubleshooting/articles/add" method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Title</label>
                    <input type="text" name="title" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Article Image (Optional)</label>
                    <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                        <input type="file" id="articleImageInputNew" name="article_image" accept=".jpg,.jpeg,.png,.webp,.svg" class="sr-only js-article-image-input" data-file-name-target="articleImageFileNameNew">
                        <div class="flex items-center gap-3">
                            <button type="button" id="articleImageBrowseNew" class="px-6 py-2 rounded-lg bg-[#0086C9] text-white font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md js-article-image-browse" data-target-input="articleImageInputNew">
                                Choose Image
                            </button>
                            <span id="articleImageFileNameNew" class="text-sm text-gray-600">No files chosen.</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Recommended: landscape image, at least 1200x700.</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Description</label>
                    <textarea name="description" rows="5" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" required></textarea>
                    <p class="mt-2 text-xs text-gray-500">
                        Formatting supported: <span class="font-semibold">*header size*</span>, <span class="font-semibold">*bold*text*end of bold*</span>, <span class="font-semibold">*bullet*</span>, numbered lines like <span class="font-semibold">1.</span>, and links like <span class="font-semibold">[label](https://example.com)</span>.
                    </p>
                </div>
                <div class="flex justify-end pt-2">
                    <?php if (!$isStaff): ?>
                        <button type="submit" class="bg-[#0086C9] text-white px-8 py-3 rounded-lg font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md">Add Article</button>
                    <?php else: ?>
                        <span class="text-sm text-gray-500">Staff account: article creation disabled</span>
                    <?php endif; ?>
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
                            <form action="/admin/tips-troubleshooting/articles/update" method="POST" enctype="multipart/form-data" class="space-y-5">
                                <input type="hidden" name="id" value="<?= (int) $article['id'] ?>">
                                <input type="hidden" name="page" value="<?= (int) $page ?>">
                                <input type="hidden" name="current_image_path" value="<?= htmlspecialchars((string)($article['image_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-semibold text-[#062B41] mb-2">Title</label>
                                        <input type="text" name="title" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" value="<?= htmlspecialchars($article['title']) ?>" required>
                                    </div>
                                    <div class="flex items-end pb-1">
                                        <label class="flex items-center gap-3 cursor-pointer select-none">
                                            <div class="relative">
                                                <input type="checkbox" name="is_featured_placeholder" class="sr-only peer" disabled>
                                                <!-- Featured is toggled via separate form below -->
                                            </div>
                                            <span class="text-sm font-semibold text-[#062B41]">
                                                <?php if (!empty($article['is_featured'])): ?>
                                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-[#0086C9] text-white text-xs font-bold">&#9733; Featured</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-gray-200 text-gray-500 text-xs font-semibold">Not Featured</span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-[#062B41] mb-2">Description</label>
                                    <textarea name="description" rows="4" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#0086C9] focus:border-transparent transition" required><?= htmlspecialchars($article['description']) ?></textarea>
                                    <p class="mt-2 text-xs text-gray-500">
                                        Supports: <span class="font-semibold">*header size*</span>, <span class="font-semibold">*bold*text*end of bold*</span>, <span class="font-semibold">*bullet*</span>, numbered lines, and <span class="font-semibold">[label](https://example.com)</span> links.
                                    </p>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2 items-start">
                                    <div>
                                        <label class="block text-sm font-semibold text-[#062B41] mb-2">Replace Article Image (Optional)</label>
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <input type="file" id="articleImageInput-<?= (int) $article['id'] ?>" name="article_image" accept=".jpg,.jpeg,.png,.webp,.svg" class="sr-only js-article-image-input" data-file-name-target="articleImageFileName-<?= (int) $article['id'] ?>">
                                            <div class="flex items-center gap-3">
                                                <button type="button" class="px-5 py-2 rounded-lg bg-[#0086C9] text-white font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md js-article-image-browse" data-target-input="articleImageInput-<?= (int) $article['id'] ?>">
                                                    Choose Image
                                                </button>
                                                <span id="articleImageFileName-<?= (int) $article['id'] ?>" class="text-sm text-gray-600">No files chosen.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-[#062B41] mb-2">Current Image</label>
                                        <?php if (!empty($article['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($article['image_path']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="w-full max-w-xs h-28 object-cover rounded-lg border border-gray-200">
                                        <?php else: ?>
                                            <p class="text-sm text-gray-500">No image uploaded yet.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center gap-3 pt-3 border-t border-gray-200">
                                    <?php if (!$isStaff): ?>
                                        <button
                                            type="submit"
                                            formaction="/admin/tips-troubleshooting/articles/delete"
                                            formmethod="POST"
                                            onclick="return confirm('Delete this article?');"
                                            class="px-6 py-2 rounded-lg bg-red-500 text-white font-semibold hover:bg-red-600 hover:cursor-pointer transition shadow-md"
                                        >Delete</button>
                                        <button type="submit" class="px-6 py-2 rounded-lg bg-[#0086C9] text-white font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md">Save Changes</button>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">Staff account: view only</span>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <?php if (!$isStaff): ?>
                            <!-- Featured toggle form (separate from update form to avoid confusion) -->
                            <form action="/admin/tips-troubleshooting/articles/toggle-featured" method="POST" class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between gap-4">
                                <input type="hidden" name="id" value="<?= (int) $article['id'] ?>">
                                <input type="hidden" name="page" value="<?= (int) $page ?>">
                                <span class="text-sm text-gray-600">Feature this article on the public Tips page?</span>
                                <div class="flex items-center gap-3">
                                    <input type="hidden" name="is_featured" value="0">
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <div class="relative">
                                            <input type="checkbox" name="is_featured" value="1" class="sr-only peer" <?= !empty($article['is_featured']) ? 'checked' : '' ?> onchange="this.closest('form').submit()">
                                            <div class="w-11 h-6 rounded-full transition bg-gray-300 peer-checked:bg-[#0086C9]"></div>
                                            <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-all peer-checked:translate-x-5"></div>
                                        </div>
                                        <span class="text-sm font-semibold <?= !empty($article['is_featured']) ? 'text-[#0086C9]' : 'text-gray-400' ?>">
                                            <?= !empty($article['is_featured']) ? 'Featured' : 'Not Featured' ?>
                                        </span>
                                    </label>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-8 gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>"
                        class="px-4 py-2 rounded-lg border transition font-semibold <?= $i == $page ? 'bg-[#0086C9] text-white border-[#0086C9]' : 'bg-white text-[#0086C9] border-blue-200 hover:bg-blue-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="bg-gray-50 rounded-xl border-2 border-dashed border-gray-300 p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="text-gray-600 text-lg font-semibold">No articles yet</p>
                <p class="text-gray-500 text-sm">Articles you create will appear here</p>
            </div>
        <?php endif; ?>
        </div>

        <!-- Featured Articles Display Order -->
        <?php
        $featuredArticles = array_filter($allArticles ?? [], fn($a) => !empty($a['is_featured']));
        usort($featuredArticles, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
        $nonFeaturedArticles = array_filter($allArticles ?? [], fn($a) => empty($a['is_featured']));
        ?>
        <div class="mt-10">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-2 h-8 bg-[#0086C9] rounded-full"></div>
                <h2 class="text-2xl font-bold text-[#062B41]">Featured Display Order</h2>
            </div>
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
                <p class="text-sm text-gray-500 mb-2">Drag to reorder the articles shown on the public Tips &amp; Troubleshooting page. Only <span class="font-semibold text-[#062B41]">Featured</span> articles appear here. Toggle the Featured switch on an article above to include it.</p>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Maximum of 6 featured articles</p>
                <?php if (!empty($featuredArticles)): ?>
                    <form action="/admin/tips-troubleshooting/articles/featured-order" method="POST" id="featuredOrderForm">
                        <input type="hidden" name="page" value="<?= (int) $page ?>">
                        <ul id="featuredSortable" class="space-y-3 mb-6">
                            <?php foreach ($featuredArticles as $fa): ?>
                                <li class="flex items-center gap-4 bg-gray-50 border border-gray-200 rounded-xl px-5 py-3 cursor-grab active:cursor-grabbing" data-id="<?= (int) $fa['id'] ?>">
                                    <input type="hidden" name="featured_order[]" value="<?= (int) $fa['id'] ?>">
                                    <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                                    <span class="font-semibold text-[#062B41] flex-1"><?= htmlspecialchars($fa['title']) ?></span>
                                    <span class="text-xs text-white bg-[#0086C9] px-2 py-0.5 rounded-full">Featured</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!$isStaff): ?>
                            <div class="flex justify-end">
                                <button type="submit" class="bg-[#0086C9] text-white px-8 py-3 rounded-lg font-semibold hover:bg-[#0073a8] hover:cursor-pointer transition shadow-md">Save Display Order</button>
                            </div>
                        <?php else: ?>
                            <span class="text-sm text-gray-500">Staff account: view only</span>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <p class="font-semibold">No featured articles yet</p>
                        <p class="text-sm mt-1">Toggle the Featured switch on any article above to feature it here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isStaff = <?= $isStaff ? 'true' : 'false' ?>;
    const input = document.getElementById('tipsImageInput');
    const browseBtn = document.getElementById('tipsImageBrowseBtn');
    const fileName = document.getElementById('tipsImageFileName');
    const maxFiles = 5;
    const maxFileSize = 64 * 1024 * 1024;
    const maxTotalSize = 120 * 1024 * 1024;

    if (isStaff) {
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
            });
        });
        document.querySelectorAll('input, textarea, select, button[type="submit"], button[type="button"]').forEach(el => {
            el.disabled = true;
        });
    }

    if (!input || !browseBtn || !fileName) {
        return;
    }

    browseBtn.addEventListener('click', function() {
        input.click();
    });

    input.addEventListener('change', function() {
        if (input.files && input.files.length > 0) {
            if (input.files.length > maxFiles) {
                fileName.textContent = 'Please select at most 5 files.';
                input.value = '';
                return;
            }

            const files = Array.from(input.files);
            const totalSize = files.reduce((sum, file) => sum + file.size, 0);
            const oversizedFile = files.find(file => file.size > maxFileSize);

            if (oversizedFile) {
                fileName.textContent = oversizedFile.name + ' is too large. Each image must be 64 MB or less.';
                input.value = '';
                return;
            }

            if (totalSize > maxTotalSize) {
                fileName.textContent = 'Selected files are too large together. Keep the total under 120 MB.';
                input.value = '';
                return;
            }

            const names = files.map(file => file.name);
            fileName.textContent = names.join(', ');
            return;
        }

        fileName.textContent = 'No files chosen.';
    });

    document.querySelectorAll('.js-article-image-browse').forEach(function(button) {
        button.addEventListener('click', function() {
            const inputId = button.getAttribute('data-target-input');
            const targetInput = inputId ? document.getElementById(inputId) : null;
            if (targetInput) {
                targetInput.click();
            }
        });
    });

    document.querySelectorAll('.js-article-image-input').forEach(function(articleInput) {
        articleInput.addEventListener('change', function() {
            const targetNameId = articleInput.getAttribute('data-file-name-target');
            const targetName = targetNameId ? document.getElementById(targetNameId) : null;
            if (!targetName) {
                return;
            }

            if (articleInput.files && articleInput.files.length > 0) {
                const firstFile = articleInput.files[0];
                if (firstFile.size > maxFileSize) {
                    targetName.textContent = firstFile.name + ' is too large. File must be 64 MB or less.';
                    articleInput.value = '';
                    return;
                }
                targetName.textContent = firstFile.name;
                return;
            }

            targetName.textContent = 'No files chosen.';
        });
    });

    // Drag-and-drop sort for featured display order
    const sortable = document.getElementById('featuredSortable');
    if (sortable) {
        let draggedItem = null;

        sortable.querySelectorAll('li').forEach(function(item) {
            item.setAttribute('draggable', 'true');

            item.addEventListener('dragstart', function(e) {
                draggedItem = item;
                setTimeout(function() { item.style.opacity = '0.4'; }, 0);
            });

            item.addEventListener('dragend', function() {
                draggedItem = null;
                item.style.opacity = '1';
                // Sync hidden inputs with new visual order
                Array.from(sortable.querySelectorAll('li')).forEach(function(li) {
                    li.querySelector('input[type="hidden"]').value = li.getAttribute('data-id');
                });
            });

            item.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (draggedItem && draggedItem !== item) {
                    const rect = item.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    if (e.clientY < midY) {
                        sortable.insertBefore(draggedItem, item);
                    } else {
                        sortable.insertBefore(draggedItem, item.nextSibling);
                    }
                }
            });
        });
    }
});
</script>
