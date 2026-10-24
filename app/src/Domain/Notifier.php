<?php

namespace Blog\Domain;

/**
 * In-app notifications (the notiser table).
 * Rows keep the blog's format: `title` holds the link, `content` the text.
 */
final class Notifier
{
    public static function record(int $recipientId, string $type, int $assignId, string $link, string $text): void
    {
        $stmt = wocDb()->prepare('INSERT INTO notiser (user_id, type, assign_id, title, content, date) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('isiss', $recipientId, $type, $assignId, $link, $text);
        $stmt->execute();
    }
}
