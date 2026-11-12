<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\Widgets;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Dashboard\Widgets\AdditionalCssInterface;
use TYPO3\CMS\Dashboard\Widgets\JavaScriptInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use Vcat\Vcsafetycheck\View\WidgetContentRenderer;

/**
 * Dashboard widget showing the current list of known vulnerabilities
 *
 * Implements only the stable dashboard interfaces (WidgetInterface plus the
 * optional JavaScript/CSS mix-ins) so it works unchanged on TYPO3 12, 13 and 14.
 */
final class SafetyCheckWidget implements WidgetInterface, JavaScriptInterface, AdditionalCssInterface
{
    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly WidgetContentRenderer $renderer,
        private readonly array $options = [],
    ) {}

    public function renderWidgetContent(): string
    {
        return $this->renderer->render();
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return JavaScriptModuleInstruction[]
     */
    public function getJavaScriptModuleInstructions(): array
    {
        return [
            JavaScriptModuleInstruction::create('@vcat/vc_safety_check/safety-check-widget.js'),
        ];
    }

    /**
     * @return string[]
     */
    public function getCssFiles(): array
    {
        return [
            'EXT:vc_safety_check/Resources/Public/Css/widget.css',
        ];
    }
}
