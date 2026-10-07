<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder\Support;

use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use Random\RandomException;
use ReflectionException;
use Vihzhuo\Core\HttpContext;

use function App\Shared\Helpers\site_url;

final class Navigation
{
    /**
     * @param array $items
     * @param string $label
     * @param string $breakpoint
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws RandomException
     * @throws ReflectionException
     */
    public static function render(array $items, string $label = 'Main navigation', string $breakpoint = '768'): string
    {
        $id = 'hfb-nav-' . bin2hex(random_bytes(6));
        $breakpoint = max(320, min(1600, (int) $breakpoint));
        return Runtime::assets() . '<nav class="hfb-nav" aria-label="' . Html::escape($label)
        . '" data-hfb-nav data-breakpoint="' . $breakpoint . '"><button class="hfb-hamburger" type="button" aria-expanded="false" aria-controls="'
        . $id . '" aria-label="Toggle navigation"><span aria-hidden="true">☰</span></button><ul id="'
        . $id . '" class="hfb-menu">' . self::items($items) . '</ul></nav>';
    }

    /**
     * @param array $items
     * @return string
     * @throws Exception
     * @throws JsonException
     * @throws RandomException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    private static function items(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            $template = ($item['template'] ?? '') !== '' ? Runtime::repository()?->template($item['template']) : null;
            if ($template && !Runtime::megaAvailable($template, Runtime::context()['roles'])) {
                $template = null;
            }
            $hasPanel = $template !== null || ($item['children'] ?? []) !== [];
            $panelId = 'hfb-panel-' . bin2hex(random_bytes(6));
            $path = HttpContext::request()?->getUri()->getPath() ?? '/';
            $active = (parse_url(Html::url($item['url']), PHP_URL_PATH) ?: '') === $path;
            $html .= '<li class="hfb-item" data-trigger="' . Html::escape($item['trigger'] ?? 'hover') . '"><a class="hfb-link'
            . ($active ? ' is-active' : '') . '" href="' . Html::escape(Html::url($item['url'])) . '"'
            . ($active ? ' aria-current="page"' : '') . (($item['target'] ?? '') === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '')
            . '>' . Html::escape($item['label']) . '</a>';
            if ($hasPanel) {
                $mode = $template ? ($item['width_mode'] ?? 'default') : 'default';
                $ajax = $template && !empty($item['ajax']);
                $html .= '<button type="button" class="hfb-panel-toggle" aria-expanded="false" aria-controls="' . $panelId
                . '" aria-label="Open ' . Html::escape($item['label']) . '"><span aria-hidden="true">▾</span></button>'
                . '<div id="' . $panelId . '" class="hfb-panel' . ($template ? ' hfb-mega' : '') . '" hidden data-width-mode="'
                . Html::escape($mode) . '" data-align="' . Html::escape($item['align'] ?? 'left')
                . '" style="--hfb-panel-width:' . Html::escape(Html::length($item['width'] ?? '600px', '600px'))
                . ';--hfb-panel-mobile-width:' . Html::escape(Html::length($item['mobile_width'] ?? '100%', '100%')) . '"'
                . ($ajax ? ' data-hfb-url="' . Html::escape(site_url('header-footer-builder/mega/' . $template['id'])
                        . '?language=' . rawurlencode(phpb_current_language())) . '"' : '') . '>';
                $html .= $template
                ? ($ajax ? '<p class="hfb-loading" role="status">Loading…</p>' : Runtime::render($template, Runtime::theme()))
                : '<ul class="hfb-submenu">' . self::items($item['children']) . '</ul>';
                $html .= '</div>';
            }
            $html .= '</li>';
        }
        return $html;
    }
}
