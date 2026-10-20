<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics;

use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\MetricsBundle\Application\Metric\Label;
use Msstc4Symfony\MetricsBundle\Application\Metric\LabelType;
use Msstc4Symfony\MetricsBundle\Application\Metric\MetricDefinitionInterface;
use Msstc4Symfony\MetricsBundle\Application\Metric\MetricType;
use Override;

enum LogicMetric: string implements MetricDefinitionInterface
{
    case ACTION_DURATION = 'logic_action_duration_histogram_seconds';

    #[Override]
    public function getType(): MetricType
    {
        return MetricType::HISTOGRAM;
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Application action duration in seconds by outcome';
    }

    #[Override]
    public function getLabels(): array
    {
        return [
            new Label('action', LabelType::STRING, 'Action name, as in the logs'),
            new Label('outcome', LabelType::STRING, self::outcomeValues()),
        ];
    }

    #[Override]
    public function getBatches(): array
    {
        return [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0];
    }

    private static function outcomeValues(): string
    {
        return implode(', ', array_map(static fn (ActionOutcome $outcome): string => $outcome->value, ActionOutcome::cases()));
    }
}
