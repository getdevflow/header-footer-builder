<?php

use Plugin\HeaderFooterBuilder\Support\Html;
use Plugin\HeaderFooterBuilder\Support\Runtime;

use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\site_url;
use function Codefy\Framework\Helpers\config;
use function Qubus\Security\Helpers\__observer;

$name = (string) get_option('sitename', config()->string('app.name', 'Website'));
$source = $block->setting('source', true) ?: (string) get_option('site_logo', '');
$source = __observer()->filter->applyFilter('header.footer.site.logo', $source);
$link = $block->setting('link', true) ?: site_url();
echo Runtime::assets();
?>
<a class="hfb-logo" href="<?= Html::escape(Html::url($link)); ?>" style="--hfb-logo-width:<?= Html::escape(Html::length($block->setting('width', true), '180px')); ?>;--hfb-logo-tablet:<?= Html::escape(Html::length($block->setting('tablet-width', true), '150px')); ?>;--hfb-logo-mobile:<?= Html::escape(Html::length($block->setting('mobile-width', true), '120px')); ?>">
    <?php if ($source && Html::url($source) !== '#') : ?>
        <img src="<?= Html::escape(Html::url($source)); ?>" alt="<?= Html::escape($block->setting('alt', true) ?: $name); ?>" loading="eager">
    <?php else : ?>
        <span><?= Html::escape($name); ?></span>
    <?php endif; ?>
</a>
