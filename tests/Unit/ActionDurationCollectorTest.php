<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Test\Unit;

use InvalidArgumentException;
use Monolog\Attribute\WithMonologChannel;
use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\ActionDurationCollector;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\LogicMetric;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\RecordingMetricRecorder;
use Msstc4Symfony\MetricsBundle\Application\LogChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

#[CoversClass(ActionDurationCollector::class)]
#[UsesClass(LogicMetric::class)]
final class ActionDurationCollectorTest extends TestCase
{
    public function testObservesTheDurationWithActionAndOutcomeLabels(): void
    {
        $recorder = new RecordingMetricRecorder();

        (new ActionDurationCollector($recorder))->observe('order.create', ActionOutcome::ExpectedFailure, 0.25);

        self::assertSame(
            [['method' => 'observe', 'metric' => LogicMetric::ACTION_DURATION, 'value' => 0.25, 'labels' => ['order.create', 'expected_failure']]],
            $recorder->calls,
        );
    }

    public function testARecorderFailureIsLoggedAndSwallowed(): void
    {
        $failure = new InvalidArgumentException('observe() needs a histogram or summary');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Could not record the action metric', [
            'metric' => 'logic_action_duration_histogram_seconds',
            'action' => 'order.create',
            'exception' => $failure,
        ]);

        (new ActionDurationCollector(new RecordingMetricRecorder($failure), $logger))->observe('order.create', ActionOutcome::Error, 0.1);
    }

    #[DoesNotPerformAssertions]
    public function testARecorderFailureWithoutALoggerIsSwallowed(): void
    {
        (new ActionDurationCollector(new RecordingMetricRecorder(new RuntimeException('no such metric'))))->observe('x', ActionOutcome::Success, 0.1);
    }

    public function testReportsAFailureFromOutsideTheCollector(): void
    {
        $failure = new RuntimeException('name builder down');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Could not record the action metric', [
            'metric' => 'logic_action_duration_histogram_seconds',
            'action' => 'App\\Action',
            'exception' => $failure,
        ]);

        (new ActionDurationCollector(new RecordingMetricRecorder(), $logger))->reportFailure('App\\Action', $failure);
    }

    #[DoesNotPerformAssertions]
    public function testAFailingLoggerIsSwallowedToo(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $logger->method('error')->willThrowException(new RuntimeException('log handler down'));

        (new ActionDurationCollector(new RecordingMetricRecorder(new RuntimeException('no such metric')), $logger))->observe('order.create', ActionOutcome::Success, 0.1);
    }

    public function testLogsToTheMetricsChannelSoTheErrorCounterDoesNotCountItsOwnFailures(): void
    {
        $attribute = (new ReflectionClass(ActionDurationCollector::class))->getAttributes(WithMonologChannel::class)[0] ?? null;

        self::assertNotNull($attribute);
        self::assertSame(LogChannel::NAME, $attribute->newInstance()->channel);
    }
}
