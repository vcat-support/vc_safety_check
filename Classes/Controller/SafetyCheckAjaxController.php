<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Vcat\Vcsafetycheck\Service\SafetyCheckService;
use Vcat\Vcsafetycheck\View\WidgetContentRenderer;

/**
 * Backend AJAX endpoints behind the dashboard widget:
 *   - runAction:  triggers a fresh vulnerability scan (widget "run"-button)
 *   - listAction: returns the freshly rendered widget body to refresh the content
 */
final class SafetyCheckAjaxController
{
    public function __construct(
        private readonly SafetyCheckService $safetyCheckService,
        private readonly WidgetContentRenderer $renderer,
        private readonly LoggerInterface $logger,
    ) {}

    public function runAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $result = $this->safetyCheckService->run(time());
        } catch (\Throwable $e) {
            $this->logger->error('vc_safety_check: manual run failed: {msg}', ['msg' => $e->getMessage()]);
            return new JsonResponse([
                'success' => false,
                'message' => sprintf(
                    $GLOBALS['LANG']->sL('LLL:EXT:vc_safety_check/Resources/Private/Language/locallang.xlf:widget.error.runfailed'),
                    $e->getMessage()
                ),
            ], 500);
        }

        return new JsonResponse([
            'success' => true,
            'packagesScanned' => $result->packagesScanned,
            'vulnerabilitiesFound' => $result->vulnerabilitiesFound,
            'removedStale' => $result->removedStale,
            'severityCounts' => $result->severityCounts,
            'html' => $this->renderer->render($request),
        ]);
    }

    public function listAction(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'html' => $this->renderer->render($request),
        ]);
    }
}
