<?php

use Plugin\HeaderFooterBuilder\Support\Html;
use Plugin\HeaderFooterBuilder\Support\Runtime;

echo Runtime::assets();
?>
<div class="hfb-top-bar">
    <span><?= $block->setting('text'); ?></span>
    <?php if ($block->setting('email', true)) : ?>
        <a href="<?= Html::escape(Html::url('mailto:' . $block->setting('email', true))); ?>"><?= $block->setting('email'); ?></a>
    <?php endif; ?>
    <?php if ($block->setting('phone', true)) : ?>
        <a href="<?= Html::escape(Html::url('tel:' . preg_replace('/[^+0-9]/', '', $block->setting('phone', true)))); ?>"><?= $block->setting('phone'); ?></a>
    <?php endif; ?>
    <span class="hfb-social">
        <?php for ($index = 1; $index <= 3; $index++) :
            $url = $block->setting('social-' . $index . '-url', true);
            $icon = $block->setting('social-' . $index . '-icon', true);
            if (!$url) {
                continue;
            }
            ?>
        <a href="<?= Html::escape(Html::url($url)); ?>" aria-label="<?= $block->setting('social-' . $index . '-label'); ?>" target="_blank" rel="noopener noreferrer">
            <?php if (preg_match('/^(?:fa[srlb]?|fa-solid|fa-regular|fa-brands)\s+[a-z0-9 -]+$/i', $icon)) : ?>
                <i aria-hidden="true" class="<?= Html::escape($icon); ?>"></i>
            <?php else : ?>
                <span aria-hidden="true"><?= Html::escape($icon); ?></span>
            <?php endif; ?>
        </a>
        <?php endfor; ?>
    </span>
</div>
