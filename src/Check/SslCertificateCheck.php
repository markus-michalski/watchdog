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
            'hosts' => [],
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
                'name' => 'hosts',
                'label' => 'URLs to check',
                'type' => 'client_url_multiselect',
                'required' => true,
                'default' => [],
                'placeholder' => '',
                'help' => 'Select the client URLs whose TLS certificates should be checked. The check fails if any certificate is expiring.',
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
        return 'Hosts';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $hosts = $config['hosts'] ?? [];
        if (!is_array($hosts) || [] === $hosts) {
            return null;
        }

        $nonEmpty = array_values(array_filter($hosts, static fn ($h) => is_string($h) && '' !== $h));

        return [] === $nonEmpty ? null : implode(', ', $nonEmpty);
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $checkResult = new CheckResult();
        $checkResult->setCheck($check);

        $rawHosts = $config['hosts'] ?? [];
        $hosts = is_array($rawHosts)
            ? array_values(array_filter(
                array_map(static fn (mixed $h): string => is_string($h) ? trim($h) : '', $rawHosts),
                static fn (string $h): bool => '' !== $h,
            ))
            : [];

        if ([] === $hosts) {
            $checkResult->setStatus(CheckStatus::Unknown);
            $checkResult->setMessage('No hosts configured');

            return $checkResult;
        }

        $port = is_numeric($config['port']) ? (int) $config['port'] : 443;
        $warnDays = is_numeric($config['warn_days']) ? (int) $config['warn_days'] : 14;
        $failDays = is_numeric($config['fail_days']) ? (int) $config['fail_days'] : 3;
        $timeout = is_numeric($config['timeout']) ? (int) $config['timeout'] : 10;
        $allowSelfSigned = (bool) ($config['allow_self_signed'] ?? false);

        $worstStatus = CheckStatus::Ok;
        /** @var list<array{status: CheckStatus, message: string, days?: int}> */
        $hostResults = [];

        foreach ($hosts as $host) {
            $expiryOrError = $this->certExpiryReader->read($host, $port, $timeout, $allowSelfSigned);

            if (is_string($expiryOrError)) {
                $worstStatus = $this->worseStatus($worstStatus, CheckStatus::Fail);
                $hostResults[] = ['status' => CheckStatus::Fail, 'message' => $expiryOrError];

                continue;
            }

            $daysRemaining = (int) ceil(($expiryOrError - time()) / 86400);

            if ($daysRemaining <= 0) {
                $worstStatus = $this->worseStatus($worstStatus, CheckStatus::Fail);
                $hostResults[] = ['status' => CheckStatus::Fail, 'message' => sprintf('%s: expired', $host)];
            } elseif ($daysRemaining <= $failDays) {
                $worstStatus = $this->worseStatus($worstStatus, CheckStatus::Fail);
                $hostResults[] = ['status' => CheckStatus::Fail, 'message' => sprintf('%s: expires in %d day(s)', $host, $daysRemaining), 'days' => $daysRemaining];
            } elseif ($daysRemaining <= $warnDays) {
                $worstStatus = $this->worseStatus($worstStatus, CheckStatus::Warn);
                $hostResults[] = ['status' => CheckStatus::Warn, 'message' => sprintf('%s: expires in %d day(s)', $host, $daysRemaining), 'days' => $daysRemaining];
            } else {
                $hostResults[] = ['status' => CheckStatus::Ok, 'message' => sprintf('%s: valid for %d day(s)', $host, $daysRemaining), 'days' => $daysRemaining];
            }
        }

        $checkResult->setStatus($worstStatus);
        $checkResult->setMessage($this->buildSummaryMessage($hostResults));

        return $checkResult;
    }

    /**
     * Single host → full message. Multiple hosts → compact: problem hosts listed,
     * OK hosts counted. All OK → "N hosts — all OK (min. Xd)".
     *
     * @param list<array{status: CheckStatus, message: string, days?: int}> $hostResults
     */
    private function buildSummaryMessage(array $hostResults): string
    {
        if (1 === count($hostResults)) {
            return $hostResults[0]['message'];
        }

        $problemMessages = [];
        $okDays = [];

        foreach ($hostResults as $r) {
            if (CheckStatus::Ok === $r['status']) {
                if (isset($r['days'])) {
                    $okDays[] = $r['days'];
                }
            } else {
                $problemMessages[] = $r['message'];
            }
        }

        if ([] === $problemMessages) {
            $minDays = $okDays ? min($okDays) : 0;

            return sprintf('%d hosts — all OK (min. %dd)', count($hostResults), $minDays);
        }

        $summary = implode('; ', $problemMessages);
        $okCount = count($okDays);
        if ($okCount > 0) {
            $summary .= sprintf(' (%d OK)', $okCount);
        }

        return $summary;
    }

    private function worseStatus(CheckStatus $current, CheckStatus $new): CheckStatus
    {
        return $new->priority() > $current->priority() ? $new : $current;
    }
}
