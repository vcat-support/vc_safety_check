<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\Service;

use Vcat\Vcsafetycheck\Domain\Model\Typo3VersionStatus;

/**
 * Summary of a completed safety check run.
 */
final class SafetyCheckResult
{
    public function __construct(
        public readonly int $packagesScanned = 0,
        public readonly int $vulnerabilitiesFound = 0,
        public readonly int $removedStale = 0,
        public readonly int $runTime = 0,
        public readonly array $severityCounts = [],
        public readonly ?Typo3VersionStatus $typo3VersionStatus = null,
    ) {}
}
