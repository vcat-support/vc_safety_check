<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'VC Safety Check',
    'description' => 'Checks all installed extensions, core packages and their dependencies for known security vulnerabilities via our API, stores the results and displays them in a dashboard widget.',
    'category' => 'module',
    'author' => 'VCAT Consulting GmbH',
    'author_email' => 'team.t3@vcat.de',
    'state' => 'stable',
    'version' => '1.0.2',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.4.99',
            'typo3' => '12.4.0-14.99.99',
            'dashboard' => '12.4.0-14.99.99',
            'scheduler' => '12.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
