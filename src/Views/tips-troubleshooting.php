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


<section id="tipsContentSection" class="bg-slate-50 px-4 py-14 sm:px-6 lg:px-8">
    <?php if ($isViewingAllArticles): ?>
        <div class="mx-auto max-w-7xl mt-8">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm w-1/3">
                <h2 class="text-3xl font-bold text-slate-900 font-[Barlow]">Tips &amp; Troubleshooting</h2>
                <p class="mt-2 text-sm text-slate-500">Helpful guides and quick answers in one place.</p>
            </div>

            <div class="mt-7 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($articles as $article): ?>
                    <?php
                    $thumb = trim((string) ($article['image_path'] ?? ''));
                    $tag = !empty($article['is_featured']) ? 'Troubleshooting' : 'Tips';
                    ?>
                    <a href="/tips-troubleshooting?article=<?= (int) $article['id'] ?>" class="block h-full rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md">
                        <div class="flex h-full flex-col">
                            <?php if ($thumb !== ''): ?>
                                <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="h-40 w-full rounded-xl object-cover">
                            <?php endif; ?>
                            <div class="mt-3 inline-flex w-max rounded-full border border-sky-100 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-[#0086C9]"><?= htmlspecialchars($tag) ?></div>
                            <div class="mt-3 flex items-start justify-between gap-3">
                                <h3 class="line-clamp-2 text-xl font-semibold text-slate-900 font-[Barlow] leading-7"><?= htmlspecialchars($article['title']) ?></h3>
                            </div>
                            <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600"><?= htmlspecialchars(formatTipsArticlePreview($article['description'] ?? '')) ?></p>
                            <span class="mt-auto pt-4 inline-flex items-center text-sm font-semibold text-[#0086C9]">Read more</span>
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
        <?php
        $lead = formatTipsArticlePreview($selectedArticle['description'] ?? '');
        if (mb_strlen($lead) > 200) {
            $lead = rtrim(mb_substr($lead, 0, 200)) . '...';
        }
        $otherArticles = [];
        foreach ($articles as $article) {
            if (!empty($selectedArticle) && (int) $article['id'] === (int) $selectedArticle['id']) {
                continue;
            }
            $otherArticles[] = $article;
        }
        ?>

        <div class="mx-auto max-w-4xl">
            <?php if (!empty($selectedArticle)): ?>
                <article class="rounded-3xl border border-slate-200 bg-white px-6 py-10 shadow-xl sm:px-10 mt-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="mt-5 text-4xl font-bold leading-tight text-slate-900 font-[Barlow] sm:text-5xl">
                            <?= htmlspecialchars($selectedArticle['title']) ?>
                        </h2>
                       
                    </div>

                    <div class="mx-auto mt-10 max-w-3xl border-t border-slate-100 pt-8" id="selected-article-content">
                        <?php if (!empty($selectedArticle['image_path'])): ?>
                            <img src="<?= htmlspecialchars($selectedArticle['image_path']) ?>" alt="<?= htmlspecialchars($selectedArticle['title']) ?>" class="mb-8 h-[260px] w-full rounded-2xl object-cover sm:h-[380px]">
                        <?php endif; ?>

                        <?= formatTipsArticleDescription($selectedArticle['description'] ?? '') ?>

                        <div class="mt-10 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
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
        </div>

        <?php if (!empty($otherArticles)): ?>
            <div class="mx-auto mt-16 max-w-7xl">
                <div class="mb-6">
                    <h3 class="text-3xl font-bold text-slate-900 font-[Barlow]">More Articles</h3>
                    <p class="mt-1 text-sm text-slate-500">Explore other helpful topics.</p>
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <?php foreach (array_slice($otherArticles, 0, 6) as $article): ?>
                        <?php $otherThumb = trim((string) ($article['image_path'] ?? '')); ?>
                        <a href="/tips-troubleshooting?article=<?= (int) $article['id'] ?>" class="block h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md">
                            <div class="flex h-full flex-col">
                                <?php if ($otherThumb !== ''): ?>
                                    <img src="<?= htmlspecialchars($otherThumb) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="h-40 w-full rounded-xl object-cover">
                                <?php endif; ?>
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="line-clamp-2 text-xl font-semibold text-slate-900 font-[Barlow] leading-7"><?= htmlspecialchars($article['title']) ?></h4>
                                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full <?= !empty($article['is_featured']) ? 'bg-[#0086C9] text-white' : 'bg-slate-100 text-transparent' ?>" aria-hidden="true">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M10 1.75l2.47 5 5.53.8-4 3.9.95 5.5L10 14.35l-4.95 2.6.95-5.5-4-3.9 5.53-.8L10 1.75z"/>
                                        </svg>
                                    </span>
                                </div>
                                <p class="mt-3 line-clamp-4 text-sm leading-6 text-slate-600"><?= htmlspecialchars(formatTipsArticlePreview($article['description'] ?? '')) ?></p>
                                <span class="mt-auto pt-4 inline-flex items-center text-sm font-semibold text-[#0086C9]">Read more</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const backToTopButton = document.getElementById('tipsBackToTopBtn');

    if (backToTopButton) {
        backToTopButton.addEventListener('click', function(event) {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});
</script>

<style>
#selected-article-content h3 {
    margin-top: 2rem;
    margin-bottom: 0.9rem;
    font-size: 1.55rem;
    line-height: 1.2;
    font-weight: 700;
    color: #0f172a;
}

#selected-article-content p {
    margin-bottom: 1.25rem;
    font-size: 1rem;
    line-height: 1.9;
    color: #334155;
}

#selected-article-content ul,
#selected-article-content ol {
    margin-bottom: 1.5rem;
    padding-left: 1.5rem;
    color: #334155;
}

#selected-article-content li {
    margin-bottom: 0.5rem;
    line-height: 1.85;
}

@media (min-width: 640px) {
    #selected-article-content p {
        font-size: 1.0625rem;
    }
}
</style>
