<?php

return [
    'category' => 'Header & Footer', 'title' => 'Icon Button', 'icon' => 'fa fa-circle-arrow-right',
    'settings' => [
        'label' => ['type' => 'text', 'label' => 'Button text / accessible label', 'value' => 'Get in touch'],
        'link' => ['type' => 'text', 'label' => 'Link', 'value' => '/contact'],
        'icon' => ['type' => 'text', 'label' => 'Icon (symbol or Font Awesome classes)', 'value' => '→'],
        'position' => ['type' => 'select', 'label' => 'Icon position', 'value' => 'after', 'options' => [
            ['value' => 'before', 'label' => 'Before text'], ['value' => 'after', 'label' => 'After text'],
            ['value' => 'only', 'label' => 'Icon only'],
        ]],
        'new-window' => ['type' => 'yes_no', 'label' => 'Open in new window', 'value' => '0'],
    ],
];
