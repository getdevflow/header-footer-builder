<?php

use Plugin\HeaderFooterBuilder\Support\Html;
use Plugin\HeaderFooterBuilder\Support\Navigation;
use Plugin\HeaderFooterBuilder\Support\Runtime;

$menu = Runtime::repository()?->menu($block->setting('menu', true));
if (!$menu) {
    echo '<div class="hfb-menu-placeholder">' . Html::escape(phpb_in_editmode() ? 'Create a menu in Header Footer Builder, then choose it in Settings.' : '') . '</div>';
    return;
}
echo Navigation::render($menu['items'], $block->setting('label', true), $block->setting('breakpoint', true));
