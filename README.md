# Header Footer Builder

Build reusable headers, footers, and mega menu panels with the installed Vihzhuo editor and its native blocks. Templates live in their own tables and are never added to the public pages list or sitemap.

> __Requires__ Devflow Version: 3.x

> __Tested Up To:__ 3.0.0

> __Requires PHP:__ 8.4+

> __Stable Tag:__ 1.0.0

> __License:__ GPLv2-only

## Getting started

1. Activate **Header Footer Builder** in Plugins. Activation creates the site-prefixed `hfb_template` and `hfb_menu` tables. If the original empty template was already active, deactivate and reactivate it once.
2. Open **Plugins → Header Footer Builder**. Access requires `vihzhuo:manage`.
3. Create a Header, Footer, or Mega menu template. Choose a starter layout, save it, and open **Design**.
4. Drag native Vihzhuo blocks and the Header & Footer widgets onto the canvas. Save the design with Vihzhuo's Save button.
5. Open **Conditions**, choose the display rules, and check **Published**. **Preview** works for drafts and published templates and requires editor access.

Header starters include a logo/navigation row and a top bar with navigation. Footer starters include simple copyright, three columns, and centered logo/links. Every type also offers a blank canvas. The Section block is an editable layout block; native Container and Grid blocks remain available.

## Display conditions

**Entire site**, selected pages, and selected content types are alternative inclusion rules. Selected roles restrict those matches. Excluded pages, content types, and roles always prevent a match. A role-only header/footer needs Entire site or a page/content type inclusion. Guest means a signed-out visitor.

Only one published header and one published footer are chosen per request. Higher priority wins. At equal priority, a page rule wins over a content type rule, then a role-restricted global rule, then an unrestricted global rule. Ties use a stable template ID order. An unmatched or excluded template leaves the theme's original header/footer in place.

The plugin automatically resolves custom content types for published `/type/slug` and `/slug` content routes. Integrations with other route patterns can supply `content_type` on the Vihzhuo Page or use the `header.footer.context` filter, which receives the context array and Page. Context contains `page_id`, `content_type`, and `roles`.

## Widgets and styling

- **Navigation Menu:** choose a saved menu, accessible label, and mobile breakpoint. Typography, spacing, normal/hover/active colors, dropdown colors, borders, dividers, radii, and panel shadows are available under Navigation Styling and the standard style sectors. Tablet/mobile styles use Vihzhuo's device controls.
- **Site Logo:** uses the site's `site_logo` option and `sitename` dynamically, or choose an uploaded image. Set the link, alternative text, and desktop/tablet/mobile widths. The `header.footer.site.logo` filter can supply a logo from another site branding service.
- **Icon Button:** set the text, link, icon symbol or Font Awesome classes, icon position, and new-window behavior. Icon Button Styling supplies normal/hover colors, backgrounds, padding, border, and radius. Icon-only buttons keep their accessible label.
- **Top Bar:** announcement/custom text, email, telephone, and up to three labeled social icons. Use the typography, background, layout, and spacing controls to style it.
- **Copyright:** `{year}` and `{company}` substitutions, optional starting year for a range, company URL, and dynamic site name. Use the typography and other native styling controls.

Font Awesome uses the same CDN stylesheet as the Vihzhuo editor. Symbol icons do not require an icon font.

## Header effects

In a **header** template, select a Section or Container, open the style controls, expand **Advanced**, then **Header Effects**. Enable the effect and configure sticky positioning, transparency before scrolling, scroll distance, solid background, bottom shadow, slide/fade animation, transition duration, and stack order.

Effects are stored as CSS custom properties on the element's unique class, so they survive Vihzhuo's PHP container serialization. Sticky headers reserve their original height; transparent sticky headers overlay the initial content. Choose one outer Section/Container for the sticky header to avoid overlapping sticky sections. Reduced-motion preferences disable animation.

## Menus and mega menus

The plugin includes its own menu editor. Add links, use Add child for dropdowns, and reorder with Move up/Move down. Assign a Mega menu template to a top-level item. Choose hover or click, default/custom/full-viewport width, a custom mobile width, screen alignment, and optional on-demand AJAX loading. Widths accept `px`, `%`, `vw`, and `rem`.

If [MenuBuilder](https://getdevflow.com/extensions/getdevflow/menu-builder) is installed and activated, its menus can be imported as editable copies. The copy keeps labels, URLs, nested items, and new-window behavior. Assign mega templates in this plugin's menu editor. Imports do not alter MenuBuilder or stay synchronized with subsequent MenuBuilder changes.

The mobile menu uses a full-width hamburger panel with scrollable accordion dropdowns. Escape, Arrow Down, outside clicks, and dropdown buttons are supported. AJAX fragments load once per panel, offer Retry on failure, and run authored block scripts. Only published Mega menu templates are served; role restrictions and excluded roles apply equally to inline and AJAX rendering. Mega placement is controlled by menu-item assignment rather than page display conditions. Circular template/menu references are stopped during rendering.

## Theme integration

Themes opt in through filters registered in their `handle()` method. The plugin
does not assume a theme, block slug, stylesheet, or Bootstrap version, and does
not require entries in the CMS's `config/vihzhuo.php`.

`header.footer.slots` receives an empty mapping, the rendering `ThemeContract`,
and the optional `PageContract`. Return header/navigation and footer block slugs.
`header.footer.canvas.assets` receives `['styles' => [], 'scripts' => []]` and the
rendering `ThemeContract`. Return the assets needed by your header/footer canvas.
Specify `arguments: 2` (or `3` when using the Page) when registering a callback.

For example, inside a theme's `handle()`:

```php
$filters = \Qubus\EventDispatcher\ActionFilter\Filter::getInstance();

$ownsTheme = static function (\Vihzhuo\Contracts\ThemeContract $theme): bool {
    $folders = $theme instanceof \Vihzhuo\Theme
        ? $theme->getThemeFolders() : [$theme->getFolder()];
    return in_array(realpath(__DIR__), array_map('realpath', $folders), true);
};

$filters->addFilter('header.footer.slots', static function (array $slots, $theme) use ($ownsTheme): array {
    return $ownsTheme($theme)
        ? array_replace($slots, ['header' => ['my-navigation'], 'footer' => ['my-footer']])
        : $slots;
}, arguments: 2);

$filters->addFilter('header.footer.canvas.assets', static function (array $assets, $theme) use ($ownsTheme): array {
    if ($ownsTheme($theme)) {
        $assets['styles'] = array_merge($assets['styles'] ?? [], ['css/style.css']);
        $assets['scripts'] = array_merge($assets['scripts'] ?? [], ['js/theme.js']);
    }
    return $assets;
}, arguments: 2);
```

Scope callbacks to the supplied adapter rather than the current site's selection,
so they also work for children and do not affect unrelated preview themes. A child
can call `parent::handle()` and override these filters at a later priority, such as
`priority: 20`. Register the preview candidate's integration hooks before rendering
an independent preview; constructing its adapter does not initialize theme hooks.

When installed and activated, [BootstrapBusiness](https://getdevflow.com/extensions/getdevflow/bootstrap-business) theme registers its own `bb-navbar` and `bb-footer`
slots and matching assets. Its integration is inert without the active plugin.
The document head stays with the theme. A footer's `<footer>` element is replaced
while trailing scripts and document closing tags are preserved. Do not map a
document-head block as the header slot. Without a slots filter, published templates
remain available for explicit rendering but theme blocks are not replaced.

Canvas assets can be theme-relative paths, root-relative URLs, or HTTP(S) URLs.
Relative paths fall back through ancestors using the supplied adapter; choose the
versions used by your layout. Unsafe URL schemes and malformed values are ignored.
The plugin always supplies its own widget assets. Scripts on ordinary public pages
remain provided by the active theme. Move any previous `header_footer` configuration
into these filters; configuration mappings are no longer used.

Vihzhuo 2.1 or later is required. Child themes use Core's PHP theme-class inheritance; no separate parent map is needed. A child directory replaces a complete block or layout, so keep the original slot slugs when overriding header/footer blocks. Body previews, editor canvases, and mega menus use the configured adapter. For an independently supplied preview adapter, pass the same `$theme` to `Runtime::render($template, $theme, true)` and `Runtime::canvasAssets($theme)`. See the CMS [child-theme guide](https://github.com/getdevflow/cmf/blob/3.x/docs/3-0-upgrade/vihzhuo-child-themes.md).

For a custom CMS view that does not render Vihzhuo blocks, initialize Vihzhuo normally and render the selected location explicitly:

```php
use Plugin\HeaderFooterBuilder\Support\Runtime;

$context = [...Runtime::context(), 'content_type' => 'article'];
echo Runtime::renderLocation('header', $context, $vihzhuo->getTheme());
// Render the page content here.
echo Runtime::renderLocation('footer', $context, $vihzhuo->getTheme());
```

The plugin installs a Vihzhuo PageRenderer class replacement while active. If another extension also replaces PageRenderer, combine the renderer integrations before using both. Vihzhuo's full-page cache is disabled while this plugin is active so role conditions, menu changes, and shared-template edits are resolved immediately. Public mega menu responses are private and not cached by HTTP clients.

Deactivation preserves all templates and menus. Reactivation is safe and does not overwrite existing designs.


