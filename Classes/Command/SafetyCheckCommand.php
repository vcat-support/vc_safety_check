<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vcat\Vcsafetycheck\Domain\Model\Typo3VersionStatus;
use Vcat\Vcsafetycheck\Service\SafetyCheckService;

/**
 * CLI entry point for the safety check.
 *
 * Run manually:      vendor/bin/typo3 vc_safety_check:check
 * Run as scheduler:  create a scheduler task of type "Execute console command"
 *                    (v12/13) or a scheduled task selecting this command
 */
final class SafetyCheckCommand extends Command
{
    public function __construct(
        private readonly SafetyCheckService $safetyCheckService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            'Collects all installed packages (TYPO3 core, extensions and their '
            . 'Composer dependencies) and checks them against the typo3-status.com/typo3-api API.'
            . 'Additionally compares the installed TYPO3 version with the release cycle data.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('vc_safety_check – security scan');

        try {
            $result = $this->safetyCheckService->run(time());
        } catch (\Throwable $e) {
            $io->error('Safety check failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Scanned %d packages – %d vulnerability finding(s), %d stale entries removed.',
            $result->packagesScanned,
            $result->vulnerabilitiesFound,
            $result->removedStale
        ));

        if ($result->severityCounts !== []) {
            $rows = [];
            foreach ($result->severityCounts as $severity => $count) {
                $rows[] = [$severity, $count];
            }
            $io->table(['Severity', 'Count'], $rows);
        }

        $this->outputVersionStatus($io, $result->typo3VersionStatus);

        return Command::SUCCESS;
    }

    private function outputVersionStatus(SymfonyStyle $io, ?Typo3VersionStatus $status): void
    {
        $io->section('TYPO3 version');

        if ($status === null || $status->state === Typo3VersionStatus::STATE_UNKNOWN) {
            $io->warning(
                'Version comparison unavailable'
                . ($status !== null && $status->error !== '' ? ': ' . $status->error : '.')
            );
            return;
        }

        $rows = [
            ['Installed', $status->currentVersion],
            ['Release cycle', $status->cycle],
            ['Latest release in cycle', $status->latestCycleVersion !== '' ? $status->latestCycleVersion : 'unknown'],
            ['Latest release overall', $status->latestVersion !== '' ? $status->latestVersion : 'unknown'],
            ['Active support until', $this->formatDate($status->activeSupportEnd)],
            ['Security support until', $this->formatDate($status->securitySupportEnd)],
            ['ELTS until', $this->formatDate($status->eltsEnd)],
        ];
        $io->table([], $rows);

        match ($status->state) {
            Typo3VersionStatus::STATE_EOES => $io->error(sprintf(
                'TYPO3 %s is out of support entirely – even ELTS ended on %s. Migrate to a supported branch.',
                $status->cycle,
                $this->formatDate($status->eltsEnd)
            )),
            Typo3VersionStatus::STATE_EOL => $io->error(sprintf(
                'Security support for TYPO3 %s ended on %s. Upgrade the branch or book ELTS.',
                $status->cycle,
                $this->formatDate($status->securitySupportEnd)
            )),
            Typo3VersionStatus::STATE_UPDATE => $io->warning(sprintf(
                'Update available: %s is installed, %s is the latest release of branch %s.',
                $status->currentVersion,
                $status->latestCycleVersion,
                $status->cycle
            )),
            default => $io->success(sprintf(
                'TYPO3 %s is the latest release of branch %s and within security support.',
                $status->currentVersion,
                $status->cycle
            )),
        };

        if ($status->majorUpdateAvailable) {
            $io->note(sprintf(
                'A newer TYPO3 release exists outside branch %s: %s.',
                $status->cycle,
                $status->latestVersion
            ));
        }

        if ($status->activeSupportEnded && $status->state !== Typo3VersionStatus::STATE_EOES) {
            $io->note(sprintf(
                'Active support for branch %s ended on %s – only security fixes are released.',
                $status->cycle,
                $this->formatDate($status->activeSupportEnd)
            ));
        }
    }

    private function formatDate(int $timestamp): string
    {
        return $timestamp > 0 ? date('Y-m-d', $timestamp) : 'unknown';
    }
}
