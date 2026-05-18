<?php
namespace App\Models;

use App\Utils\Database;
use PDO;

class TipsTroubleshootingModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureTables();
        $this->seedDefaults();
    }

    private function ensureTables() {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS tips_troubleshooting_section (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                heading VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                image_alt VARCHAR(255) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS tips_troubleshooting_articles (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                image_path VARCHAR(255) NULL,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Backward-compatible migrations for existing installations.
        $columnCheck = $this->db->query("SHOW COLUMNS FROM tips_troubleshooting_articles LIKE 'image_path'");
        if (!$columnCheck || !$columnCheck->fetch(PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE tips_troubleshooting_articles ADD COLUMN image_path VARCHAR(255) NULL AFTER description");
        }

        $featuredCheck = $this->db->query("SHOW COLUMNS FROM tips_troubleshooting_articles LIKE 'is_featured'");
        if (!$featuredCheck || !$featuredCheck->fetch(PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE tips_troubleshooting_articles ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path");
        }
    }

    private function seedDefaults() {
        $sectionCount = (int) $this->db->query("SELECT COUNT(*) FROM tips_troubleshooting_section")->fetchColumn();
        if ($sectionCount === 0) {
            $stmt = $this->db->prepare(
                "INSERT INTO tips_troubleshooting_section (id, heading, description, image_path, image_alt)
                 VALUES (1, ?, ?, ?, ?)"
            );
            $stmt->execute([
                'Tips & Troubleshooting',
                'Treat candidates with a rich careers site and a wonderful application process.',
                '/img/pwd1.svg',
                'Woman in wheelchair'
            ]);
        }

        $articleCount = (int) $this->db->query("SELECT COUNT(*) FROM tips_troubleshooting_articles")->fetchColumn();
        if ($articleCount === 0) {
            $stmt = $this->db->prepare(
                "INSERT INTO tips_troubleshooting_articles (title, description, sort_order)
                 VALUES (?, ?, ?)"
            );

            $defaults = [
                [
                    'Troubleshooting',
                    'Vitae cum magnam maiores. Cupiditate dy. Sapiente ipsum eos quas nostrum fugit v.',
                    1,
                ],
                [
                    '5 tips for Las Vegas travel with a disability',
                    'In magnam est magnam ducimus enim quos. In ducimus aliquid consectetur quaerat.',
                    2,
                ],
                [
                    'Las Vegas shows are accessible',
                    'In magnam est magnam ducimus enim quos. In ducimus aliquid consectetur quaerat.',
                    3,
                ],
            ];

            foreach ($defaults as $article) {
                $stmt->execute($article);
            }
        }
    }

    public function getSection() {
        $stmt = $this->db->query("SELECT * FROM tips_troubleshooting_section WHERE id = 1 LIMIT 1");
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$section) {
            return null;
        }

        $imagePaths = $this->normalizeImagePaths($section['image_path'] ?? '');
        $section['image_paths'] = $imagePaths;
        $section['image_path'] = $imagePaths[0] ?? '/img/pwd1.svg';

        return $section;
    }

    public function getArticles() {
        $stmt = $this->db->query(
            "SELECT * FROM tips_troubleshooting_articles ORDER BY id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllArticlesForPublic(int $page = 1, int $perPage = 15): array {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->db->prepare(
            "SELECT *
             FROM tips_troubleshooting_articles
             ORDER BY is_featured DESC, sort_order ASC, id ASC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getArticlesPaginated(int $page, int $perPage = 3): array {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            "SELECT * FROM tips_troubleshooting_articles ORDER BY id ASC LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countArticles(): int {
        return (int) $this->db->query("SELECT COUNT(*) FROM tips_troubleshooting_articles")->fetchColumn();
    }

    public function countFeaturedArticles(): int {
        return (int) $this->db->query("SELECT COUNT(*) FROM tips_troubleshooting_articles WHERE is_featured = 1")->fetchColumn();
    }

    public function getFeaturedArticles(?int $limit = null): array {
        $sql = "SELECT * FROM tips_troubleshooting_articles WHERE is_featured = 1 ORDER BY sort_order ASC, id ASC";
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPublicArticlePage(int $articleId, int $perPage = 15): int {
        $article = $this->getArticleById($articleId);
        if (!$article) {
            return 1;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM tips_troubleshooting_articles
             WHERE is_featured > ?
                OR (is_featured = ? AND sort_order < ?)
                OR (is_featured = ? AND sort_order = ? AND id < ?)"
        );
        $stmt->execute([
            (int) $article['is_featured'],
            (int) $article['is_featured'],
            (int) $article['sort_order'],
            (int) $article['is_featured'],
            (int) $article['sort_order'],
            (int) $article['id'],
        ]);

        $countBefore = (int) $stmt->fetchColumn();

        return (int) floor($countBefore / max(1, $perPage)) + 1;
    }

    public function toggleFeatured(int $id, bool $featured): bool {
        $stmt = $this->db->prepare(
            "UPDATE tips_troubleshooting_articles SET is_featured = ? WHERE id = ?"
        );
        return $stmt->execute([$featured ? 1 : 0, $id]);
    }

    public function updateFeaturedOrder(array $orderedIds): void {
        $stmt = $this->db->prepare(
            "UPDATE tips_troubleshooting_articles SET sort_order = ? WHERE id = ?"
        );
        foreach ($orderedIds as $position => $id) {
            $stmt->execute([$position + 1, (int) $id]);
        }
    }

    public function getArticleById($id) {
        $stmt = $this->db->prepare(
            "SELECT * FROM tips_troubleshooting_articles WHERE id = ? LIMIT 1"
        );
        $stmt->execute([(int) $id]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        return $article ?: null;
    }

    public function updateSection($heading, $description, array $imagePaths, $imageAlt) {
        $encodedImagePaths = json_encode($this->normalizeImagePaths($imagePaths), JSON_UNESCAPED_SLASHES);
        if ($encodedImagePaths === false) {
            $encodedImagePaths = json_encode(['/img/pwd1.svg'], JSON_UNESCAPED_SLASHES);
        }

        $stmt = $this->db->prepare(
            "UPDATE tips_troubleshooting_section
             SET heading = ?, description = ?, image_path = ?, image_alt = ?
             WHERE id = 1"
        );

        return $stmt->execute([$heading, $description, $encodedImagePaths, $imageAlt]);
    }

    private function normalizeImagePaths($imagePathValue) {
        $paths = [];

        if (is_array($imagePathValue)) {
            $paths = $imagePathValue;
        } elseif (is_string($imagePathValue) && $imagePathValue !== '') {
            $decoded = json_decode($imagePathValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $paths = $decoded;
            } else {
                $paths = [$imagePathValue];
            }
        }

        $paths = array_values(array_filter(array_map('trim', $paths), static function ($path) {
            return $path !== '';
        }));

        if (empty($paths)) {
            return ['/img/pwd1.svg'];
        }

        return array_slice($paths, 0, 5);
    }

    public function addArticle($title, $description, $imagePath) {
        $stmt = $this->db->prepare(
            "INSERT INTO tips_troubleshooting_articles (title, description, image_path)
             VALUES (?, ?, ?)"
        );

        return $stmt->execute([$title, $description, $imagePath]);
    }

    public function updateArticle($id, $title, $description, $imagePath) {
        $stmt = $this->db->prepare(
            "UPDATE tips_troubleshooting_articles
             SET title = ?, description = ?, image_path = ?
             WHERE id = ?"
        );

        return $stmt->execute([$title, $description, $imagePath, $id]);
    }

    public function deleteArticle($id) {
        $stmt = $this->db->prepare("DELETE FROM tips_troubleshooting_articles WHERE id = ?");
        return $stmt->execute([$id]);
    }
}