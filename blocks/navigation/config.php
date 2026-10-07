<?php

use Plugin\HeaderFooterBuilder\Support\Runtime;

$options = [['value' => '', 'label' => 'Choose a menu']];
foreach (Runtime::repository()?->menus() ?? [] as $menu) {
    $options[] = ['value' => $menu['id'], 'label' => $menu['name']];
}

return [
    'category' => 'Header & Footer', 'title' => 'Navigation Menu', 'icon' => 'fa fa-bars',
    'settings' => [
        'menu' => ['type' => 'select', 'label' => 'Menu', 'options' => $options],
        'label' => ['type' => 'text', 'label' => 'Accessible label', 'value' => 'Main navigation'],
        'breakpoint' => ['type' => 'text', 'label' => 'Mobile breakpoint (px)', 'value' => '768'],
    ],
];
