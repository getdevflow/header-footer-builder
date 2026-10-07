<?php

return [
    'category' => 'Header & Footer', 'title' => 'Site Logo', 'icon' => 'fa fa-image',
    'settings' => [
        'source' => ['type' => 'image', 'label' => 'Logo (blank uses site logo)'],
        'link' => ['type' => 'text', 'label' => 'Link (blank uses homepage)'],
        'alt' => ['type' => 'text', 'label' => 'Alternative text (blank uses site name)'],
        'width' => ['type' => 'text', 'label' => 'Desktop width (px, %, vw, rem)', 'value' => '180px'],
        'tablet-width' => ['type' => 'text', 'label' => 'Tablet width', 'value' => '150px'],
        'mobile-width' => ['type' => 'text', 'label' => 'Mobile width', 'value' => '120px'],
    ],
];
