<?php

return [
    'custom' => [
        // Layout
        'myLayout' => 'vertical', // Opções: vertical, horizontal, blank, front
        'myTheme' => 'theme-default', // Opções: theme-default, theme-bordered, theme-semi-dark
        'myStyle' => 'green', // Opções: light, dark, system
        'myRTLSupport' => true, // Suporte para RTL (Right-to-Left)
        'myRTLMode' => false, // Ativar modo RTL
        'hasCustomizer' => true, // Habilitar customizer
        'showDropdownOnHover' => true, // Mostrar dropdown ao passar o mouse
        'displayCustomizer' => false, // Exibir customizer na interface
        'contentLayout' => 'compact', // Layout do conteúdo: compact, wide
        'headerType' => 'fixed',
        'navbarType' => 'fixed',
        'menuFixed' => true,
        'menuCollapsed' => false,
        'footerFixed' => false,
        'customizerControls' => [
            'rtl',
            'style',
            'headerType',
            'contentLayout',
            'layoutCollapsed',
            'showDropdownOnHover',
            'layoutNavbarOptions',
            'themes',
        ],
    ],
];