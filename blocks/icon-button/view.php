<?php

use Plugin\HeaderFooterBuilder\Support\Html;
use Plugin\HeaderFooterBuilder\Support\Runtime;

$icon = $block->setting('icon', true);
$iconHtml = preg_match('/^(?:fa[srlb]?|fa-solid|fa-regular|fa-brands)\s+[a-z0-9 -]+$/i', $icon)
? '<i aria-hidden="true" class="' . Html::escape($icon) . '"></i>' : '<span aria-hidden="true">' . Html::escape($icon) . '</span>';
$position = $block->setting('position', true);
echo Runtime::assets();
?>
<a class="hfb-icon-button" href="<?= Html::escape(Html::url($block->setting('link', true))); ?>" aria-label="<?= $block->setting('label'); ?>"<?= $block->setting('new-window', true) === '1' ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
    <?= $position !== 'after' ? $iconHtml : ''; ?>
    <?= $position !== 'only' ? '<span>' . $block->setting('label') . '</span>' : ''; ?>
    <?= $position === 'after' ? $iconHtml : ''; ?>
</a>
