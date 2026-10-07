<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder\Editor;

use Plugin\HeaderFooterBuilder\Support\Runtime;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;
use Vihzhuo\Modules\GrapesJS\PageRenderer;

class TemplateRenderer extends PageRenderer
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws \Exception
     */
    public function renderBlock(string $slug, ?string $id = null, ?array $context = null, int $maxDepth = 25): string
    {
        if ($this->forPageBuilder || $this->page->get('hfb_kind')) {
            return parent::renderBlock($slug, $id, $context, $maxDepth);
        }
        $kind = Runtime::slot($slug, $this->theme, $this->page);
        if ($kind === null) {
            return parent::renderBlock($slug, $id, $context, $maxDepth);
        }

        $template = Runtime::selected($kind, Runtime::context($this->page));
        if ($template === null) {
            return parent::renderBlock($slug, $id, $context, $maxDepth);
        }

        $replacement = Runtime::wrap($kind, Runtime::render($template, $this->theme));
        if ($kind === 'footer') {
            // Some theme footer blocks also own document scripts and closing tags.
            $original = parent::renderBlock($slug, $id, $context, $maxDepth);
            if (preg_match('/<footer\b[^>]*>.*?<\/footer>/is', $original)) {
                return preg_replace_callback('/<footer\b[^>]*>.*?<\/footer>/is', static fn (): string => $replacement, $original, 1);
            }
        }
        return $replacement;
    }
}
