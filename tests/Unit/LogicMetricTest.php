<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Test\Unit;

use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\LogicMetric;
use Msstc4Symfony\MetricsBundle\Application\Metric\Label;
use Msstc4Symfony\MetricsBundle\Application\Metric\MetricType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogicMetric::class)]
final class LogicMetricTest extends TestCase
{
    public function testDefinesTheActionDurationHistogram(): void
    {
        $metric = LogicMetric::ACTION_DURATION;

        self::assertSame('logic_action_duration_histogram_seconds', $metric->value);
        self::assertSame(MetricType::HISTOGRAM, $metric->getType());
        self::assertSame('Application action duration in seconds by outcome', $metric->getDescription());
        self::assertSame(['action', 'outcome'], array_map(static fn (Label $label): string => $label->name, $metric->getLabels()));
        self::assertSame('success, expected_failure, error', $metric->getLabels()[1]->description);
        self::assertSame([0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0], $metric->getBatches());
    }
}
