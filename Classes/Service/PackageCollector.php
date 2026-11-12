<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\Service;

use Composer\InstalledVersions;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Collects every installed package (TYPO3 core packages, third-party
 * extensions and their Composer dependencies) together with the
 * exact installed version, ready to be queried against the API
 *
 * Primary source is the Composer runtime API (Composer\InstalledVersions),
 * which is available in every Composer-based TYPO3 installation and yields the
 * complete dependency tree. In non-Composer installations it falls
 * back to the TYPO3 PackageManager (extensions only).
 */
final class PackageCollector
{
    /**
     * @return array<int, array{name: string, version: string, type: string}>
     *         de-duplicated list of packages with a resolvable version
     */
    public function collect(): array
    {
        $packages = $this->collectFromComposer();
        if ($packages === []) {
            $packages = $this->collectFromPackageManager();
        }

        // De-duplicate by name and drop entries without a comparable version.
        $unique = [];
        foreach ($packages as $package) {
            $version = $this->normalizeVersion($package['version']);
            if ($version === null) {
                continue;
            }
            $unique[$package['name']] = [
                'name' => $package['name'],
                'version' => $version,
                'type' => $package['type'],
            ];
        }

        ksort($unique);
        return array_values($unique);
    }

    /**
     * @return array<int, array{name: string, version: string, type: string}>
     */
    private function collectFromComposer(): array
    {
        if (!class_exists(InstalledVersions::class)) {
            return [];
        }

        $result = [];
        foreach (InstalledVersions::getInstalledPackages() as $name) {
            // Skip meta/replaced packages that have no real installation.
            if (!InstalledVersions::isInstalled($name, false)) {
                continue;
            }
            $prettyVersion = InstalledVersions::getPrettyVersion($name);
            if ($prettyVersion === null) {
                continue;
            }
            $result[] = [
                'name' => $name,
                'version' => (string)$prettyVersion,
                'type' => $this->guessType($name),
            ];
        }
        return $result;
    }

    /**
     * @return array<int, array{name: string, version: string, type: string}>
     */
    private function collectFromPackageManager(): array
    {
        $result = [];
        try {
            $packageManager = GeneralUtility::makeInstance(PackageManager::class);
        } catch (\Throwable) {
            return [];
        }

        foreach ($packageManager->getActivePackages() as $package) {
            $meta = $package->getPackageMetaData();
            $version = method_exists($meta, 'getVersion') ? (string)$meta->getVersion() : '';
            $composerName = $package->getValueFromComposerManifest('name');
            $result[] = [
                'name' => is_string($composerName) && $composerName !== ''
                    ? $composerName
                    : (string)$package->getPackageKey(),
                'version' => $version,
                'type' => 'typo3-cms-extension',
            ];
        }
        return $result;
    }

    private function guessType(string $name): string
    {
        if (str_starts_with($name, 'typo3/cms-')) {
            return 'typo3-core';
        }
        if (str_starts_with($name, 'typo3/')) {
            return 'typo3';
        }
        return 'dependency';
    }

    /**
     * Reduce a Composer version to a plain semver string the API can match.
     * Returns null for dev branches / unresolvable references the API cannot use.
     */
    private function normalizeVersion(string $version): ?string
    {
        $version = ltrim(trim($version), 'vV');
        if ($version === '') {
            return null;
        }
        // dev-main, dev-master, 9999999-dev, ...-dev, dev branches: not queryable
        if (str_contains($version, 'dev') || str_starts_with($version, '9999999')) {
            return null;
        }
        // Strip build metadata but keep pre-release identifiers.
        $version = preg_replace('/\+.*$/', '', $version) ?? $version;
        // Must start with a digit to be a usable package version.
        if (!preg_match('/^\d/', $version)) {
            return null;
        }
        return $version;
    }
}
