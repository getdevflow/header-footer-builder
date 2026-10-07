<?php

return [
    'category' => 'Header & Footer', 'title' => 'Copyright', 'icon' => 'fa fa-copyright', 'cache' => false,
    'settings' => [
        'text' => [
            'type' => 'text',
            'label' => 'Text ({year}, {company})',
            'value' => '© {year} {company}. All rights reserved.'
        ],
        'company' => ['type' => 'text', 'label' => 'Company (blank uses site name)'],
        'start-year' => ['type' => 'text', 'label' => 'Start year (optional year range)'],
        'link' => ['type' => 'text', 'label' => 'Company URL (optional)'],
    ],
];
