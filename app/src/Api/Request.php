<?php

namespace Blog\Api;

/** The incoming API request: method, route parameters and input (JSON or form). */
final class Request
{
    private ?array $json = null;

    public function __construct(public readonly string $method, public readonly array $params = [])
    {
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        if ($this->json === null) {
            $decoded = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
                ? json_decode((string) file_get_contents('php://input'), true)
                : null;
            $this->json = is_array($decoded) ? $decoded : [];
        }
        return $this->json[$key] ?? ($_GET[$key] ?? $default);
    }

    public function string(string $key, int $max = 10000): string
    {
        $value = trim((string) $this->input($key, ''));
        if (mb_strlen($value) > $max) {
            throw new ApiException("Texten är för lång (högst $max tecken).", 422);
        }
        return $value;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function param(string $key): int
    {
        return (int) ($this->params[$key] ?? 0);
    }

    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $file : null;
    }
}
