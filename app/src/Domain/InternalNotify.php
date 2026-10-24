<?php

namespace Blog\Domain;

/**
 * Notices from other PlainShow apps (Split), signed with a shared secret.
 * They show under Notiser and go out as pushes, like the blog's own.
 */
final class InternalNotify
{
    public static function handle(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $file = dirname(__DIR__, 3) . '/config/notify.php';
        $body = (string) file_get_contents('php://input', false, null, 0, 16384);
        $signature = (string) ($_SERVER['HTTP_X_PLAINSHOW_SIGNATURE'] ?? '');
        $config = is_file($file) ? require $file : null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !$config || !hash_equals(hash_hmac('sha256', $body, $config['secret']), $signature)) {
            self::reply(403, ps_t('Ogiltig signatur.'));
        }
        $data = json_decode($body, true);
        if (!is_array($data) || abs(time() - (int) ($data['time'] ?? 0)) > 300 || !in_array($data['source'] ?? '', $config['sources'], true)) {
            self::reply(422, ps_t('Ogiltigt meddelande.'));
        }
        $link = (string) ($data['link'] ?? '');
        $split = ps_url('split');
        if ($split === '' || !preg_match('~^' . preg_quote($split, '~') . '/[\w\-./?=&%]*$~', $link)) {
            self::reply(422, ps_t('Ogiltig länk.'));
        }
        $text = mb_substr(trim((string) ($data['text'] ?? '')), 0, 240);
        $users = array_values(array_unique(array_filter(array_map('intval', (array) ($data['users'] ?? [])), fn ($id) => $id > 0)));
        if ($text === '' || !$users || count($users) > 100) {
            self::reply(422, ps_t('Ogiltigt meddelande.'));
        }
        foreach ($users as $userId) {
            Notifier::record($userId, (string) $data['source'], 0, $link, $text);
        }
        self::reply(200, 'Skickat.', ['sent' => count($users)]);
    }

    private static function reply(int $status, string $message, array $extra = []): never
    {
        http_response_code($status);
        echo json_encode(['ok' => $status === 200, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
