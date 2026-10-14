<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Test\Unit;

use Msstc4Symfony\LogicBundle\Application\Action\ActionNameResolver;
use Msstc4Symfony\LogicBundle\Application\Action\ExpectedFailureInterface;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\ActionNameBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute\AsActionMiddleware;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\ActionDurationCollector;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\LogicMetric;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\MetricsMiddleware;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\EchoAction;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\EchoInput;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\EchoOutput;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\InMemoryRegistry;
use Msstc4Symfony\MetricsBundle\Infrastructure\Repository\MetricRepository;
use Msstc4Symfony\MetricsBundle\Infrastructure\Repository\MetricRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

#[CoversClass(MetricsMiddleware::class)]
#[UsesClass(ActionDurationCollector::class)]
#[UsesClass(LogicMetric::class)]
final class MetricsMiddlewareTest extends TestCase
{
    private const METRIC = 'symfony_logic_action_duration_histogram_seconds';

    private CollectorRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = InMemoryRegistry::create();
    }

    public function testRecordsASuccessAndReturnsTheSameOutput(): void
    {
        $output = new EchoOutput('ok');

        $result = $this->middleware()->handle(new EchoInput(), ActionContext::of(EchoAction::class), static function () use ($output): OutputInterface {
            usleep(1000);

            return $output;
        });

        self::assertSame($output, $result);
        self::assertSame([[['app', 'cmp', EchoAction::class, 'success'], 1.0]], InMemoryRegistry::counts($this->registry, self::METRIC));
        $sums = InMemoryRegistry::sums($this->registry, self::METRIC);
        self::assertGreaterThanOrEqual(0.001, $sums[0][1] ?? 0.0);
        self::assertLessThan(1.0, $sums[0][1] ?? 1.0);
    }

    public function testRethrowsTheSameExceptionAndRecordsError(): void
    {
        $failure = new RuntimeException('boom');

        try {
            $this->middleware()->handle(new EchoInput(), ActionContext::of(EchoAction::class), static fn (InputInterface $input): never => throw $failure);
            self::fail('The exception must be rethrown.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame([[['app', 'cmp', EchoAction::class, 'error'], 1.0]], InMemoryRegistry::counts($this->registry, self::METRIC));
    }

    public function testRecordsAnExpectedFailureAndRethrowsIt(): void
    {
        $failure = new class('out of stock') extends RuntimeException implements ExpectedFailureInterface {};

        try {
            $this->middleware()->handle(new EchoInput(), ActionContext::of(EchoAction::class), static fn (InputInterface $input): never => throw $failure);
            self::fail('The exception must be rethrown.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame([[['app', 'cmp', EchoAction::class, 'expected_failure'], 1.0]], InMemoryRegistry::counts($this->registry, self::METRIC));
    }

    public function testUsesTheResolvedActionName(): void
    {
        $names = new ActionNameResolver([new class implements ActionNameBuilderInterface {
            public function build(string $actionClass): string
            {
                return 'echo';
            }

            public function supports(string $actionClass): bool
            {
                return true;
            }
        }]);

        $this->middleware($names)->handle(new EchoInput(), ActionContext::of(EchoAction::class), static fn (InputInterface $input): OutputInterface => new EchoOutput('ok'));

        self::assertSame([[['app', 'cmp', 'echo', 'success'], 1.0]], InMemoryRegistry::counts($this->registry, self::METRIC));
    }

    public function testACollectorFailureDoesNotFailTheAction(): void
    {
        $repository = self::createStub(MetricRepositoryInterface::class);
        $repository->method('find')->willThrowException(new RuntimeException('metrics down'));
        $middleware = new MetricsMiddleware(new ActionDurationCollector($this->registry, $repository, 'app', 'cmp'), new ActionNameResolver());
        $output = new EchoOutput('ok');

        self::assertSame($output, $middleware->handle(new EchoInput(), ActionContext::of(EchoAction::class), static fn (InputInterface $input): OutputInterface => $output));
    }

    public function testACollectorFailureKeepsTheActionException(): void
    {
        $repository = self::createStub(MetricRepositoryInterface::class);
        $repository->method('find')->willThrowException(new RuntimeException('metrics down'));
        $middleware = new MetricsMiddleware(new ActionDurationCollector($this->registry, $repository, 'app', 'cmp'), new ActionNameResolver());
        $failure = new RuntimeException('boom');

        try {
            $middleware->handle(new EchoInput(), ActionContext::of(EchoAction::class), static fn (InputInterface $input): never => throw $failure);
            self::fail('The exception must be rethrown.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    public function testAThrowingNameBuilderDoesNotFailTheAction(): void
    {
        $output = new EchoOutput('ok');

        self::assertSame($output, $this->middleware($this->throwingNames())->handle(new EchoInput(), ActionContext::of(EchoAction::class), static fn (InputInterface $input): OutputInterface => $output));
    }

    public function testAThrowingNameBuilderIsLoggedWithTheActionClass(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Could not record the action metric', self::callback(
            static fn (array $context): bool => $context['metric'] === LogicMetric::ACTION_DURATION->value
                && $context['action'] === EchoAction::class
                && $context['exception'] instanceof RuntimeException
                && $context['exception']->getMessage() === 'name builder down',
        ));
        $collector = new ActionDurationCollector($this->registry, new MetricRepository([]), 'app', 'cmp');
        $collector->setLogger($logger);

        (new MetricsMiddleware($collector, $this->throwingNames()))->handle(new EchoInput(), ActionContext::of(EchoAction::class), static fn (InputInterface $input): OutputInterface => new EchoOutput('ok'));
    }

    public function testAThrowingNameBuilderKeepsTheActionException(): void
    {
        $failure = new RuntimeException('boom');

        try {
            $this->middleware($this->throwingNames())->handle(new EchoInput(), ActionContext::of(EchoAction::class), static fn (InputInterface $input): never => throw $failure);
            self::fail('The exception must be rethrown.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    public function testSupportsEveryActionAtPriority400(): void
    {
        self::assertTrue($this->middleware()->supports(ActionContext::of(EchoAction::class)));
        self::assertSame(400, MetricsMiddleware::PRIORITY);
        $attribute = (new ReflectionClass(MetricsMiddleware::class))->getAttributes(AsActionMiddleware::class)[0] ?? null;
        self::assertNotNull($attribute);
        self::assertSame(400, $attribute->newInstance()->priority);
    }

    private function throwingNames(): ActionNameResolver
    {
        return new ActionNameResolver([new class implements ActionNameBuilderInterface {
            public function build(string $actionClass): never
            {
                throw new RuntimeException('name builder down');
            }

            public function supports(string $actionClass): bool
            {
                return true;
            }
        }]);
    }

    private function middleware(?ActionNameResolver $names = null): MetricsMiddleware
    {
        return new MetricsMiddleware(new ActionDurationCollector($this->registry, new MetricRepository([]), 'app', 'cmp'), $names ?? new ActionNameResolver());
    }
}
