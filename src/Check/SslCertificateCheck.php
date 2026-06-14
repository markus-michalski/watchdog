<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class SslCertificateCheck implements CheckInterface
{
    public function __construct(private readonly SslCertExpiryReaderInterface $certExpiryReader)
    {
    }

    public function getType(): string
    {
        return 'ssl_cert';
    }

    public function getLabel(): string
    {
        return 'SSL Certificate';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'host' => '',
            'port' => 443,
            'warn_days' => 14,
            'fail_days' => 3,
            'timeout' => 10,
            'allow_self_signed' => false,
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'host',
                'label' => 'Hostname',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => 'example.com',
                'help' => 'Hostname to connect to (without https://). The TLS certificate of this host will be checked.',
            ],
            [
                'name' => 'port',
                'label' => 'Port',
                'type' => 'number',
                'required' => false,
                'default' => 443,
                'placeholder' => '443',
                'help' => 'TCP port to use for the TLS connection. Default: 443.',
            ],
            [
                'name' => 'warn_days',
                'label' => 'Warn threshold (days)',
                'type' => 'number',
                'required' => false,
                'default' => 14,
                'placeholder' => '14',
                'help' => 'Warn if the certificate expires within this many days (inclusive).',
            ],
            [
                'name' => 'fail_days',
                'label' => 'Fail threshold (days)',
                'type' => 'number',
                'required' => false,
                'default' => 3,
                'placeholder' => '3',
                'help' => 'Fail if the certificate expires within this many days (inclusive, must be less than warn threshold).',
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
            [
                'name' => 'allow_self_signed',
                'label' => 'Allow self-signed',
                'type' => 'checkbox',
                'required' => false,
                'default' => false,
                'placeholder' => '',
                'help' => 'Accept self-signed and internal-CA certificates. Enable for internal infrastructure.',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'Host';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $host = $config['host'] ?? '';

        return is_string($host) && '' !== $host ? $host : null;
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $checkResult = new CheckResult();
        $checkResult->setCheck($check);

        $host = is_string($config['host']) ? trim($config['host']) : '';
        if ('' === $host) {
            $checkResult->setStatus(CheckStatus::Unknown);
            $checkResult->setMessage('No host configured');

            return $checkResult;
        }

        $port = is_numeric($config['port']) ? (int) $config['port'] : 443;
        $warnDays = is_numeric($config['warn_days']) ? (int) $config['warn_days'] : 14;
        $failDays = is_numeric($config['fail_days']) ? (int) $config['fail_days'] : 3;
        $timeout = is_numeric($config['timeout']) ? (int) $config['timeout'] : 10;
        $allowSelfSigned = (bool) ($config['allow_self_signed'] ?? false);

        $expiryOrError = $this->certExpiryReader->read($host, $port, $timeout, $allowSelfSigned);

        if (is_string($expiryOrError)) {
            $checkResult->setStatus(CheckStatus::Fail);
            $checkResult->setMessage($expiryOrError);

            return $checkResult;
        }

        $daysRemaining = (int) ceil(($expiryOrError - time()) / 86400);

        if ($daysRemaining <= 0) {
            $checkResult->setStatus(CheckStatus::Fail);
            $checkResult->setMessage(sprintf('Certificate for %s has expired', $host));

            return $checkResult;
        }

        if ($daysRemaining <= $failDays) {
            $checkResult->setStatus(CheckStatus::Fail);
            $checkResult->setMessage(sprintf('Certificate for %s expires in %d day(s)', $host, $daysRemaining));

            return $checkResult;
        }

        if ($daysRemaining <= $warnDays) {
            $checkResult->setStatus(CheckStatus::Warn);
            $checkResult->setMessage(sprintf('Certificate for %s expires in %d day(s)', $host, $daysRemaining));

            return $checkResult;
        }

        $checkResult->setStatus(CheckStatus::Ok);
        $checkResult->setMessage(sprintf('Certificate for %s valid for %d day(s)', $host, $daysRemaining));

        return $checkResult;
    }
}
