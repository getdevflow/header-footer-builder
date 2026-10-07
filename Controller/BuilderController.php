<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder\Controller;

use App\Application\Devflow;
use App\Infrastructure\Services\Vihzhuo\DevflowPageBuilder;
use Exception;
use InvalidArgumentException;
use JsonException;
use Plugin\HeaderFooterBuilder\Editor\TemplateEditor;
use Plugin\HeaderFooterBuilder\Repository\BuilderRepository;
use Plugin\HeaderFooterBuilder\Support\Html;
use Plugin\HeaderFooterBuilder\Support\Runtime;
use Plugin\MenuBuilder\Domain\MenuItem;
use Plugin\MenuBuilder\MenuBuilderPlugin;
use Plugin\MenuBuilder\Repository\NavigationRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\Factories\HtmlResponseFactory;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\Factories\RedirectResponseFactory;
use Random\RandomException;
use ReflectionException;
use Vihzhuo\Core\HttpContext;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\is_plugin_activated;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\view;

final readonly class BuilderController
{
    public function __construct(private BuilderRepository $repository)
    {
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $imports = [];

        if ($this->hasMenuBuilder()) {
            $imports = Devflow::$PHP->make(NavigationRepository::class)->menus();
        }

        return view('plugin::HeaderFooterBuilder/view/index', [
            'templates' => $this->repository->templates(), 'menus' => $this->repository->menus(),
            'selected' => $this->repository->template((string) ($query['template'] ?? '')),
            'menu' => $this->repository->menu((string) ($query['menu'] ?? '')),
            'targets' => $this->repository->targets(), 'imports' => $imports,
            'saved' => isset($query['saved']),
        ]);
    }

    /**
     * @throws \Qubus\Exception\Exception
     */
    private function redirect(string $query = ''): ResponseInterface
    {
        return RedirectResponseFactory::create(
            admin_url('plugin/header-footer-builder/' . $query),
            303
        );
    }

    /**
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    private function error(InvalidArgumentException | \JsonException $error): ResponseInterface
    {
        return HtmlResponseFactory::create('<p>' . Html::escape($error->getMessage()) . '</p><p><a href="'
            . Html::escape(admin_url('plugin/header-footer-builder/')) . '">Back to Header Footer Builder</a></p>', 422);
    }

    /**
     * @throws RandomException
     * @throws \Qubus\Exception\Exception
     * @throws JsonException
     */
    public function save(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $id = $this->repository->saveTemplate((array) $request->getParsedBody());
            return $this->redirect('?template=' . rawurlencode($id) . '&saved=1');
        } catch (InvalidArgumentException $error) {
            return $this->error($error);
        }
    }

    /**
     * @throws \Qubus\Exception\Exception
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $this->repository->deleteTemplate((string) (((array) $request->getParsedBody())['id'] ?? ''));
        return $this->redirect('?saved=1');
    }

    /**
     * @throws RandomException
     * @throws \Qubus\Exception\Exception
     */
    public function saveMenu(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $input = (array) $request->getParsedBody();
            $input['items'] = json_decode((string) ($input['items_json'] ?? '[]'), true, 32, JSON_THROW_ON_ERROR);

            if (!is_array($input['items'])) {
                throw new InvalidArgumentException('Invalid menu items.');
            }

            $id = $this->repository->saveMenu($input);

            return $this->redirect('?menu=' . rawurlencode($id) . '&saved=1');
        } catch (InvalidArgumentException | \JsonException $error) {
            return $this->error($error);
        }
    }

    /**
     * @throws \Qubus\Exception\Exception
     */
    public function deleteMenu(ServerRequestInterface $request): ResponseInterface
    {
        $this->repository->deleteMenu((string) (((array) $request->getParsedBody())['id'] ?? ''));
        return $this->redirect('?saved=1');
    }

    private function hasMenuBuilder(): bool
    {
        return class_exists(MenuBuilderPlugin::class) && is_plugin_activated(MenuBuilderPlugin::class);
    }

    /**
     * @throws RandomException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws JsonException
     * @throws Exception
     */
    public function importMenu(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->hasMenuBuilder()) {
            return JsonResponseFactory::create(['error' => 'MenuBuilder is not active.'], 404);
        }

        $id = (string) (((array) $request->getParsedBody())['source_id'] ?? '');
        $source = Devflow::$PHP->make(NavigationRepository::class)->findMenu($id);

        if (!$source) {
            return JsonResponseFactory::create(['error' => 'Menu not found.'], 404);
        }

        $convert = static function (array $items) use (&$convert): array {
            return array_map(static fn (MenuItem $item): array => [
                'label' => $item->label(), 'url' => $item->url(), 'target' => $item->target(),
                'children' => $convert($item->children()),
            ], $items);
        };

        $menuId = $this->repository->saveMenu(['name' => $source->name(), 'items' => $convert($source->rootItems())]);

        return $this->redirect('?menu=' . rawurlencode($menuId) . '&saved=1');
    }

    /**
     * @throws TypeException
     */
    private function builder(ServerRequestInterface $request, bool $editor = false): DevflowPageBuilder
    {
        HttpContext::setRequest($request);

        $settings = config()->array('vihzhuo');

        if ($editor) {
            $settings['pagebuilder']['class'] = TemplateEditor::class;
            $settings['pagebuilder']['url'] = '/admin/plugin/header-footer-builder/editor/';
            $settings['pagebuilder']['actions']['back'] = '/admin/plugin/header-footer-builder/';
        }

        return new DevflowPageBuilder($settings);
    }

    /**
     * @throws TypeException
     * @throws JsonException
     * @throws Exception
     */
    public function editor(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $template = $this->repository->template((string) ($query['page'] ?? ''));

        if (!$template) {
            return HtmlResponseFactory::create('Template not found.', 404);
        }

        $this->builder($request, true);
        $action = isset($query['action']) && is_string($query['action']) ? $query['action'] : null;

        if (!in_array($action, [null, 'edit', 'store', 'upload', 'upload_delete', 'renderBlock', 'renderLanguageVariant'], true)) {
            return HtmlResponseFactory::create('Unknown editor action.', 404);
        }

        return new TemplateEditor()->handleRequest($request, null, $action, Runtime::page($template))
        ?? HtmlResponseFactory::create('Invalid editor request.', 422);
    }

    /**
     * @throws ReflectionException
     * @throws TypeException
     * @throws JsonException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function preview(ServerRequestInterface $request): ResponseInterface
    {
        $template = $this->repository->template((string) ($request->getQueryParams()['id'] ?? ''));

        if (!$template) {
            return HtmlResponseFactory::create('Template not found.', 404);
        }

        $theme = $this->builder($request)->getTheme();
        $content = Runtime::render($template, $theme);

        return HtmlResponseFactory::create('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
            . Html::escape($template['name']) . '</title>' . Runtime::canvasAssets($theme) . '</head><body data-hfb-kind="'
            . Html::escape($template['kind']) . '">' . $content . '<div style="min-height:120vh"></div></body></html>')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws JsonException
     * @throws ReflectionException
     * @throws TypeException
     * @throws Exception
     */
    public function mega(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $template = $this->repository->template($id);

        if (!$template || !Runtime::megaAvailable($template, Runtime::context()['roles'])) {
            return JsonResponseFactory::create(['error' => 'Published mega menu not found.'], 404);
        }

        $language = $request->getQueryParams()['language'] ?? null;
        $builder = $this->builder($request);
        $theme = $builder->getTheme();
        $locale = is_string($language) && isset(phpb_active_languages()[$language]) ? $language : null;

        return JsonResponseFactory::create(['html' => Runtime::render($template, $theme, language: $locale)])
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
