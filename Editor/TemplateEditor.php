<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder\Editor;

use App\Infrastructure\Services\Vihzhuo\PageEditor;
use JsonException;
use Plugin\HeaderFooterBuilder\Support\Html;
use Plugin\HeaderFooterBuilder\Support\Runtime;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\Response;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Modules\GrapesJS\PageBuilder;

class TemplateEditor extends PageBuilder
{
    private string $kind = '';

    public function handleRequest(
        ServerRequestInterface $request,
        ?string $route = null,
        ?string $action = null,
        ?PageContract $page = null
    ): ?ResponseInterface {
        $postActions = ['store', 'upload', 'upload_delete', 'renderBlock', 'renderLanguageVariant'];
        if ($request->getMethod() !== 'POST' && in_array($action, $postActions, true)) {
            return new Response(status: 405, headers: ['Allow' => 'POST']);
        }
        if ($page === null || $page->get('hfb_kind') === null) {
            return new Response(status: 404);
        }
        $this->kind = (string) $page->get('hfb_kind');
        if ($action === 'store') {
            try {
                $input = (array) $request->getParsedBody();
                $data = json_decode((string) ($input['data'] ?? ''), true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($data) || !isset($data['html']) || (!is_array($data['html']) && !is_string($data['html']))) {
                    return JsonResponseFactory::create(['error' => 'Invalid template data.'], 422);
                }

                if (is_array($data['html']) && array_any($data['html'], static fn ($html): bool => !is_string($html))) {
                    return JsonResponseFactory::create(['error' => 'Invalid template HTML.'], 422);
                }

                return JsonResponseFactory::create(['success' => $this->updatePage($page, $data)]);
            } catch (\JsonException | \InvalidArgumentException $error) {
                return JsonResponseFactory::create(['error' => $error->getMessage()], 422);
            }
        }
        return parent::handleRequest($request, $route, $action, $page);
    }

    public function updatePage(PageContract $page, array $data): bool
    {
        return Runtime::repository()->saveData($page->getId(), $data);
    }

    public function getPageComponents(PageContract $page): array
    {
        $data = $page->getBuilderData();
        if (isset($data['components'])) {
            return parent::getPageComponents($page);
        }

        // Starter layouts have HTML but no GrapesJS component snapshot yet.
        // Vihzhuo expands these placeholders from the rendered pageBlocks map.
        $containers = is_array($data['html'] ?? null) ? $data['html'] : [$data['html'] ?? ''];
        return array_map(static fn (string $html): string => preg_replace_callback(
            '/\[block\s+slug="([a-zA-Z0-9_-]+)"(?:\s+id="([a-zA-Z0-9_-]+)")?\s*\]/',
            static fn (array $match): string => '<phpb-block slug="' . Html::escape($match[1]) . '" id="'
                . Html::escape($match[2] ?? $match[1]) . '"></phpb-block>',
            $html
        ), $containers);
    }

    /**
     * @throws TypeException
     * @throws Exception
     * @throws JsonException
     */
    public function customScripts(string $location, ?string $scripts = null): string
    {
        $custom = parent::customScripts($location, $scripts);
        if ($location !== 'head' || $scripts !== null) {
            return $custom;
        }
        // Reuse the application's authenticated editor CSRF integration.
        return $custom . new PageEditor()->customScripts('head')
        . '<script>window.HfbTemplateKind=' . json_encode($this->kind, JSON_HEX_TAG | JSON_THROW_ON_ERROR) . ';</script>'
        . '<script src="' . Html::escape(Runtime::asset('js/editor.js')) . '"></script>';
    }
}
