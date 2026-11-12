<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Registry;
use Vcat\Vcsafetycheck\Domain\Model\Vulnerability;
use Vcat\Vcsafetycheck\Domain\Repository\VulnerabilityRepository;

/**
 * Orchestrates a full safety check:
 *   1. collect all installed packages + versions
 *   2. check API for known vulnerabilities
 *   3. refresh the TYPO3 version/end-of-life status
 *
 * This is the single entry point used by both the scheduler command and the
 * dashboard widget button, so both paths produce identical results.
 */
final class SafetyCheckService
{
    public function __construct(
        private readonly PackageCollector         $packageCollector,
        private readonly VulnerabilityApiClient   $vulnerabilityApiClient,
        private readonly VulnerabilityRepository  $repository,
        private readonly Typo3VersionCheckService $versionCheckService,
        private readonly Registry                 $registry,
        private readonly LoggerInterface          $logger,
    ) {}

    public const REGISTRY_NAMESPACE = 'vc_safety_check';
    public const REGISTRY_LAST_RUN = 'lastRun';

    public function getLastRunTime(): int
    {
        return (int)$this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_LAST_RUN, 0);
    }

    /**
     * @param array<int, array{name: string, version: string, type: string}>|null $packages
     *        optional explicit package list; when null all installed packages
     *        are collected automatically (the normal case)
     */
    public function run(int $runTime, ?array $packages = null): SafetyCheckResult
    {
        $runTime = $runTime > 0 ? $runTime : time();
        $packages ??= $this->packageCollector->collect();
        $this->logger->info('vc_safety_check: scanning {count} packages against the API', [
            'count' => count($packages),
        ]);

        $packageTypes = [];
        foreach ($packages as $package) {
            $packageTypes[strtolower($package['name'])] = $package['type'];
        }

        $found = 0;
        foreach ($this->vulnerabilityApiClient->queryBatch($packages) as $entry) {
            $type = $packageTypes[strtolower($entry['name'])] ?? '';
            foreach ($entry['vulnerabilities'] as $vulnData) {
                if (empty($vulnData['osv_id'])) {
                    continue;
                }
                $vulnerability = $this->buildVulnerability($vulnData, $entry['name'], $entry['version'], $type);
                $this->repository->upsert($vulnerability, $runTime);
                $found++;
            }
        }

        $removed = $this->repository->deleteStale($runTime);
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_LAST_RUN, $runTime);

        $versionStatus = $this->versionCheckService->getStatus(true);

        $result = new SafetyCheckResult(
            packagesScanned: count($packages),
            vulnerabilitiesFound: $found,
            removedStale: $removed,
            runTime: $runTime,
            severityCounts: $this->repository->countBySeverity(),
            typo3VersionStatus: $versionStatus,
        );

        $this->logger->info(
            'vc_safety_check: finished – {found} findings, {removed} stale entries removed',
            ['found' => $found, 'removed' => $removed]
        );

        return $result;
    }

    /**
     * Convert one vulnerability record from the API into a Vulnerability.
     *
     * @param array<string, mixed> $vulnData
     */
    private function buildVulnerability(array $vulnData, string $name, string $version, string $type): Vulnerability
    {
        $referenceUrl = (string)($vulnData['reference_url'] ?? '');
        return new Vulnerability(
            packageName: $name,
            packageVersion: $version,
            packageType: $type,
            ecosystem: 'Packagist',
            osvId: (string)$vulnData['osv_id'],
            aliases: array_values(array_filter(array_map('strval', $vulnData['aliases'] ?? []))),
            summary: (string)($vulnData['summary'] ?? ''),
            details: mb_substr((string)($vulnData['details'] ?? ''), 0, 60000),
            severity: strtoupper((string)($vulnData['severity'] ?? '')),
            fixedVersions: array_values(array_filter(array_map('strval', (array)($vulnData['fixed_version'] ?? [])))),
            referenceUrl: $referenceUrl,
        );
    }
}
