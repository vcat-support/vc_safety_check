<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\View;

use Psr\Http\Message\ServerRequestInterface;
use Vcat\Vcsafetycheck\Domain\Model\Typo3VersionStatus;
use Vcat\Vcsafetycheck\Domain\Model\Vulnerability;
use Vcat\Vcsafetycheck\Domain\Repository\VulnerabilityRepository;
use Vcat\Vcsafetycheck\Service\SafetyCheckService;
use Vcat\Vcsafetycheck\Service\Typo3VersionCheckService;

/**
 * Prepares the view data for the dashboard widget body and renders it
 * through the Fluid template Resources/Private/Templates/Widget/Content.html.
 */
final class WidgetContentRenderer
{
    public function __construct(
        private readonly VulnerabilityRepository $repository,
        private readonly SafetyCheckService $safetyCheckService,
        private readonly Typo3VersionCheckService $versionCheckService,
        private readonly TemplateRenderer $templateRenderer,
    ) {}

    public function render(?ServerRequestInterface $request = null): string
    {
        $vulnerabilities = $this->repository->findAll();
        $lastRun = $this->safetyCheckService->getLastRunTime();

        return $this->templateRenderer->render(
            'Widget/Content',
            [
                'lastRun' => $lastRun,
                'lastRunFormatted' => $this->formatDateTime($lastRun),
                'total' => count($vulnerabilities),
                'versionStatus' => $this->buildVersionStatus($this->versionCheckService->getStatus()),
                'systemStatus' => $this->buildSystemStatus($vulnerabilities),
                'severityCounts' => $this->buildSeverityCounts($this->repository->countBySeverity()),
                'vulnerabilities' => $this->buildVulnerabilityRows($vulnerabilities),
            ],
            $request
        );
    }

    private function formatDate(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        return date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? 'd.m.Y', $timestamp);
    }

    private function formatDateTime(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        $dateFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? 'd.m.Y';
        $timeFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] ?? 'H:i';

        return date($dateFormat . ' ' . $timeFormat, $timestamp);
    }

    /**
     * Takes a \Typo3VersionStatus and make it displayready
     *
     * @return array<string, mixed>
     */
    private function buildVersionStatus(Typo3VersionStatus $status): array
    {
        return [
            'state' => $status->state,
            'severityClass' => $status->getSeverityClass(),
            'actionRequired' => $status->isActionRequired(),
            'currentVersion' => $status->currentVersion,
            'branch' => $status->branch,
            'cycle' => $status->cycle,
            'latestVersion' => $status->latestVersion,
            'latestCycleVersion' => $status->latestCycleVersion,
            'updateAvailable' => $status->updateAvailable,
            'majorUpdateAvailable' => $status->majorUpdateAvailable,
            'activeSupportEnd' => $this->formatDate($status->activeSupportEnd),
            'activeSupportEnded' => $status->activeSupportEnded,
            'securitySupportEnd' => $this->formatDate($status->securitySupportEnd),
            'securitySupportEnded' => $status->securitySupportEnded,
            'eltsEnd' => $this->formatDate($status->eltsEnd),
            'eltsEnded' => $status->eltsEnded,
            'checkedAt' => $this->formatDateTime($status->checkedAt),
            'error' => $status->error,
        ];
    }

    /**
     * All severity counts in order. The UNKNOWN bucket is
     * only kept when it actually holds findings, since it makes the list too long.
     * The others are always shown.
     *
     * @param array<string, int> $counts
     * @return list<array{severity: string, severityClass: string, count: int}>
     */
    private function buildSeverityCounts(array $counts): array
    {
        $result = [];
        foreach (Vulnerability::SEVERITY_ORDER as $severity) {
            $count = $counts[$severity] ?? 0;
            if ($count === 0 && $severity === Vulnerability::SEVERITY_UNKNOWN) {
                continue;
            }
            $result[] = [
                'severity' => $severity,
                'severityClass' => $this->severityClass($severity),
                'severityColor' => strtolower($severity),
                'count' => $count,
            ];
        }

        return $result;
    }

    /**
     * @param Vulnerability[] $vulnerabilities
     * @return list<array<string, string|string[]>>
     */
    private function buildVulnerabilityRows(array $vulnerabilities): array
    {
        $rows = [];
        foreach ($vulnerabilities as $vulnerability) {
            $rows[] = [
                'severity' => $vulnerability->severity,
                'severityClass' => $this->severityClass($vulnerability->severity),
                'severityColor' => strtolower($vulnerability->severity),
                'packageName' => $vulnerability->packageName,
                'packageType' => $vulnerability->packageType,
                'packageVersion' => $vulnerability->packageVersion,
                'fixedVersions' => $vulnerability->fixedVersions,
                'osvId' => $vulnerability->osvId,
                'advisoryUrl' => $vulnerability->referenceUrl,
                'summary' => $this->shorten($vulnerability->summary ?: $vulnerability->details),
            ];
        }

        return $rows;
    }

    /**
     * The most severe finding, used as the overall security status of the
     * installation. Falls back to an empty "all clear" status.
     *
     * @param Vulnerability[] $vulnerabilities
     * @return array<string, string>
     */
    private function buildSystemStatus(array $vulnerabilities): array
    {
        $mostSevere = null;
        foreach ($vulnerabilities as $vulnerability) {
            if ($mostSevere === null || $vulnerability->getSeverityWeight() > $mostSevere->getSeverityWeight()) {
                $mostSevere = $vulnerability;
            }
        }

        if ($mostSevere === null) {
            return [
                'severity' => 'OK',
                'severityClass' => $this->severityClass('ok'),
                'severityColor' => 'ok',
            ];
        } else {
            return [
                'severity' => $mostSevere->severity,
                'severityClass' => $this->severityClass($mostSevere->severity),
                'severityColor' => strtolower($mostSevere->severity),
            ];
        }
    }

    private function severityClass(string $severity): string
    {
        return 'vcsafetycheck-sev-' . strtolower($severity);
    }

    private function shorten(string $text, int $length = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 1) . '…';
    }
}
