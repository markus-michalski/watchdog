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

    public function run(SiteCheck $check): CheckResult
    {
        $result = new CheckResult();
        $result->setCheck($check);

        $containerName = $check->getConfig()['container_name'] ?? '';
        if ($containerName === '') {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No container_name configured');

            return $result;
        }

        if (!file_exists(self::SOCKET_PATH)) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('Docker socket not available at ' . self::SOCKET_PATH);

            return $result;
        }

        try {
            $data = $this->queryDockerApi('/containers/' . urlencode($containerName) . '/json');
        } catch (\Throwable $e) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage('Docker API error: ' . $e->getMessage());

            return $result;
        }

        $running = $data['State']['Running'] ?? false;
        $healthStatus = $data['State']['Health']['Status'] ?? null;

        if (!$running) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('Container "%s" is not running', $containerName));

            return $result;
        }

        if ($healthStatus !== null) {
            if ($healthStatus === 'healthy') {
                $result->setStatus(CheckStatus::Ok);
                $result->setMessage(sprintf('Container "%s" is healthy', $containerName));
            } elseif ($healthStatus === 'starting') {
                $result->setStatus(CheckStatus::Unknown);
                $result->setMessage(sprintf('Container "%s" health check is starting', $containerName));
            } else {
                $result->setStatus(CheckStatus::Fail);
                $result->setMessage(sprintf('Container "%s" health: %s', $containerName, $healthStatus));
            }
        } else {
            // Running but no healthcheck defined — treat as OK
            $result->setStatus(CheckStatus::Ok);
            $result->setMessage(sprintf('Container "%s" is running (no healthcheck defined)', $containerName));
        }

        return $result;
    }

    private function queryDockerApi(string $path): array
    {
        $socket = stream_socket_client('unix://' . self::SOCKET_PATH, $errno, $errstr, 5);
        if ($socket === false) {
            throw new \RuntimeException(sprintf('Cannot connect to Docker socket: %s (%d)', $errstr, $errno));
        }

        stream_set_timeout($socket, 5);

        $request = "GET " . $path . " HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n";
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

        if ($statusCode === 404) {
            throw new \RuntimeException(sprintf('Container not found (404)'));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Docker API returned %d', $statusCode));
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Docker API');
        }

        return $data;
    }
}
