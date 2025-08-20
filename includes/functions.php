<?php
// includes/functions.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

declare(strict_types=1);

/**
 * Optional one-time load guard (in addition to function_exists safeguards).
 * If this file is accidentally included multiple times, this prevents any side-effects.
 */
if (!defined('APP_FUNCTIONS_LOADED')) {
    define('APP_FUNCTIONS_LOADED', true);

    if (!function_exists('slugify')) {
        /**
         * Convert a string to a URL friendly slug.
         */
        function slugify($text) {
            $text = preg_replace('~[^\pL\d]+~u', '-', $text);
            // iconv can return false; coerce to string to avoid warnings
            $converted = @iconv('utf-8', 'us-ascii//TRANSLIT', (string)$text);
            if ($converted !== false) {
                $text = $converted;
            }
            $text = preg_replace('~[^-\w]+~', '', $text);
            $text = trim($text, '-');
            $text = preg_replace('~-+~', '-', $text);
            $text = strtolower($text);
            if (empty($text)) return 'n-a-' . bin2hex(random_bytes(3));
            return $text;
        }
    }

    if (!function_exists('price_display')) {
        /**
         * Format a numeric price to two decimal places.
         */
        function price_display($value) {
            return number_format((float)$value, 2);
        }
    }

    if (!function_exists('upload_image')) {
        /**
         * Handle an image upload and move it into $destDir.
         * Returns a relative path string on success or null if no file provided.
         * Throws RuntimeException on validation/move errors.
         *
         * Usage:
         *   $path = upload_image($_FILES['image'], __DIR__ . '/../public/uploads');
         */
        function upload_image($file, $destDir) {
            if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return null; // nothing uploaded
            }

            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Upload error code: ' . (int)$file['error']);
            }

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            // Ensure finfo is available
            if (!class_exists('finfo')) {
                throw new RuntimeException('Fileinfo extension not available.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);

            if (!isset($allowed[$mime])) {
                throw new RuntimeException('Unsupported image type.');
            }

            // Basic size check (5 MB)
            if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
                throw new RuntimeException('File too large (max 5MB).');
            }

            if (!is_dir($destDir)) {
                // 0775 allows web server + group write; adjust per hosting needs
                if (!mkdir($destDir, 0775, true) && !is_dir($destDir)) {
                    throw new RuntimeException('Failed creating upload dir.');
                }
            }

            $ext  = $allowed[$mime];
            $name = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
            $path = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $name;

            // Extra safety: ensure it is an uploaded file before moving
            if (!is_uploaded_file($file['tmp_name'])) {
                throw new RuntimeException('Invalid upload source.');
            }

            if (!move_uploaded_file($file['tmp_name'], $path)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }

            /**
             * Return a relative/URL path that matches your existing convention.
             * If your public URL for $destDir ends with "/uploads", then:
             */
            return 'uploads/' . $name;
        }
    }

    if (!function_exists('get_currencies')) {
        /**
         * Fetch currency list and detect the base currency.
         * Returns: [array $rows, string|null $base]
         * Each row includes: code, symbol, is_base, rate_to_base
         */
        function get_currencies(PDO $pdo) {
            $stmt = $pdo->query("SELECT code, symbol, is_base, rate_to_base FROM currencies");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $base = null;
            foreach ($rows as $r) {
                if (!empty($r['is_base'])) {
                    $base = $r['code'];
                    break;
                }
            }
            return [$rows, $base];
        }
    }

    if (!function_exists('fetch_categories')) {
        /**
         * Fetch all categories (id, name, slug) sorted by name.
         */
        function fetch_categories(PDO $pdo) {
            $stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!function_exists('fetch_attributes_by_category')) {
        /**
         * Fetch allowed attributes for a category grouped by type (occasion, length, style).
         * Returns an array like:
         * [
         *   'occasion' => [ ['id'=>..., 'value'=>...], ... ],
         *   'length'   => [ ... ],
         *   'style'    => [ ... ],
         * ]
         */
        function fetch_attributes_by_category(PDO $pdo, int $category_id) {
            $sql = "
                SELECT a.id, a.value, t.code AS type
                FROM category_attribute_allowed caa
                JOIN attributes a      ON a.id   = caa.attribute_id
                JOIN attribute_types t  ON t.id   = a.type_id
                WHERE caa.category_id = ?
                ORDER BY t.code, a.value
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$category_id]);

            $res = ['occasion' => [], 'length' => [], 'style' => []];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $type = $row['type'];
                if (!isset($res[$type])) {
                    // In case new types appear later, initialize dynamically
                    $res[$type] = [];
                }
                $res[$type][] = ['id' => (int)$row['id'], 'value' => $row['value']];
            }

            return $res;
        }
    }

} // end APP_FUNCTIONS_LOADED guard
