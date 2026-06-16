<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class DockerCheck implements CheckInterface
{
    private const SOCKET_PATH = '/var/run/docker.sock';

    public function supportsAgentRunner(): bool { return true; }

    public function getType(): string
    {
        return 'docker';
    }

    public function getLabel(): string
    {
        return 'Docker Container Health';
    }

    public function getDefaultConfig(): array
    {
        return [
            'container_name' => '',
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'container_name',
                'label' => 'Container name',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => 'my-app',
                'help' => 'Exact name of the Docker container (docker ps --format "{{.Names}}").',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'Container';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $name = $config['container_name'] ?? '';

        return is_string($name) && '' !== $name ? $name : null;
    }

    public function run(SiteCheck $check): CheckResult
    {
        $result = new CheckResult();
        $result->setCheck($check);

        $rawName = $check->getConfig()['container_name'] ?? '';
        $containerName = is_string($rawName) ? $rawName : '';
        if ('' === $containerName) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No container_name configured');

            return $result;
        }

        if (!file_exists(self::SOCKET_PATH)) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('Docker socket not available at '.self::SOCKET_PATH);

            return $result;
        }

        try {
            $data = $this->queryDockerApi('/containers/'.urlencode($containerName).'/json');
        } catch (\Throwable $e) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage('Docker API error: '.$e->getMessage());

            return $result;
        }

        $state = $data['State'];
        if (!is_array($state)) {
            $state = [];
        }
        $running = (bool) ($state['Running'] ?? false);
        $healthData = $state['Health'] ?? null;
        if (is_array($healthData)) {
            $rawStatus = $healthData['Status'] ?? null;
            $healthStatus = is_string($rawStatus) ? $rawStatus : null;
        } else {
            $healthStatus = null;
        }

        if (!$running) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('Container "%s" is not running', $containerName));

            return $result;
        }

        if (null !== $healthStatus) {
            if ('healthy' === $healthStatus) {
                $result->setStatus(CheckStatus::Ok);
                $result->setMessage('healthy');
            } elseif ('starting' === $healthStatus) {
                $result->setStatus(CheckStatus::Unknown);
                $result->setMessage('starting');
            } elseif ('unhealthy' === $healthStatus) {
                $result->setStatus(CheckStatus::Warn);
                $result->setMessage('unhealthy');
            } else {
                $result->setStatus(CheckStatus::Fail);
                $result->setMessage((string) $healthStatus);
            }
        } else {
            // Running but no healthcheck defined — treat as OK
            $result->setStatus(CheckStatus::Ok);
            $result->setMessage('running');
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function queryDockerApi(string $path): array
    {
        $socket = stream_socket_client('unix://'.self::SOCKET_PATH, $errno, $errstr, 5);
        if (false === $socket) {
            throw new \RuntimeException(sprintf('Cannot connect to Docker socket: %s (%d)', $errstr, $errno));
        }

        stream_set_timeout($socket, 5);

        $request = 'GET '.$path." HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n";
        fwrite($socket, $request);

        $response = '';
        while (!feof($socket)) {
            $response .= fread($socket, 8192);
        }
        fclose($socket);

        [$headers, $body] = explode("\r\n\r\n", $response, 2);

        // Extract HTTP status from first header line
        preg_match('/HTTP\/\d\.\d (\d+)/', $headers, $matches);
        $statusCode = (int) ($matches[1] ?? 0);

        if (404 === $statusCode) {
            throw new \RuntimeException(sprintf('Container not found (404)'));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Docker API returned %d', $statusCode));
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Docker API');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
