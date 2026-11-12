<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\Domain\Model;

/**
 * Result of comparing the installed TYPO3 version against the API-data
 **/
final class Typo3VersionStatus
{
    /** The Version API could not be reached and no cached payload was available. */
    public const STATE_UNKNOWN = 'unknown';

    /** Newest release of the installed branch, security support still running. */
    public const STATE_OK = 'ok';

    /** A newer release exists in the installed branch. */
    public const STATE_UPDATE = 'update';

    /** Regular security support of the branch has ended (ELTS may still apply). */
    public const STATE_EOL = 'eol';

    /** Even extended long term support (ELTS) has ended. */
    public const STATE_EOES = 'eoes';

    public function __construct(
        public readonly string $currentVersion = '',
        public readonly string $branch = '',
        public readonly string $cycle = '',
        /** Newest TYPO3 release overall, across all release cycles. */
        public readonly string $latestVersion = '',
        /** Newest release within the installed release cycle. */
        public readonly string $latestCycleVersion = '',
        public readonly string $state = self::STATE_UNKNOWN,
        /** A newer release exists within the installed cycle. */
        public readonly bool $updateAvailable = false,
        /** A newer cycle (major/LTS) than the installed one has been released. */
        public readonly bool $majorUpdateAvailable = false,
        public readonly int $activeSupportEnd = 0,
        public readonly bool $activeSupportEnded = false,
        public readonly int $securitySupportEnd = 0,
        public readonly bool $securitySupportEnded = false,
        public readonly int $eltsEnd = 0,
        public readonly bool $eltsEnded = false,
        public readonly int $checkedAt = 0,
        public readonly string $error = '',
    ) {}

    public function getSeverityClass(): string
    {
        return match ($this->state) {
            self::STATE_EOES => 'vcsafetycheck-sev-critical',
            self::STATE_EOL => 'vcsafetycheck-sev-high',
            self::STATE_UPDATE => 'vcsafetycheck-sev-moderate',
            self::STATE_OK => 'vcsafetycheck-sev-none',
            default => 'vcsafetycheck-sev-unknown',
        };
    }

    /**
     * True for everything that could need an update or migration.
     */
    public function isActionRequired(): bool
    {
        return in_array($this->state, [self::STATE_UPDATE, self::STATE_EOL, self::STATE_EOES], true);
    }
}
