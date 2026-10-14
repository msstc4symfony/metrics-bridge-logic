<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Test\Unit;

use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\ActionDurationCollector;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\LogicMetric;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\InMemoryRegistry;
use Msstc4Symfony\MetricsBundle\Infrastructure\Repository\MetricRepository;
use Msstc4Symfony\MetricsBundle\Infrastructure\Repository\MetricRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(ActionDurationCollector::class)]
#[UsesClass(LogicMetric::class)]
final class ActionDurationCollectorTest extends TestCase
{
    private const METRIC = 'symfony_logic_action_duration_histogram_seconds';

    public function testObservesWithActionAndOutcomeLabels(): void
    {
        $registry = InMemoryRegistry::create();

        (new ActionDurationCollector($registry, new MetricRepository([]), 'app', 'cmp'))->observe('order.create', ActionOutcome::ExpectedFailure, 0.25);

        self::assertSame([[['app', 'cmp', 'order.create', 'expected_failure'], 0.25]], InMemoryRegistry::sums($registry, self::METRIC));
    }

    public function testAFailingMetricLookupIsSwallowed(): void
    {
        $repository = self::createStub(MetricRepositoryInterface::class);
        $repository->method('find')->willThrowException(new RuntimeException('no such metric'));

        (new ActionDurationCollector(InMemoryRegistry::create(), $repository, 'app', 'cmp'))->observe('x', ActionOutcome::Success, 0.1);

        $this->addToAssertionCount(1);
    }

    public function testAFailingMetricLookupIsLoggedWithTheActionName(): void
    {
        $failure = new RuntimeException('no such metric');
        $repository = self::createStub(MetricRepositoryInterface::class);
        $repository->method('find')->willThrowException($failure);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Could not record the action metric', ['metric' => 'logic_action_duration_histogram_seconds', 'action' => 'order.create', 'exception' => $failure]);
        $collector = new ActionDurationCollector(InMemoryRegistry::create(), $repository, 'app', 'cmp');
        $collector->setLogger($logger);

        $collector->observe('order.create', ActionOutcome::Error, 0.1);
    }

    public function testReportsAFailureFromOutsideTheCollector(): void
    {
        $failure = new RuntimeException('name builder down');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Could not record the action metric', ['metric' => 'logic_action_duration_histogram_seconds', 'action' => 'App\\Action', 'exception' => $failure]);
        $collector = new ActionDurationCollector(InMemoryRegistry::create(), new MetricRepository([]), 'app', 'cmp');
        $collector->setLogger($logger);

        $collector->reportFailure('App\\Action', $failure);
    }

    public function testAFailingLoggerIsSwallowedToo(): void
    {
        $repository = self::createStub(MetricRepositoryInterface::class);
        $repository->method('find')->willThrowException(new RuntimeException('no such metric'));
        $logger = self::createStub(LoggerInterface::class);
        $logger->method('error')->willThrowException(new RuntimeException('log handler down'));
        $collector = new ActionDurationCollector(InMemoryRegistry::create(), $repository, 'app', 'cmp');
        $collector->setLogger($logger);

        $collector->observe('order.create', ActionOutcome::Success, 0.1);

        $this->addToAssertionCount(1);
    }
}
