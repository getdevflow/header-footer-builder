<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder\Support;

use Random\RandomException;

final class Layouts
{
    public static function choices(string $kind): array
    {
        return match ($kind) {
            'header' => [
                'blank' => 'Blank',
                'classic' => 'Logo and navigation',
                'stacked' => 'Top bar and navigation'
            ],
            'footer' => [
                'blank' => 'Blank',
                'simple' => 'Simple copyright',
                'columns' => 'Three columns',
                'centered' => 'Centered logo and links'
            ],
            default => ['blank' => 'Blank', 'columns' => 'Three columns'],
        };
    }

    /**
     * @throws RandomException
     */
    public static function data(string $kind, string $layout): array
    {
        $html = match ($layout) {
            'classic' => '<section class="hfb-layout hfb-row">[block slug="hfb-site-logo"] [block slug="hfb-navigation"] [block slug="hfb-icon-button"]</section>',
            'stacked' => '[block slug="hfb-top-bar"]<section class="hfb-layout hfb-row">[block slug="hfb-site-logo"] [block slug="hfb-navigation"]</section>',
            'simple' => '<section class="hfb-layout">[block slug="hfb-copyright"]</section>',
            'centered' => '<section class="hfb-layout hfb-centered">[block slug="hfb-site-logo"] [block slug="hfb-navigation"] [block slug="hfb-copyright"]</section>',
            'columns' => '<section class="hfb-layout"><div class="hfb-columns"><div><h3>About us</h3><p>Tell your visitors about your company.</p></div><div><h3>Explore</h3>[block slug="hfb-navigation"]</div><div><h3>Get in touch</h3>[block slug="hfb-top-bar"]</div></div>' . ($kind === 'footer' ? '[block slug="hfb-copyright"]' : '') . '</section>',
            default => '<section class="hfb-layout"><p>Build your ' . Html::escape($kind) . ' here.</p></section>',
        };
        $html = preg_replace_callback('/\[block slug="([a-zA-Z0-9_-]+)"\]/', static fn (array $match): string =>
            '[block slug="' . $match[1] . '" id="ID' . bin2hex(random_bytes(10)) . '"]', $html);
        $section = 'ID' . bin2hex(random_bytes(10));
        return ['html' => ['[block slug="hfb-section" id="' . $section . '"]'], 'css' => '', 'blocks' => [
            $section => ['html' => $html, 'is_html' => true, 'settings' => [], 'blocks' => []],
        ]];
    }
}
