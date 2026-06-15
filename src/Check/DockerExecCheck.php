<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class DockerExecCheck implements CheckInterface
{
    private const SOCKET_PATH = '/var/run/docker.sock';

    public function supportsAgentRunner(): bool { return true; }

    public function getType(): string
    {
        return 'docker_exec';
    }

    public function getLabel(): string
    {
        return 'Docker Exec';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'container_name' => '',
            'command' => '',
            'timeout' => 10,
        ];
    }

    /**
     * @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}>
     */
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
            [
                'name' => 'command',
                'label' => 'Command',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => 'php bin/console app:health',
                'help' => 'Command to run inside the container. Exit code 0 = OK, anything else = Fail.',
            ],
            [
                'name' => 'timeout',
                'label' => 'Timeout (seconds)',
                'type' => 'number',
                'required' => false,
                'default' => 10,
                'placeholder' => '10',
                'help' => '',
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

        $config = array_merge($this->getDefaultConfig(), $check->getConfig());

        $containerName = is_string($config['container_name']) ? $config['container_name'] : '';
        if ('' === $containerName) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No container_name configured');

            return $result;
        }

        $command = is_string($config['command']) ? $config['command'] : '';
        if ('' === $command) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No command configured');

            return $result;
        }

        if (!file_exists(self::SOCKET_PATH)) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('Docker socket not available at '.self::SOCKET_PATH);

            return $result;
        }

        $timeout = is_int($config['timeout']) ? max(1, $config['timeout']) : 10;

        try {
            $execId = $this->createExec($containerName, $command, $timeout);
            $this->startExec($execId, $timeout);
            $exitCode = $this->inspectExec($execId, $timeout);
        } catch (\Throwable $e) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage('Docker API error: '.$e->getMessage());

            return $result;
        }

        if (0 === $exitCode) {
            $result->setStatus(CheckStatus::Ok);
            $result->setMessage('Exit code 0');
        } else {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('Exit code %d', $exitCode));
        }

        return $result;
    }

    private function createExec(string $containerName, string $command, int $timeout): string
    {
        $body = json_encode([
            'AttachStdout' => false,
            'AttachStderr' => false,
            'Cmd' => ['sh', '-c', $command],
        ]);

        $data = $this->postDockerApi(
            '/containers/'.urlencode($containerName).'/exec',
            $body,
            $timeout,
        );

        $id = $data['Id'] ?? null;
        if (!is_string($id) || '' === $id) {
            throw new \RuntimeException('Docker exec create returned no ID');
        }

        return $id;
    }

    private function startExec(string $execId, int $timeout): void
    {
        $body = json_encode(['Detach' => false, 'Tty' => false]);

        // Blocks until the command finishes; we only need the exit code from inspect.
        $this->postDockerApiRaw('/exec/'.urlencode($execId).'/start', $body, $timeout);
    }

    private function inspectExec(string $execId, int $timeout): int
    {
        // Docker may briefly report Running=true / ExitCode=null right after the stream closes.
        // Poll until Running=false or deadline is reached.
        $deadline = microtime(true) + $timeout;

        do {
            $data = $this->getDockerApi('/exec/'.urlencode($execId).'/json', $timeout);
            $running = (bool) ($data['Running'] ?? false);

            if (!$running) {
                $exitCode = $data['ExitCode'] ?? null;

                return is_int($exitCode) ? $exitCode : 1;
            }

            if (microtime(true) >= $deadline) {
                throw new \RuntimeException(sprintf('Exec still running after %ds; aborting inspect', $timeout));
            }

            usleep(100_000); // 100ms poll interval
        } while (true);
    }

    /** @return array<string, mixed> */
    private function postDockerApi(string $path, string|false $body, int $timeout): array
    {
        if (false === $body) {
            throw new \RuntimeException('Failed to encode JSON body');
        }

        [$statusCode, $responseBody] = $this->sendDockerRequest('POST', $path, $body, $timeout);

        if (404 === $statusCode) {
            throw new \RuntimeException(sprintf('Container not found (404) at %s', $path));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Docker API returned %d for POST %s', $statusCode, $path));
        }

        $data = json_decode($responseBody, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Docker API');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function postDockerApiRaw(string $path, string|false $body, int $timeout): void
    {
        if (false === $body) {
            throw new \RuntimeException('Failed to encode JSON body');
        }

        [$statusCode] = $this->sendDockerRequest('POST', $path, $body, $timeout);

        if (404 === $statusCode) {
            throw new \RuntimeException(sprintf('Exec not found (404) at %s', $path));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Docker API returned %d for POST %s', $statusCode, $path));
        }
    }

    /** @return array<string, mixed> */
    private function getDockerApi(string $path, int $timeout): array
    {
        [$statusCode, $body] = $this->sendDockerRequest('GET', $path, null, $timeout);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Docker API returned %d for GET %s', $statusCode, $path));
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Docker API');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function sendDockerRequest(string $method, string $path, ?string $body, int $timeout): array
    {
        $socket = stream_socket_client('unix://'.self::SOCKET_PATH, $errno, $errstr, $timeout);
        if (false === $socket) {
            throw new \RuntimeException(sprintf('Cannot connect to Docker socket: %s (%d)', $errstr, $errno));
        }

        stream_set_timeout($socket, $timeout);

        if (null !== $body) {
            $request = $method.' '.$path." HTTP/1.0\r\n"
                ."Host: localhost\r\n"
                ."Content-Type: application/json\r\n"
                .'Content-Length: '.strlen($body)."\r\n"
                ."Connection: close\r\n\r\n"
                .$body;
        } else {
            $request = $method.' '.$path." HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n";
        }

        fwrite($socket, $request);

        $response = '';
        $deadline = microtime(true) + $timeout;
        while (!feof($socket)) {
            $chunk = fread($socket, 8192);
            if (false !== $chunk) {
                $response .= $chunk;
            }
            if (microtime(true) > $deadline || stream_get_meta_data($socket)['timed_out']) {
                fclose($socket);
                throw new \RuntimeException(sprintf('Docker socket read timed out after %ds', $timeout));
            }
        }
        fclose($socket);

        if (!str_contains($response, "\r\n\r\n")) {
            throw new \RuntimeException('Invalid HTTP response from Docker API');
        }

        [$headers, $responseBody] = explode("\r\n\r\n", $response, 2);

        preg_match('/HTTP\/\d\.\d (\d+)/', $headers, $matches);
        $statusCode = (int) ($matches[1] ?? 0);

        return [$statusCode, $responseBody];
    }
}
