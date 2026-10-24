<?php

if (!function_exists('wocAvatarInitials')) {
    function wocAvatarInitials(string $name = '', string $fallback = '?'): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($normalized === '') {
            return $fallback;
        }

        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $letters = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (function_exists('mb_substr')) {
                $letters[] = mb_substr($part, 0, 1, 'UTF-8');
            } else {
                $letters[] = substr($part, 0, 1);
            }

            if (count($letters) >= 2) {
                break;
            }
        }

        $value = implode('', $letters);
        if ($value === '') {
            return $fallback;
        }

        if (function_exists('mb_strtoupper')) {
            $value = mb_strtoupper($value, 'UTF-8');
        } else {
            $value = strtoupper($value);
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 2, 'UTF-8');
        }

        return substr($value, 0, 2);
    }
}

if (!function_exists('wocAvatarResolveSrc')) {
    function wocAvatarResolveSrc(string $source = ''): string
    {
        $value = trim($source);
        if ($value === '') {
            return '';
        }

        if (preg_match('~^(https?:)?//~i', $value) || strpos($value, 'data:') === 0) {
            return $value;
        }

        $value = str_replace('\\', '/', $value);
        while (strpos($value, '../') === 0) {
            $value = substr($value, 3);
        }
        $value = ltrim($value, '/');

        if ($value === '') {
            return '';
        }

        if (strpos($value, 'assets/') === 0) {
            return '/' . $value;
        }

        if (strpos($value, 'profile_pictures/') === 0) {
            return '/assets/' . $value;
        }

        return '/assets/' . $value;
    }
}

if (!function_exists('wocRenderAvatar')) {
    function wocRenderAvatar(array $options = []): string
    {
        $size = (int) ($options['size'] ?? 40);
        if ($size < 16) {
            $size = 16;
        } elseif ($size > 320) {
            $size = 320;
        }

        $imageSource = (string) ($options['image_source'] ?? $options['src'] ?? '');
        $firstname = (string) ($options['firstname'] ?? $options['first_name'] ?? '');
        $lastname = (string) ($options['lastname'] ?? $options['last_name'] ?? '');
        $username = (string) ($options['username'] ?? '');
        $name = trim((string) ($options['name'] ?? trim($firstname . ' ' . $lastname)));
        if ($name === '' && $username !== '') {
            $name = $username;
        }

        $fallbackInitials = $username !== '' ? wocAvatarInitials($username, '?') : '?';
        $initials = (string) ($options['initials'] ?? wocAvatarInitials($name, $fallbackInitials));
        $src = wocAvatarResolveSrc($imageSource);

        $class = trim((string) ($options['class'] ?? ''));
        $imgClass = trim((string) ($options['img_class'] ?? ''));
        $fallbackClass = trim((string) ($options['fallback_class'] ?? ''));

        $title = array_key_exists('title', $options)
            ? (string) $options['title']
            : $name;
        $alt = array_key_exists('alt', $options)
            ? (string) $options['alt']
            : ($name !== '' ? $name : ps_t('Profilbild'));

        $containerClass = 'woc-avatar' . ($class !== '' ? ' ' . $class : '');
        $containerStyle = '--woc-avatar-size:' . $size . 'px;';
        $titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '';

        $html = '<span class="' . htmlspecialchars($containerClass, ENT_QUOTES, 'UTF-8') . '" style="' . htmlspecialchars($containerStyle, ENT_QUOTES, 'UTF-8') . '"' . $titleAttr . '>';
        if ($src !== '') {
            $imageClass = 'woc-avatar__img' . ($imgClass !== '' ? ' ' . $imgClass : '');
            $initialsClass = 'woc-avatar__fallback' . ($fallbackClass !== '' ? ' ' . $fallbackClass : '');
            $html .= '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($imageClass, ENT_QUOTES, 'UTF-8') . '" loading="lazy" onerror="this.style.display=\'none\';if(this.nextElementSibling){this.nextElementSibling.style.display=\'inline-flex\';}">';
            $html .= '<span class="' . htmlspecialchars($initialsClass, ENT_QUOTES, 'UTF-8') . '" style="display:none;">' . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</span>';
        } else {
            $initialsClass = 'woc-avatar__fallback' . ($fallbackClass !== '' ? ' ' . $fallbackClass : '');
            $html .= '<span class="' . htmlspecialchars($initialsClass, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $html .= '</span>';

        return $html;
    }
}
