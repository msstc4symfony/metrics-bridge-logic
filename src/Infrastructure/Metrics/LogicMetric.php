<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics;

use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\MetricsBundle\Infrastructure\Entity\Label;
use Msstc4Symfony\MetricsBundle\Infrastructure\Enum\MetricLabelEnumInterface;
use Msstc4Symfony\MetricsBundle\Infrastructure\Enum\MetricLabelTypeEnum;
use Msstc4Symfony\MetricsBundle\Infrastructure\Enum\MetricTypeEnum;
use Override;

enum LogicMetric: string implements MetricLabelEnumInterface
{
    case ACTION_DURATION = 'logic_action_duration_histogram_seconds';

    #[Override]
    public function getType(): MetricTypeEnum
    {
        return MetricTypeEnum::HISTOGRAM;
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
            new Label('action', MetricLabelTypeEnum::STRING, 'Action name, as in the logs'),
            new Label('outcome', MetricLabelTypeEnum::STRING, self::outcomeValues()),
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
