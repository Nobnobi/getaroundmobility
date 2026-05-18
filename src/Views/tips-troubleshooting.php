<?php
$section = $tipsSection ?? null;
$articles = $tipsArticles ?? [];
$selectedArticle = $selectedArticle ?? null;
$isViewingAllArticles = !empty($isViewingAllArticles);
$currentArticlesPage = max(1, (int) ($currentArticlesPage ?? 1));
$articlesTotalPages = max(1, (int) ($articlesTotalPages ?? 1));

if (empty($selectedArticle) && !empty($articles)) {
    $selectedArticle = $articles[0];
}

if (!function_exists('formatTipsArticleInline')) {
    function formatTipsArticleInline(string $text): string
    {
        $line = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');

        // Custom token: *link*Label|https://example.com*end of link*
        $line = preg_replace(
            '/\*link\*(.*?)\|(https?:\/\/[^\s]+)\*end of link\*/i',
            '<a href="$2" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#0086C9] underline underline-offset-2">$1</a>',
            $line
        );

        // Markdown style links: [Label](https://example.com)
        $line = preg_replace(
            '/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/i',
            '<a href="$2" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#0086C9] underline underline-offset-2">$1</a>',
            $line
        );

        // Custom bold tokens and markdown bold
        $line = preg_replace('/\*bold\*(.*?)\*end of bold\*/i', '<strong>$1</strong>', $line);
        $line = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $line);

        // If custom link token has no URL, render as emphasized text.
        $line = preg_replace('/\*link\*(.*?)\*end of link\*/i', '<strong>$1</strong>', $line);

        return $line;
    }
}

if (!function_exists('formatTipsArticleDescription')) {
    function formatTipsArticleDescription(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = explode("\n", $text);
        $html = [];
        $inUnorderedList = false;
        $inOrderedList = false;

        $closeLists = static function () use (&$html, &$inUnorderedList, &$inOrderedList): void {
            if ($inUnorderedList) {
                $html[] = '</ul>';
                $inUnorderedList = false;
            }
            if ($inOrderedList) {
                $html[] = '</ol>';
                $inOrderedList = false;
            }
        };

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                $closeLists();
                continue;
            }

            if (preg_match('/^\*header size\*\s*(.+)$/i', $line, $match) || preg_match('/^#{2,3}\s+(.+)$/', $line, $match)) {
                $closeLists();
                $html[] = '<h3 class="mt-8 mb-3 text-xl font-bold text-slate-900 font-[Barlow]">' . formatTipsArticleInline($match[1]) . '</h3>';
                continue;
            }

            if (preg_match('/^\*bullet\*\s*(.+)$/i', $line, $match) || preg_match('/^[-*]\s+(.+)$/', $line, $match)) {
                if ($inOrderedList) {
                    $html[] = '</ol>';
                    $inOrderedList = false;
                }
                if (!$inUnorderedList) {
                    $html[] = '<ul class="mb-5 list-disc pl-6 text-base leading-8 text-slate-700 sm:text-lg">';
                    $inUnorderedList = true;
                }
                $html[] = '<li>' . formatTipsArticleInline($match[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $line, $match)) {
                if ($inUnorderedList) {
                    $html[] = '</ul>';
                    $inUnorderedList = false;
                }
                if (!$inOrderedList) {
                    $html[] = '<ol class="mb-5 list-decimal pl-6 text-base leading-8 text-slate-700 sm:text-lg">';
                    $inOrderedList = true;
                }
                $html[] = '<li>' . formatTipsArticleInline($match[1]) . '</li>';
                continue;
            }

            $closeLists();
            $html[] = '<p class="mb-4 text-base leading-8 text-slate-700 sm:text-lg">' . formatTipsArticleInline($line) . '</p>';
        }

        $closeLists();

        return implode("\n", $html);
    }
}

if (!function_exists('formatTipsArticlePreview')) {
    function formatTipsArticlePreview(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        $text = preg_replace('/\*header size\*/i', '', $text);
        $text = preg_replace('/\*bold\*(.*?)\*end of bold\*/i', '$1', $text);
        $text = preg_replace('/\*bullet\*/i', '', $text);
        $text = preg_replace('/\*link\*(.*?)\|(https?:\/\/[^\s]+)\*end of link\*/i', '$1', $text);
        $text = preg_replace('/\*link\*(.*?)\*end of link\*/i', '$1', $text);
        $text = preg_replace('/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/i', '$1', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
?>

<div id="tips-page-top"></div>

<section class="relative overflow-hidden bg-gradient-to-b from-[#062B41] via-[#08324b] to-[#f8fafc] text-white">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(0,134,201,0.28),_transparent_42%),radial-gradient(circle_at_left,_rgba(255,255,255,0.1),_transparent_35%)]"></div>
    <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            
            <h1 class="text-4xl font-bold leading-tight sm:text-5xl md:text-6xl font-[Barlow]">
                <?= htmlspecialchars($section['heading'] ?? 'Tips & Troubleshooting') ?>
            </h1>
            <p class="mt-5 max-w-2xl text-base leading-7 text-white/80 sm:text-lg">
                <?= htmlspecialchars($section['description'] ?? 'Helpful articles, troubleshooting notes, and practical guidance all in one place.') ?>
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/#rentalForm" class="inline-flex items-center rounded-full bg-[#0086C9] px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-[#0086C9]/25 transition hover:translate-y-[-1px] hover:bg-[#0a78ae]">
                    Back to rentals
                </a>
                <a href="/" class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-6 py-3 text-sm font-semibold text-white/90 backdrop-blur transition hover:bg-white/15">
                    Home
                </a>
            </div>
        </div>
    </div>
</section>

<section id="tipsContentSection" class="bg-slate-50 px-4 py-14 sm:px-6 lg:px-8">
    <?php if ($isViewingAllArticles): ?>
        <div class="mx-auto max-w-7xl">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 font-[Barlow]">All Articles</h2>
                        
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Page <?= (int) $currentArticlesPage ?></span>
                        <a href="/tips-troubleshooting" class="inline-flex items-center rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                            Back to featured articles
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <?php foreach ($articles as $article): ?>
                    <a href="/tips-troubleshooting?article=<?= (int) $article['id'] ?>" class="block h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md">
                        <div class="flex h-full flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="line-clamp-2 text-lg font-semibold text-slate-900 font-[Barlow] leading-6"><?= htmlspecialchars($article['title']) ?></h3>
                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full <?= !empty($article['is_featured']) ? 'bg-[#0086C9] text-white' : 'bg-slate-100 text-transparent' ?>" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 1.75l2.47 5 5.53.8-4 3.9.95 5.5L10 14.35l-4.95 2.6.95-5.5-4-3.9 5.53-.8L10 1.75z"/>
                                    </svg>
                                </span>
                            </div>
                            <p class="mt-3 line-clamp-4 text-sm leading-6 text-slate-600"><?= htmlspecialchars(formatTipsArticlePreview($article['description'] ?? '')) ?></p>
                            <span class="mt-auto pt-4 inline-flex items-center text-sm font-semibold text-[#0086C9]">Read article</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($articlesTotalPages > 1): ?>
                <div class="mt-8 flex flex-wrap justify-center gap-2 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <?php for ($i = 1; $i <= $articlesTotalPages; $i++): ?>
                        <a href="/tips-troubleshooting?<?= htmlspecialchars(http_build_query(['view' => 'all', 'page' => $i]), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg border text-sm font-semibold transition <?= $i === $currentArticlesPage ? 'border-[#0086C9] bg-[#0086C9] text-white' : 'border-slate-200 bg-white text-[#0086C9] hover:bg-sky-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div id="tipsLayout" class="tips-layout mx-auto grid max-w-7xl gap-8 lg:grid-cols-[320px_minmax(0,1fr)]">
            <aside id="tipsArticlesAside" class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900 font-[Barlow]">Featured Articles</h2>                    
                </div>

                <div class="space-y-3">
                    <?php foreach ($articles as $article): ?>
                        <?php $isActive = $selectedArticle && (int) $selectedArticle['id'] === (int) $article['id']; ?>
                        <a href="/tips-troubleshooting?article=<?= (int) $article['id'] ?>" class="block h-44 overflow-hidden rounded-2xl border p-4 shadow-sm transition-all duration-200 <?= $isActive ? 'border-[#0086C9] bg-[#eaf7fd] shadow-md' : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md' ?>">
                            <div class="flex h-full items-start justify-between gap-3">
                                <div class="min-w-0 flex h-full flex-1 flex-col">
                                    <h3 class="line-clamp-2 break-words font-semibold text-slate-900 font-[Barlow] leading-6">
                                        <?= htmlspecialchars($article['title']) ?>
                                    </h3>
                                    <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">
                                        <?= htmlspecialchars(formatTipsArticlePreview($article['description'] ?? '')) ?>
                                    </p>
                                </div>
                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full <?= !empty($article['is_featured']) ? 'bg-[#0086C9] text-white' : 'bg-slate-100 text-transparent' ?>" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 1.75l2.47 5 5.53.8-4 3.9.95 5.5L10 14.35l-4.95 2.6.95-5.5-4-3.9 5.53-.8L10 1.75z"/>
                                    </svg>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <main id="tipsArticleMain" class="min-w-0">
                <?php if (!empty($selectedArticle)): ?>
                    <article id="selected-article" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl scroll-mt-8">
                        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-6 py-5 sm:px-8">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#0086C9]">Selected article</p>
                            <h2 class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl font-[Barlow]">
                                <?= htmlspecialchars($selectedArticle['title']) ?>
                            </h2>
                        </div>

                        <div class="px-6 py-6 sm:px-8 sm:py-8">
                            <?php if (!empty($selectedArticle['image_path'])): ?>
                                <img src="<?= htmlspecialchars($selectedArticle['image_path']) ?>" alt="<?= htmlspecialchars($selectedArticle['title']) ?>" class="mb-6 h-[220px] w-full rounded-2xl object-cover sm:h-[320px]">
                            <?php endif; ?>
                            <?= formatTipsArticleDescription($selectedArticle['description'] ?? '') ?>

                            <div class="mt-8 flex flex-wrap gap-3">
                                <a href="/tips-troubleshooting?<?= htmlspecialchars(http_build_query(['view' => 'all']), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                                    View all articles
                                </a>
                                <a href="#tips-page-top" id="tipsBackToTopBtn" class="inline-flex items-center rounded-full bg-[#0086C9] px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-[#0086C9]/25 transition hover:bg-[#0a78ae]">
                                    Back to top
                                </a>
                            </div>
                        </div>
                    </article>
                <?php else: ?>
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
                        <p class="text-lg font-semibold text-slate-900">No articles available.</p>
                        <p class="mt-2 text-sm text-slate-500">Create an article in the admin area to populate this page.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isViewingAllArticles = <?= $isViewingAllArticles ? 'true' : 'false' ?>;
    const layout = document.getElementById('tipsLayout');
    const section = document.getElementById('tipsContentSection');
    const backToTopButton = document.getElementById('tipsBackToTopBtn');

    if (backToTopButton) {
        backToTopButton.addEventListener('click', function(event) {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    if (isViewingAllArticles || !layout || !section) {
        return;
    }

    let isFocused = false;

    const updateFocusedLayout = function() {
        if (window.innerWidth < 1024) {
            layout.classList.remove('tips-layout-focused');
            isFocused = false;
            return;
        }

        const enterThreshold = section.offsetTop + 230;
        const exitThreshold = section.offsetTop + 150;
        const y = window.scrollY;

        if (!isFocused && y > enterThreshold) {
            isFocused = true;
            layout.classList.add('tips-layout-focused');
            return;
        }

        if (isFocused && y < exitThreshold) {
            isFocused = false;
            layout.classList.remove('tips-layout-focused');
        }
    };

    window.addEventListener('scroll', updateFocusedLayout, { passive: true });
    window.addEventListener('resize', updateFocusedLayout);
    updateFocusedLayout();
});
</script>

<style>
@media (min-width: 1024px) {
    .tips-layout {
        transition: max-width 0.55s cubic-bezier(0.22, 1, 0.36, 1), grid-template-columns 0.55s cubic-bezier(0.22, 1, 0.36, 1);
    }

    #tipsArticlesAside {
        transform-origin: top left;
        transition: opacity 0.42s ease, transform 0.42s ease, filter 0.42s ease, max-height 0.42s ease, margin 0.42s ease;
    }

    #tipsArticleMain {
        transition: max-width 0.55s cubic-bezier(0.22, 1, 0.36, 1), margin 0.55s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .tips-layout-focused {
        grid-template-columns: minmax(0, 1fr) !important;
        max-width: 960px;
    }

    .tips-layout-focused #tipsArticlesAside {
        opacity: 0;
        transform: translateX(-26px) scale(0.96);
        filter: blur(2px);
        pointer-events: none;
        max-height: 0;
        overflow: hidden;
        margin: 0;
    }

    .tips-layout-focused #tipsArticleMain {
        margin-left: auto;
        margin-right: auto;
        width: 100%;
        max-width: 960px;
        animation: tipsArticleCenterIn 0.55s cubic-bezier(0.22, 1, 0.36, 1);
    }

    @keyframes tipsArticleCenterIn {
        from {
            transform: translateX(90px);
        }
        to {
            transform: translateX(0);
        }
    }
}
</style>
