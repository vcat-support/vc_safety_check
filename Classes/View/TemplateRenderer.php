<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\View;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Renders a Fluid-Template.
 *
 * Hides the differences between TYPO3 13 and v14, where StandaloneView no longer exists.
 * Use ViewFactoryInterface if able and for v12 falls back to StandaloneView.
 * Both classes are resolved lazily via makeInstance so the
 * branch that does not exist in the current core version is never touched.
 */
final class TemplateRenderer
{
    private const TEMPLATE_ROOT_PATHS = ['EXT:vc_safety_check/Resources/Private/Templates/'];
    private const PARTIAL_ROOT_PATHS = ['EXT:vc_safety_check/Resources/Private/Partials/'];
    private const LAYOUT_ROOT_PATHS = ['EXT:vc_safety_check/Resources/Private/Layouts/'];

    /**
     * @param string $templateName template path below Resources/Private/Templates without file extension
     * @param array<string, mixed> $variables
     */
    public function render(string $templateName, array $variables = [], ?ServerRequestInterface $request = null): string
    {
        $request ??= $GLOBALS['TYPO3_REQUEST'] ?? null;

        if (interface_exists(ViewFactoryInterface::class)) {
            $view = GeneralUtility::makeInstance(ViewFactoryInterface::class)->create(
                new ViewFactoryData(
                    templateRootPaths: self::TEMPLATE_ROOT_PATHS,
                    partialRootPaths: self::PARTIAL_ROOT_PATHS,
                    layoutRootPaths: self::LAYOUT_ROOT_PATHS,
                    request: $request,
                )
            );
            $view->assignMultiple($variables);

            return $view->render($templateName);
        }

        // TYPO3 12 only: ViewFactoryInterface does not exist yet.
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplateRootPaths(self::TEMPLATE_ROOT_PATHS);
        $view->setPartialRootPaths(self::PARTIAL_ROOT_PATHS);
        $view->setLayoutRootPaths(self::LAYOUT_ROOT_PATHS);
        if ($request !== null) {
            $view->setRequest($request);
        }
        $view->setTemplate($templateName);
        $view->assignMultiple($variables);

        return $view->render();
    }
}
