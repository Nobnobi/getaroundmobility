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
                link_url VARCHAR(255) NOT NULL,
                link_label VARCHAR(120) NOT NULL DEFAULT 'Learn more',
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
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
                "INSERT INTO tips_troubleshooting_articles (title, description, link_url, link_label, sort_order)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $defaults = [
                [
                    'Troubleshooting',
                    'Vitae cum magnam maiores. Cupiditate dy. Sapiente ipsum eos quas nostrum fugit v.',
                    '#',
                    'Learn more',
                    1,
                ],
                [
                    '5 tips for Las Vegas travel with a disability',
                    'In magnam est magnam ducimus enim quos. In ducimus aliquid consectetur quaerat.',
                    '#',
                    'Learn more',
                    2,
                ],
                [
                    'Las Vegas shows are accessible',
                    'In magnam est magnam ducimus enim quos. In ducimus aliquid consectetur quaerat.',
                    '#',
                    'Learn more',
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
            "SELECT * FROM tips_troubleshooting_articles ORDER BY sort_order ASC, id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function addArticle($title, $description, $linkUrl, $linkLabel, $sortOrder) {
        $stmt = $this->db->prepare(
            "INSERT INTO tips_troubleshooting_articles (title, description, link_url, link_label, sort_order)
             VALUES (?, ?, ?, ?, ?)"
        );

        return $stmt->execute([$title, $description, $linkUrl, $linkLabel, $sortOrder]);
    }

    public function updateArticle($id, $title, $description, $linkUrl, $linkLabel, $sortOrder) {
        $stmt = $this->db->prepare(
            "UPDATE tips_troubleshooting_articles
             SET title = ?, description = ?, link_url = ?, link_label = ?, sort_order = ?
             WHERE id = ?"
        );

        return $stmt->execute([$title, $description, $linkUrl, $linkLabel, $sortOrder, $id]);
    }

    public function deleteArticle($id) {
        $stmt = $this->db->prepare("DELETE FROM tips_troubleshooting_articles WHERE id = ?");
        return $stmt->execute([$id]);
    }
}