<?php

require_once __DIR__ . '/db.php';

const MEMBER_AREA_NEWS_ALLOWED_TAGS = [
    'p' => [],
    'br' => [],
    'strong' => [],
    'b' => [],
    'em' => [],
    'i' => [],
    'u' => [],
    's' => [],
    'strike' => [],
    'ul' => [],
    'ol' => [],
    'li' => [],
    'h4' => [],
    'h5' => [],
    'h6' => [],
    'blockquote' => [],
    'a' => ['href'],
    'span' => ['class'],
];
const MEMBER_AREA_NEWS_ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];
const MEMBER_AREA_NEWS_MAX_IMAGES = 8;

function member_area_news_retention_days(): int
{
    $value = getenv('MEMBER_AREA_NEWS_IMAGE_RETENTION_DAYS');

    return max(1, (int)($value !== false ? $value : 30));
}

function member_area_news_upload_dir(): string
{
    $projectPublicPath = dirname(__DIR__, 2) . '/public/uploads/news';

    if (is_dir(dirname($projectPublicPath, 2))) {
        return $projectPublicPath;
    }

    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $basePath = $documentRoot !== '' ? $documentRoot : dirname(__DIR__, 2) . '/public';

    return $basePath . '/uploads/news';
}

function member_area_news_upload_dirs(): array
{
    $dirs = [member_area_news_upload_dir()];
    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot !== '') {
        $dirs[] = $documentRoot . '/uploads/news';
    }

    return array_values(array_unique($dirs));
}

function member_area_news_public_upload_path(): string
{
    return '/uploads/news';
}

function member_area_news_ensure_images_table(): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    try {
        member_area_db()->query('SELECT 1 FROM news_post_images LIMIT 1');
        $ensured = true;
        return;
    } catch (PDOException $error) {
        $errorInfo = $error->errorInfo;
        $sqlState = (string)($errorInfo[0] ?? $error->getCode());
        $driverErrorCode = (int)($errorInfo[1] ?? 0);

        if ($sqlState !== '42S02' && $driverErrorCode !== 1146) {
            throw $error;
        }
    }

    member_area_db()->exec(
        "CREATE TABLE IF NOT EXISTS news_post_images (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          post_id INT UNSIGNED NOT NULL,
          image_path VARCHAR(255) NOT NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_news_post_images_post (post_id, sort_order, id),
          CONSTRAINT fk_news_post_images_post
            FOREIGN KEY (post_id) REFERENCES news_posts (id)
            ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    member_area_db()->exec(
        "INSERT INTO news_post_images (post_id, image_path, sort_order, created_at)
         SELECT news_posts.id, news_posts.image_path, 0, news_posts.created_at
         FROM news_posts
         WHERE news_posts.image_path IS NOT NULL
           AND NOT EXISTS (
             SELECT 1 FROM news_post_images
             WHERE news_post_images.post_id = news_posts.id
           )"
    );

    $ensured = true;
}

function member_area_news_ensure_upload_dir(): void
{
    foreach (member_area_news_upload_dirs() as $uploadDir) {
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('News upload directory could not be created');
        }
    }
}

function member_area_news_existing_image_path(string $imagePath): ?string
{
    if (!str_starts_with($imagePath, member_area_news_public_upload_path() . '/')) {
        return null;
    }

    $filename = basename($imagePath);

    foreach (member_area_news_upload_dirs() as $uploadDir) {
        $path = $uploadDir . '/' . $filename;

        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function member_area_news_valid_image_path(?string $imagePath): ?string
{
    if (!$imagePath || member_area_news_existing_image_path($imagePath) === null) {
        return null;
    }

    return $imagePath;
}

function member_area_news_sanitize_dom_node(DOMNode $node): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMComment) {
            $node->removeChild($child);
            continue;
        }

        if ($child instanceof DOMText) {
            continue;
        }

        if (!$child instanceof DOMElement) {
            $node->removeChild($child);
            continue;
        }

        $tagName = strtolower($child->tagName);

        if (!array_key_exists($tagName, MEMBER_AREA_NEWS_ALLOWED_TAGS)) {
            // Unwrap disallowed tags (e.g. <script>, <img>, <div>) but keep sanitizing
            // and preserve their text/inline content instead of dropping it outright.
            member_area_news_sanitize_dom_node($child);

            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }

            $node->removeChild($child);
            continue;
        }

        $allowedAttributes = MEMBER_AREA_NEWS_ALLOWED_TAGS[$tagName];

        foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
            if (!in_array(strtolower($attribute->name), $allowedAttributes, true)) {
                $child->removeAttribute($attribute->name);
            }
        }

        if ($tagName === 'a') {
            // Mirror the browser's URL parsing (which strips tabs/newlines from anywhere
            // in the URL, including inside the scheme) before validating the scheme, so a
            // payload like "java\tscript:" can't sneak a disallowed scheme past this check.
            $href = preg_replace('/[\t\r\n]/', '', trim($child->getAttribute('href'))) ?? '';
            $scheme = null;

            if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $href, $schemeMatch)) {
                $scheme = strtolower($schemeMatch[1]);
            }

            $isRelativeOrAnchor = $href !== '' && $scheme === null;

            if ($href !== '' && ($isRelativeOrAnchor || in_array($scheme, MEMBER_AREA_NEWS_ALLOWED_URL_SCHEMES, true))) {
                $child->setAttribute('href', $href);
            } else {
                $child->setAttribute('href', '#');
            }

            $child->setAttribute('rel', 'noopener noreferrer');
            $child->setAttribute('target', '_blank');
        }

        if ($tagName === 'span') {
            $classes = array_intersect(
                preg_split('/\s+/', trim((string)$child->getAttribute('class'))) ?: [],
                ['news-size-small']
            );

            if ($classes) {
                $child->setAttribute('class', implode(' ', $classes));
            } else {
                $child->removeAttribute('class');
            }
        }

        member_area_news_sanitize_dom_node($child);
    }
}

function member_area_news_sanitize_html(string $html): string
{
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    $document = new DOMDocument();
    $previousErrorSetting = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<?xml encoding="utf-8" ?><div id="member-area-news-root">' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_use_internal_errors($previousErrorSetting);

    if (!$loaded) {
        return '';
    }

    $root = $document->getElementById('member-area-news-root');

    if (!$root) {
        return '';
    }

    member_area_news_sanitize_dom_node($root);

    $innerHtml = '';

    foreach (iterator_to_array($root->childNodes) as $child) {
        $innerHtml .= $document->saveHTML($child);
    }

    $innerHtml = preg_replace(
        '/<span class="news-size-small">\s*<span class="news-size-small">([\s\S]*?)<\/span>\s*<\/span>/i',
        '<span class="news-size-small">$1</span>',
        $innerHtml
    ) ?? $innerHtml;

    return trim($innerHtml);
}

function member_area_news_store_uploaded_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Das Bild konnte nicht hochgeladen werden.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Das Bild darf maximal 5 MB groß sein.');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $imageInfo = @getimagesize($tmpName);

    if (!$imageInfo) {
        throw new RuntimeException('Bitte eine gültige Bilddatei hochladen.');
    }

    if (($imageInfo[0] ?? 0) <= ($imageInfo[1] ?? 0)) {
        throw new RuntimeException('Bitte Bilder im Querformat zuschneiden.');
    }

    $extensions = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    $extension = $extensions[$imageInfo[2]] ?? null;

    if (!$extension) {
        throw new RuntimeException('Erlaubt sind JPG, PNG und WebP.');
    }

    member_area_news_ensure_upload_dir();

    $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $target = member_area_news_upload_dir() . '/' . $filename;

    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('Das Bild konnte nicht gespeichert werden.');
    }

    foreach (member_area_news_upload_dirs() as $uploadDir) {
        $copyTarget = $uploadDir . '/' . $filename;

        if ($copyTarget !== $target) {
            copy($target, $copyTarget);
        }
    }

    return member_area_news_public_upload_path() . '/' . $filename;
}

function member_area_news_uploaded_file_list(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [$files];
    }

    $normalizedFiles = [];
    $count = count($files['name']);

    for ($index = 0; $index < $count; $index++) {
        $normalizedFiles[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalizedFiles;
}

function member_area_news_upload_gallery_images(array $files): array
{
    $uploadedPaths = [];
    $normalizedFiles = member_area_news_uploaded_file_list($files);

    if (count($normalizedFiles) > MEMBER_AREA_NEWS_MAX_IMAGES) {
        throw new RuntimeException('Es können maximal ' . MEMBER_AREA_NEWS_MAX_IMAGES . ' Bilder hochgeladen werden.');
    }

    foreach ($normalizedFiles as $index => $file) {
        $path = member_area_news_store_uploaded_image($file);

        if ($path !== null) {
            $uploadedPaths[$index] = $path;
        }
    }

    return $uploadedPaths;
}

function member_area_news_parse_gallery_manifest(string $manifestJson): array
{
    if (trim($manifestJson) === '') {
        return [];
    }

    $items = json_decode($manifestJson, true);

    if (!is_array($items)) {
        throw new RuntimeException('Die Bildreihenfolge konnte nicht gelesen werden.');
    }

    if (count($items) > MEMBER_AREA_NEWS_MAX_IMAGES) {
        throw new RuntimeException('Es können maximal ' . MEMBER_AREA_NEWS_MAX_IMAGES . ' Bilder verwendet werden.');
    }

    $normalizedItems = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $type = (string)($item['type'] ?? '');

        if ($type === 'existing') {
            $path = (string)($item['path'] ?? '');

            if (str_starts_with($path, member_area_news_public_upload_path() . '/')) {
                $normalizedItems[] = [
                    'type' => 'existing',
                    'path' => $path,
                ];
            }
        } elseif ($type === 'upload') {
            $fileIndex = $item['file_index'] ?? null;

            if (is_int($fileIndex) || (is_string($fileIndex) && ctype_digit($fileIndex))) {
                $normalizedItems[] = [
                    'type' => 'upload',
                    'file_index' => (int)$fileIndex,
                ];
            }
        }
    }

    return $normalizedItems;
}

function member_area_news_delete_image(?string $imagePath): void
{
    if (!$imagePath || !str_starts_with($imagePath, member_area_news_public_upload_path() . '/')) {
        return;
    }

    $filename = basename($imagePath);

    foreach (member_area_news_upload_dirs() as $uploadDir) {
        $path = $uploadDir . '/' . $filename;

        if (is_file($path)) {
            unlink($path);
        }
    }
}

function member_area_news_images_for_post(int $postId, ?string $legacyImagePath = null): array
{
    $images = [];

    if ($postId > 0) {
        member_area_news_ensure_images_table();

        $statement = member_area_db()->prepare(
            'SELECT id, image_path
             FROM news_post_images
             WHERE post_id = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $statement->execute([$postId]);

        foreach ($statement->fetchAll() as $image) {
            $path = member_area_news_valid_image_path($image['image_path'] ?? null);

            if ($path !== null) {
                $images[] = [
                    'id' => (int)$image['id'],
                    'image_path' => $path,
                ];
            }
        }
    }

    if (!$images) {
        $fallbackPath = member_area_news_valid_image_path($legacyImagePath);

        if ($fallbackPath !== null) {
            $images[] = [
                'id' => null,
                'image_path' => $fallbackPath,
            ];
        }
    }

    return $images;
}

function member_area_news_image_paths_for_post(int $postId, ?string $legacyImagePath = null): array
{
    $paths = array_map(
        static fn(array $image): string => $image['image_path'],
        member_area_news_images_for_post($postId, $legacyImagePath)
    );
    $fallbackPath = member_area_news_valid_image_path($legacyImagePath);

    if ($fallbackPath !== null && !in_array($fallbackPath, $paths, true)) {
        $paths[] = $fallbackPath;
    }

    return array_values(array_unique($paths));
}

function member_area_news_replace_post_images(
    int $postId,
    array $manifestItems,
    array $uploadedPaths,
    ?string $legacyImagePath = null
): array {
    member_area_news_ensure_images_table();

    $oldPaths = member_area_news_image_paths_for_post($postId, $legacyImagePath);
    $nextPaths = [];
    $usedUploadIndexes = [];

    if (!$manifestItems && $uploadedPaths) {
        foreach (array_keys($uploadedPaths) as $fileIndex) {
            $manifestItems[] = [
                'type' => 'upload',
                'file_index' => $fileIndex,
            ];
        }
    }

    foreach ($manifestItems as $item) {
        if ($item['type'] === 'existing') {
            $path = $item['path'];

            if (!in_array($path, $oldPaths, true)) {
                throw new RuntimeException('Ein bestehendes Bild konnte nicht übernommen werden.');
            }

            if (member_area_news_valid_image_path($path) !== null && !in_array($path, $nextPaths, true)) {
                $nextPaths[] = $path;
            }
        } elseif ($item['type'] === 'upload') {
            $fileIndex = (int)$item['file_index'];

            if (!array_key_exists($fileIndex, $uploadedPaths)) {
                throw new RuntimeException('Ein neues Bild konnte nicht übernommen werden.');
            }

            if (!in_array($uploadedPaths[$fileIndex], $nextPaths, true)) {
                $nextPaths[] = $uploadedPaths[$fileIndex];
            }

            $usedUploadIndexes[] = $fileIndex;
        }
    }

    if (count($nextPaths) > MEMBER_AREA_NEWS_MAX_IMAGES) {
        throw new RuntimeException('Es können maximal ' . MEMBER_AREA_NEWS_MAX_IMAGES . ' Bilder verwendet werden.');
    }

    $deleteStatement = member_area_db()->prepare('DELETE FROM news_post_images WHERE post_id = ?');
    $deleteStatement->execute([$postId]);

    $insertStatement = member_area_db()->prepare(
        'INSERT INTO news_post_images (post_id, image_path, sort_order) VALUES (?, ?, ?)'
    );

    foreach ($nextPaths as $index => $path) {
        $insertStatement->execute([$postId, $path, $index]);
    }

    $primaryPath = $nextPaths[0] ?? null;
    $updatePostStatement = member_area_db()->prepare('UPDATE news_posts SET image_path = ? WHERE id = ?');
    $updatePostStatement->execute([$primaryPath, $postId]);

    $deletePaths = [];

    foreach ($oldPaths as $path) {
        if (!in_array($path, $nextPaths, true)) {
            $deletePaths[] = $path;
        }
    }

    foreach ($uploadedPaths as $fileIndex => $path) {
        if (!in_array((int)$fileIndex, $usedUploadIndexes, true)) {
            $deletePaths[] = $path;
        }
    }

    return [
        'primary_path' => $primaryPath,
        'delete_paths' => array_values(array_unique($deletePaths)),
    ];
}

function member_area_news_delete_post(int $postId, array $user): bool
{
    $statement = member_area_db()->prepare('SELECT id, author_id, image_path FROM news_posts WHERE id = ? LIMIT 1');
    $statement->execute([$postId]);
    $post = $statement->fetch();

    if (!$post) {
        return false;
    }

    if (!in_array($user['role'] ?? '', ['superadmin', 'admin'], true) && (int)$post['author_id'] !== (int)$user['id']) {
        return false;
    }

    $imagePaths = member_area_news_image_paths_for_post($postId, $post['image_path']);

    $delete = member_area_db()->prepare('DELETE FROM news_posts WHERE id = ?');
    $delete->execute([$postId]);

    foreach ($imagePaths as $imagePath) {
        member_area_news_delete_image($imagePath);
    }

    return true;
}

function member_area_news_cleanup_images(): void
{
    member_area_news_ensure_images_table();

    $retentionDays = member_area_news_retention_days();
    $statement = member_area_db()->prepare(
        'SELECT id, image_path
         FROM news_posts
         WHERE image_path IS NOT NULL
           AND visible_until < DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)'
    );
    $statement->execute([$retentionDays]);
    $posts = $statement->fetchAll();

    if (!$posts) {
        return;
    }

    $update = member_area_db()->prepare('UPDATE news_posts SET image_path = NULL WHERE id = ?');
    $deleteImages = member_area_db()->prepare('DELETE FROM news_post_images WHERE post_id = ?');

    foreach ($posts as $post) {
        $postId = (int)$post['id'];

        foreach (member_area_news_image_paths_for_post($postId, $post['image_path']) as $imagePath) {
            member_area_news_delete_image($imagePath);
        }

        $deleteImages->execute([$postId]);
        $update->execute([$postId]);
    }
}

function member_area_news_prepare_post(array $post): array
{
    $images = is_array($post['images'] ?? null) ? $post['images'] : [];

    if (!$images) {
        $fallbackPath = member_area_news_valid_image_path($post['image_path'] ?? null);

        if ($fallbackPath !== null) {
            $images[] = [
                'id' => null,
                'image_path' => $fallbackPath,
            ];
        }
    }

    $post['images'] = $images;
    $post['image_path'] = $images[0]['image_path'] ?? null;

    if (array_key_exists('body_html', $post)) {
        // Re-sanitize on every read, not just on save, so posts already stored
        // before a sanitizer hardening (or written by any other path than
        // news-edit.php) can never reach an HTML sink unsanitized.
        $post['body_html'] = member_area_news_sanitize_html((string)($post['body_html'] ?? ''));
    }

    return $post;
}

function member_area_news_attach_images(array $posts): array
{
    if (!$posts) {
        return [];
    }

    member_area_news_ensure_images_table();

    $postIds = array_map(static fn(array $post): int => (int)$post['id'], $posts);
    $postIds = array_values(array_unique(array_filter($postIds)));
    $imagesByPostId = [];

    if ($postIds) {
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $statement = member_area_db()->prepare(
            "SELECT id, post_id, image_path
             FROM news_post_images
             WHERE post_id IN ({$placeholders})
             ORDER BY post_id ASC, sort_order ASC, id ASC"
        );
        $statement->execute($postIds);

        foreach ($statement->fetchAll() as $image) {
            $path = member_area_news_valid_image_path($image['image_path'] ?? null);

            if ($path === null) {
                continue;
            }

            $imagesByPostId[(int)$image['post_id']][] = [
                'id' => (int)$image['id'],
                'image_path' => $path,
            ];
        }
    }

    return array_map(static function (array $post) use ($imagesByPostId): array {
        $post['images'] = $imagesByPostId[(int)$post['id']] ?? [];

        return member_area_news_prepare_post($post);
    }, $posts);
}

function member_area_news_list(string $target, bool $includeExpired = false): array
{
    member_area_news_cleanup_images();

    // Explicit allow-list rather than a ternary default: a future caller passing an
    // unexpected $target fails loudly instead of silently falling back to "members" and
    // the interpolated column name can never become anything other than these two literals.
    $visibilityColumn = match ($target) {
        'public' => 'show_public',
        'members' => 'show_members',
        default => throw new InvalidArgumentException("Unknown news list target: {$target}"),
    };
    $dateCondition = $includeExpired ? '1 = 1' : 'visible_until >= CURRENT_DATE';

    $posts = member_area_db()->query(
        "SELECT news_posts.id, news_posts.title, news_posts.body_html, news_posts.image_path,
                news_posts.show_public, news_posts.show_members, news_posts.visible_until,
                news_posts.created_at, users.username AS author_username
         FROM news_posts
         INNER JOIN users ON users.id = news_posts.author_id
         WHERE {$visibilityColumn} = 1 AND {$dateCondition}
         ORDER BY news_posts.created_at DESC, news_posts.id DESC"
    )->fetchAll();

    return member_area_news_attach_images($posts);
}

function member_area_news_all_posts(): array
{
    member_area_news_cleanup_images();

    $posts = member_area_db()->query(
        'SELECT news_posts.id, news_posts.title, news_posts.body_html, news_posts.image_path,
                news_posts.show_public, news_posts.show_members, news_posts.visible_until, news_posts.created_at,
                news_posts.author_id,
                users.username AS author_username
         FROM news_posts
         INNER JOIN users ON users.id = news_posts.author_id
         ORDER BY news_posts.created_at DESC, news_posts.id DESC'
    )->fetchAll();

    return member_area_news_attach_images($posts);
}
