<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder;

use App\Infrastructure\Services\Plugin;
use App\Application\Devflow;
use App\Shared\Services\Registry;
use Plugin\HeaderFooterBuilder\Controller\BuilderController;
use Plugin\HeaderFooterBuilder\Editor\TemplateRenderer;
use Plugin\HeaderFooterBuilder\Repository\BuilderRepository;
use Plugin\HeaderFooterBuilder\Support\Runtime;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\ServerRequest;
use Qubus\Routing\Psr7Router;
use Vihzhuo\Extensions;
use Vihzhuo\Modules\GrapesJS\PageRenderer;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\plugin_basename;
use function App\Shared\Helpers\plugin_dir_path;
use function App\Shared\Helpers\plugin_url;
use function App\Shared\Helpers\add_plugins_submenu;
use function Codefy\Framework\Helpers\config;
use function dirname;
use function get_class;
use function Qubus\Security\Helpers\esc_html__;
use function Qubus\Security\Helpers\t__;

class HeaderFooterBuilderPlugin extends Plugin
{
    /**
     * @inheritDoc
     * @throws ReflectionException
     * @throws Exception
     */
    public function meta(): array
    {
        $plugin = [
            'name' => esc_html__(string: 'Header Footer Builder', domain: 'header-footer-builder'),
            'id' => 'header-footer-builder',
            'slug' => 'HeaderFooterBuilder',
            'author' => 'Joshua Parker',
            'version' => '1.0.0',
            'description' => t__(
                msgid: 'The easiest way to create a custom header and footer in the Vihzhuo pagebuilder.',
                domain: 'header-footer-builder'
            ),
            'basename' => plugin_basename(dirname(__FILE__)),
            'path' => plugin_dir_path(dirname(__FILE__)),
            'url' => plugin_url('', __CLASS__),
            'pluginUri' => 'https://github.com/getdevflow/header-footer-builder',
            'authorUri' => 'https://nomadicjosh.com/',
            'className' => get_class($this),
        ];

        Registry::getInstance()->set('header-footer-builder', $plugin);

        return $plugin;
    }

    /**
     * @inheritDoc
     * @throws TypeException
     * @throws ReflectionException
     */
    public function handle(): void
    {
        Runtime::boot(new BuilderRepository($this->dfdb));
        Extensions::registerLayout('hfb-canvas', __DIR__ . '/layouts/canvas');
        foreach (['section', 'navigation', 'site-logo', 'icon-button', 'top-bar', 'copyright'] as $widget) {
            Extensions::registerBlock('hfb-' . $widget, __DIR__ . '/blocks/' . $widget);
        }
        $settings = config()->array('vihzhuo');
        $settings['class_replacements'][PageRenderer::class] = TemplateRenderer::class;
        // Role/page-dependent fragments and mega menus must be resolved on every request.
        $settings['cache']['enabled'] = false;
        Devflow::$PHP->configContainer->setConfigKey('vihzhuo', $settings);
        Action::getInstance()->addAction('plugins_submenu', [$this, 'registerSubmenu']);
        Action::getInstance()->addAction('plugins_loaded', [$this, 'routes'], 1);
    }

    /**
     * @throws Exception
     */
    public function onActivation(): void
    {
        new BuilderRepository($this->dfdb)->migrate();
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function registerSubmenu(): void
    {
        echo add_plugins_submenu(
            menuTitle: 'Header Footer Builder',
            menuRoute: 'plugin/header-footer-builder',
            screen: 'header-footer-builder',
            permission: 'vihzhuo:manage'
        );
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \JsonException
     * @throws \Random\RandomException
     */
    public function routes(): void
    {
        $controller = new BuilderController(Runtime::repository());
        $router = Devflow::$PHP->make(Psr7Router::class);
        $router->group(
            ['prefix' => '/admin/plugin/header-footer-builder', 'middleware' => ['gate:vihzhuo:manage, /admin/']],
            static function ($route) use ($controller): void {
                // Qubus resolves request arguments by instantiating their concrete type.
                $route->get('/', static fn (ServerRequest $request) => $controller->index($request));
                $route->post('/save', static fn (ServerRequest $request) => $controller->save($request));
                $route->post('/delete', static fn (ServerRequest $request) => $controller->delete($request));
                $route->post('/menu/save', static fn (ServerRequest $request) => $controller->saveMenu($request));
                $route->post('/menu/delete', static fn (ServerRequest $request) => $controller->deleteMenu($request));
                $route->post('/menu/import', static fn (ServerRequest $request) => $controller->importMenu($request));
                $route->map(
                    ['GET', 'POST'],
                    '/editor/',
                    static fn (ServerRequest $request) => $controller->editor($request)
                );
                $route->get('/preview/', static fn (ServerRequest $request) => $controller->preview($request));
            }
        );
        $router->get(
            '/header-footer-builder/mega/{id}',
            static fn (ServerRequest $request, string $id) => $controller->mega($request, $id)
        );
    }
}
