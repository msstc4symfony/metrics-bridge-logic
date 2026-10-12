<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics;

use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\MetricsBundle\Infrastructure\Collector\AbstractCollector;
use Throwable;

final class ActionDurationCollector extends AbstractCollector
{
    public function observe(string $action, ActionOutcome $outcome, float $seconds): void
    {
        try {
            $this->observeHistogram(LogicMetric::ACTION_DURATION, $seconds, [$action, $outcome->value]);
        } catch (Throwable $exception) {
            // metrics-bundle guards only the storage call; a failing metric lookup must not fail the Action.
            $this->reportFailure($action, $exception);
        }
    }

    /**
     * @internal for MetricsMiddleware: $action may be the Action class when its name could not be resolved
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
            // Logger failures must not break the Action either, as in AbstractCollector::processException().
        }
    }
}
