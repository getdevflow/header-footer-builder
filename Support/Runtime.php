<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder\Support;

use Plugin\HeaderFooterBuilder\Repository\BuilderRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Modules\GrapesJS\PageRenderer;
use Vihzhuo\Page;
use Vihzhuo\Core\HttpContext;
use Vihzhuo\Core\ThemeContext;

use function App\Shared\Helpers\cms_get_current_user;
use function App\Shared\Helpers\is_user_logged_in;
use function App\Shared\Helpers\plugin_url;
use function Qubus\Security\Helpers\__observer;

final class Runtime
{
    private static ?BuilderRepository $repository = null;
    private static array $rendering = [];

    public static function boot(BuilderRepository $repository): void
    {
        self::$repository = $repository;
        self::$rendering = [];
    }

    public static function repository(): ?BuilderRepository
    {
        return self::$repository;
    }

    /**
     * @throws Exception
     */
    public static function asset(string $file): string
    {
        return plugin_url('HeaderFooterBuilder/public/' . $file);
    }

    /**
     * @throws Exception
     */
    public static function assets(): string
    {
        // Each fragment is self-contained, including AJAX fragments and editor previews.
        return '<link rel="stylesheet" data-hfb-asset href="' . Html::escape(self::asset('css/builder.css')) . '">'
        . '<link rel="stylesheet" data-hfb-asset href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">'
        . '<script data-hfb-asset defer src="' . Html::escape(self::asset('js/frontend.js')) . '"></script>';
    }

    /**
     * @throws TypeException
     * @throws Exception
     */
    public static function canvasAssets(?ThemeContract $theme = null): string
    {
        if ($theme !== null) {
            return ThemeContext::run($theme, static fn (): string => self::canvasAssets());
        }
        $html = self::assets();
        $assets = __observer()->filter->applyFilter(
            'header.footer.canvas.assets',
            ['styles' => [], 'scripts' => []],
            ThemeContext::current() ?? self::theme()
        );
        if (!is_array($assets)) {
            return $html;
        }

        foreach (['styles', 'scripts'] as $type) {
            foreach (is_array($assets[$type] ?? null) ? $assets[$type] : [] as $asset) {
                $url = self::canvasAssetUrl($asset);
                if ($url !== null) {
                    $html .= $type === 'styles'
                    ? '<link rel="stylesheet" href="' . Html::escape($url) . '">'
                    : '<script defer src="' . Html::escape($url) . '"></script>';
                }
            }
        }

        return $html;
    }

    private static function canvasAssetUrl(mixed $asset): ?string
    {
        $asset = is_string($asset) ? trim($asset) : '';
        if ($asset === '' || Html::url($asset) === '#') {
            return null;
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $asset) && !preg_match('/^https?:\/\//i', $asset)) {
            return null;
        }

        return str_starts_with($asset, '/') || preg_match('/^https?:\/\//i', $asset)
        ? $asset
        : phpb_theme_asset($asset);
    }

    public static function page(array $template): Page
    {
        $page = new Page();
        $page->setData(
            [
                'id' => $template['id'],
                'name' => $template['name'],
                'layout' => 'hfb-canvas',
                'hfb_kind' => $template['kind'],
                'data' => $template['data']
            ]
        );

        $locale = (string) (phpb_config('general.language') ?: 'en');

        $page->setTranslations(
            [
                $locale => [
                    'title' => $template['name'],
                    'meta_title' => $template['name'],
                    'meta_description' => '',
                    'route' => '/admin/plugin/header-footer-builder/preview/?id=' . rawurlencode($template['id'])
                ]
            ]
        );

        return $page;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     */
    public static function context(?PageContract $page = null): array
    {
        $roles = is_user_logged_in() ? (array) cms_get_current_user()->role : ['guest'];
        $type = (string) ($page?->get('content_type') ?? '');

        if ($type === '' && self::$repository && HttpContext::hasRequest()) {
            $type = self::$repository->contentTypeForPath(HttpContext::request()->getUri()->getPath());
        }

        return __observer()->filter->applyFilter('header.footer.context', [
            'page_id' => $page?->getId() ?? '', 'content_type' => $type, 'roles' => $roles,
        ], $page);
    }

    public static function selected(string $kind, array $context): ?array
    {
        return self::$repository ? Conditions::select(self::$repository->templates(), $kind, $context) : null;
    }

    /**
     * @throws ReflectionException
     * @throws \Exception
     */
    public static function render(
        array $template,
        ThemeContract $theme,
        bool $preview = false,
        ?string $language = null
    ): string {
        // A menu inside its own mega template must never recurse indefinitely.
        if (isset(self::$rendering[$template['id']])) {
            return '';
        }
        self::$rendering[$template['id']] = true;
        try {
            PageRenderer::setCanBeCached(false);
            $renderer = new PageRenderer($theme, self::page($template), $preview);
            if ($language !== null) {
                $renderer->setLanguage($language);
            }
            return $renderer->renderBody();
        } finally {
            unset(self::$rendering[$template['id']]);
        }
    }

    public static function theme(): ThemeContract
    {
        return phpb_instance('theme', [phpb_config('theme'), phpb_config('theme.active_theme')]);
    }

    public static function megaAvailable(array $template, array $roles): bool
    {
        return $template['kind'] === 'mega' && $template['published']
        && (($template['conditions']['roles'] ?? []) === [] || array_intersect($roles, $template['conditions']['roles']) !== [])
        && array_intersect($roles, $template['conditions']['exclude_roles'] ?? []) === [];
    }

    /**
     * Use from custom CMS content views; pass page_id/content_type/roles in $context.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public static function renderLocation(string $kind, array $context, ThemeContract $theme): string
    {
        $template = self::selected($kind, $context);
        return $template ? self::wrap($kind, self::render($template, $theme)) : '';
    }

    /**
     * @throws Exception
     */
    public static function wrap(string $kind, string $content): string
    {
        $tag = $kind === 'footer' ? 'footer' : 'header';
        return self::assets() .
        '<' . $tag . ' class="hfb-' .
        $tag . '" data-hfb-template>' . $content . '</' . $tag . '>';
    }

    /**
     * Themes opt in through header_footer.slots; no block slugs are assumed.
     *
     * @throws Exception
     */
    public static function slot(string $slug, ?ThemeContract $theme = null, ?PageContract $page = null): ?string
    {
        $slots = __observer()->filter->applyFilter(
            'header.footer.slots',
            [],
            $theme ?? ThemeContext::current() ?? self::theme(),
            $page
        );

        return array_find(
            ['header', 'footer'],
            fn($kind) => is_array($slots) && is_array($slots[$kind] ?? null) && in_array($slug, $slots[$kind], true)
        );
    }
}
