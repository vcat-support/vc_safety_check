<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Registry;
use Vcat\Vcsafetycheck\Domain\Model\Typo3VersionStatus;

/**
 * Compares the installed TYPO3 version with the release cycle data from the Version API
 *
 * The response is cached in sys_registry for {CACHE_LIFETIME} so rendering the
 * dashboard widget does not trigger an request every time. A full safety
 * check run always refreshes it. If the API is unreachable
 * the last known payload is used, and only when there is none at
 * all the status becomes "unknown".
 */
final class Typo3VersionCheckService
{
    public const REGISTRY_RELEASES = 'versionReleases';

    private const CACHE_LIFETIME = 86400;

    public function __construct(
        private readonly Typo3VersionApiClient $apiClient,
        private readonly Typo3Version          $typo3Version,
        private readonly Registry              $registry,
        private readonly LoggerInterface       $logger,
    ) {}

    /**
     * @param bool $forceRefresh ignore the cached payload and query the API
     */
    public function getStatus(bool $forceRefresh = false): Typo3VersionStatus
    {
        $currentVersion = $this->typo3Version->getVersion();
        $branch = $this->typo3Version->getBranch();
        $major = $this->typo3Version->getMajorVersion();

        $releaseData = $this->getReleases($major, $forceRefresh);
        $cycle = $releaseData['cycle'];
        $newest = $releaseData['newest'];
        $fetchedAt = $releaseData['fetchedAt'];
        $error = $releaseData['error'];

        if ($cycle === null || $newest === null) {
            return new Typo3VersionStatus(
                currentVersion: $currentVersion,
                branch: $branch,
                checkedAt: $fetchedAt,
                error: $error !== '' ? $error : 'Version API data unavailable',
            );
        }

        $latestCycleVersion = (string)($cycle['latest'] ?? '');
        $updateAvailable = $latestCycleVersion !== '' && version_compare($currentVersion, $latestCycleVersion, '<');

        $latestVersion = (string)($newest['latest'] ?? '');
        $majorUpdateAvailable = (int)($newest['major'] ?? 0) > $major;

        $activeSupportEnd = $this->toTimestamp($cycle['active_until'] ?? null);
        $securitySupportEnd = $this->toTimestamp($cycle['security_until'] ?? null);
        $eltsEnd = $this->toTimestamp($cycle['extended_until'] ?? null);

        $activeSupportEnded = $this->hasPassed($activeSupportEnd);
        $securitySupportEnded = $this->hasPassed($securitySupportEnd);
        $eltsEnded = $this->hasPassed($eltsEnd);

        return new Typo3VersionStatus(
            currentVersion: $currentVersion,
            branch: $branch,
            cycle: (string)($cycle['major'] ?? $major),
            latestVersion: $latestVersion,
            latestCycleVersion: $latestCycleVersion,
            state: $this->resolveState($eltsEnded, $securitySupportEnded, $updateAvailable),
            updateAvailable: $updateAvailable,
            majorUpdateAvailable: $majorUpdateAvailable,
            activeSupportEnd: $activeSupportEnd,
            activeSupportEnded: $activeSupportEnded,
            securitySupportEnd: $securitySupportEnd,
            securitySupportEnded: $securitySupportEnded,
            eltsEnd: $eltsEnd,
            eltsEnded: $eltsEnded,
            checkedAt: $fetchedAt,
        );
    }

    private function resolveState(bool $eltsEnded, bool $securitySupportEnded, bool $updateAvailable): string
    {
        if ($eltsEnded) {
            return Typo3VersionStatus::STATE_EOES;
        }
        if ($securitySupportEnded) {
            return Typo3VersionStatus::STATE_EOL;
        }
        if ($updateAvailable) {
            return Typo3VersionStatus::STATE_UPDATE;
        }
        return Typo3VersionStatus::STATE_OK;
    }

    /**
     * Release data of the installed major version plus the newest release cycle.
     * The cache is only valid for the major version it was fetched for, so it is
     * ignored right after a major upgrade.
     *
     * @return array{cycle: array<string, mixed>|null, newest: array<string, mixed>|null, fetchedAt: int, error: string}
     */
    private function getReleases(int $major, bool $forceRefresh): array
    {
        $cached = $this->registry->get(SafetyCheckService::REGISTRY_NAMESPACE, self::REGISTRY_RELEASES);
        $cachedCycle = is_array($cached['cycle'] ?? null) ? $cached['cycle'] : [];
        $cachedNewest = is_array($cached['newest'] ?? null) ? $cached['newest'] : [];
        $cachedAt = (int)($cached['fetchedAt'] ?? 0);

        if (!$forceRefresh && ($cachedAt + self::CACHE_LIFETIME) > time()) {
            return ['cycle' => $cachedCycle, 'newest' => $cachedNewest, 'fetchedAt' => $cachedAt, 'error' => ''];
        }

        try {
            $cycle = $this->apiClient->fetchTypo3Release($major);
            $newest = $this->apiClient->fetchNewestTypo3Release();
        } catch (\Throwable $e) {
            $this->logger->warning('vc_safety_check: version check could not refresh data: {msg}', [
                'msg' => $e->getMessage(),
            ]);
            // Serve the cached data instead of nothing, may still be null.
            return ['cycle' => $cachedCycle, 'newest' => $cachedNewest, 'fetchedAt' => $cachedAt, 'error' => $e->getMessage()];
        }

        $fetchedAt = time();
        $this->registry->set(SafetyCheckService::REGISTRY_NAMESPACE, self::REGISTRY_RELEASES, [
            'fetchedAt' => $fetchedAt,
            'cycle' => $cycle,
            'newest' => $newest,
        ]);

        return ['cycle' => $cycle, 'newest' => $newest, 'fetchedAt' => $fetchedAt, 'error' => ''];
    }

    /**
     * check if the timestamp is in the past, no timestamp means no end date is known
     */
    private function hasPassed(int $timestamp): bool
    {
        return $timestamp !== 0 && $timestamp < (new \DateTimeImmutable('today'))->getTimestamp();
    }

    private function toTimestamp(mixed $value): int
    {
        if (!is_string($value) || $value === '') {
            return 0;
        }
        try {
            return (new \DateTimeImmutable($value))->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }
}
