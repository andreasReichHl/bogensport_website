<?php

require_once __DIR__ . '/db.php';

const MEMBER_AREA_NEWS_ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><blockquote><a>';

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

function member_area_news_sanitize_html(string $html): string
{
    $html = strip_tags($html, MEMBER_AREA_NEWS_ALLOWED_TAGS);
    $html = preg_replace('/\s(?:on[a-z]+|style)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? '';
    $html = preg_replace('/href\s*=\s*("|\')\s*javascript:[^"\']*("|\')/i', 'href="#"', $html) ?? '';
    $html = preg_replace('/<a\s+/i', '<a rel="noopener noreferrer" target="_blank" ', $html) ?? '';

    return trim($html);
}

function member_area_news_upload_image(array $file): ?string
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

function member_area_news_cleanup_images(): void
{
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

    foreach ($posts as $post) {
        member_area_news_delete_image($post['image_path']);
        $update->execute([(int)$post['id']]);
    }
}

function member_area_news_prepare_post(array $post): array
{
    if (!empty($post['image_path']) && member_area_news_existing_image_path($post['image_path']) === null) {
        $post['image_path'] = null;
    }

    return $post;
}

function member_area_news_list(string $target, bool $includeExpired = false): array
{
    member_area_news_cleanup_images();

    $visibilityColumn = $target === 'public' ? 'show_public' : 'show_members';
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

    return array_map('member_area_news_prepare_post', $posts);
}

function member_area_news_all_posts(): array
{
    member_area_news_cleanup_images();

    $posts = member_area_db()->query(
        'SELECT news_posts.id, news_posts.title, news_posts.image_path, news_posts.show_public,
                news_posts.show_members, news_posts.visible_until, news_posts.created_at,
                users.username AS author_username
         FROM news_posts
         INNER JOIN users ON users.id = news_posts.author_id
         ORDER BY news_posts.created_at DESC, news_posts.id DESC'
    )->fetchAll();

    return array_map('member_area_news_prepare_post', $posts);
}
