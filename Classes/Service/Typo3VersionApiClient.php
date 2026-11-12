<?php

declare(strict_types=1);

namespace Vcat\Vcsafetycheck\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Client for the Version API.
 *
 * Every endpoint answers with one release cycle (major version):
 * its latest release plus the end dates for active support, security
 * support and ELTS.
 **/
final class Typo3VersionApiClient
{
    private const API_URL = 'https://safetycheck-api.vcat.de/free-1.0/typo3/versions/';

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param int $majorVersion major version - for example 14
     * @return array<string, mixed>
     * @throws \RuntimeException when the API is unreachable or returns unusable data
     */
    public function fetchTypo3Release(int $majorVersion): array
    {
        return $this->callApi('get/' . $majorVersion);
    }

    /**
     * @return array<string, mixed> the newest release cycle
     * @throws \RuntimeException when the API is unreachable or returns unusable data
     */
    public function fetchNewestTypo3Release(): array
    {
        return $this->callApi('latest/');
    }

    /**
     * @return array<string, mixed>
     * @throws \RuntimeException when the API is unreachable or returns unusable data
     */
    private function callApi(string $endpoint): array
    {
        try {
            $response = $this->requestFactory->request(self::API_URL . $endpoint, 'GET', [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
                'connect_timeout' => 5,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Version API request "{endpoint}" failed: {msg}', [
                'endpoint' => $endpoint,
                'msg' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Version API request "{endpoint}" failed: ' . $e->getMessage(), 1758150000, $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('Version API "%s" returned HTTP %d', $endpoint, $response->getStatusCode()),
                1758150001
            );
        }

        $data = json_decode((string)$response->getBody(), true);
        $release = $data['result'] ?? null;
        if (!is_array($release) || $release === [] || !isset($release['major'], $release['latest'])) {
            throw new \RuntimeException(sprintf('Version API "%s" returned an invalid response', $endpoint), 1758150002);
        }

        return $release;
    }
}
