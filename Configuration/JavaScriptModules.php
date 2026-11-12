<?php

/**
 * Import map for the widget's ES module.
 */
return [
    'dependencies' => ['backend', 'dashboard'],
    'imports' => [
        '@vcat/vc_safety_check/' => 'EXT:vc_safety_check/Resources/Public/JavaScript/',
    ],
];
