<?php

namespace Blog\Domain;

use Blog\Api\ApiException;

/**
 * Fixed-window limits for anonymous actions. Counts per visitor IP in APCu
 * (shared by every PHP-FPM worker) and per session as a second line.
 */
final class RateLimit
{
    public static function hit(string $bucket, int $max, int $seconds, string $message): void
    {
        $window = intdiv(time(), $seconds);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $key = "blog:rl:$bucket:" . hash('sha256', $ip) . ":$window";
            apcu_add($key, 0, $seconds);
            if ((int) apcu_inc($key) > $max) {
                throw new ApiException($message, 429);
            }
        }
        $slot = &$_SESSION['blog_rate'][$bucket];
        if (!is_array($slot) || ($slot['window'] ?? null) !== $window) {
            $slot = ['window' => $window, 'count' => 0];
        }
        if (++$slot['count'] > $max) {
            throw new ApiException($message, 429);
        }
    }
}
