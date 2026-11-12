<?php

declare(strict_types=1);

use Vcat\Vcsafetycheck\Controller\SafetyCheckAjaxController;

return [
    'vcsafetycheck_run' => [
        'path' => '/vc_safety_check/run',
        'target' => SafetyCheckAjaxController::class . '::runAction',
    ],
    'vcsafetycheck_list' => [
        'path' => '/vc_safety_check/list',
        'target' => SafetyCheckAjaxController::class . '::listAction',
    ],
];
