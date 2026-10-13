<?php

require_once __DIR__ . '/mitglieder/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/news.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $posts = array_map(
        static fn(array $post): array => [
            'id' => (int)$post['id'],
            'title' => $post['title'],
            'body_html' => $post['body_html'],
            'image_path' => $post['image_path'],
            'images' => array_map(
                static fn(array $image): array => [
                    'id' => $image['id'] === null ? null : (int)$image['id'],
                    'image_path' => $image['image_path'],
                ],
                $post['images'] ?? []
            ),
            'visible_until' => $post['visible_until'],
            'created_at' => $post['created_at'],
            'author_username' => $post['author_username'],
        ],
        member_area_news_list('public')
    );
} catch (Throwable $error) {
    if (!member_area_is_database_exception($error)) {
        throw $error;
    }

    http_response_code(503);
    echo json_encode(['posts' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['posts' => $posts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
