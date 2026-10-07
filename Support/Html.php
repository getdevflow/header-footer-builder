<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder\Support;

use Qubus\Exception\Exception;

use function Qubus\Security\Helpers\esc_html;

final class Html
{
    /**
     * @throws Exception
     */
    public static function escape(mixed $value): string
    {
        return is_scalar($value) ? esc_html((string) $value) : '';
    }

    public static function url(mixed $value): string
    {
        $url = trim(is_string($value) ? $value : '');
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return '#';
        }
        if (str_starts_with($url, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return preg_match('/^(https?:|mailto:|tel:)/i', $url) ? $url : '#';
        }
        return $url;
    }

    public static function length(mixed $value, string $default = '320px'): string
    {
        return is_string($value) && preg_match('/^\d+(?:\.\d+)?(?:px|%|vw|rem)$/', $value) ? $value : $default;
    }
}
