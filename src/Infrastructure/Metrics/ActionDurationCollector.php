<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics;

use Monolog\Attribute\WithMonologChannel;
use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\MetricsBundle\Application\LogChannel;
use Msstc4Symfony\MetricsBundle\Application\Port\MetricRecorderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Logs to metrics-bundle's channel: the application's error counter does not count it, so a failing metric
 * never feeds back into the metrics.
 *
 * @internal
 */
#[WithMonologChannel(LogChannel::NAME)]
final readonly class ActionDurationCollector
{
    public function __construct(
        private MetricRecorderInterface $recorder,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function observe(string $action, ActionOutcome $outcome, float $seconds): void
    {
        try {
            $this->recorder->observe(LogicMetric::ACTION_DURATION, $seconds, [$action, $outcome->value]);
        } catch (Throwable $exception) {
            // The recorder logs storage failures itself; a definition it cannot use must not fail the Action either.
            $this->reportFailure($action, $exception);
        }
    }

    /**
     * For MetricsMiddleware: $action may be the Action class when its name could not be resolved.
     */
    public function reportFailure(string $action, Throwable $exception): void
    {
        try {
            $this->logger?->error('Could not record the action metric', [
                'metric' => LogicMetric::ACTION_DURATION->value,
                'action' => $action,
                'exception' => $exception,
            ]);
        } catch (Throwable) {
            // Logger failures must not break the Action either.
        }
    }
}
