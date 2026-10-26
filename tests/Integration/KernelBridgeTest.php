<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Test\Integration;

use Monolog\Logger;
use Msstc4Symfony\LogicBundle\Application\Logging\LoggingMiddleware;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\MiddlewareChainInterface;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\ActionDurationCollector;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\LogicMetric;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\MetricsMiddleware;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\EchoAction;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\EchoInput;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\ExceptionHandlerSnapshot;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\FailingAction;
use Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures\InMemoryRegistry;
use Msstc4Symfony\MetricsBridgeLogic\Test\Integration\Kernel\TestKernel;
use Msstc4Symfony\MetricsBundle\Application\LogChannel;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

#[CoversNothing]
final class KernelBridgeTest extends TestCase
{
    private const METRIC = 'symfony_logic_action_duration_histogram_seconds';

    private TestKernel $kernel;

    /** @var list<string> */
    private array $bundleDeprecations = [];

    private ExceptionHandlerSnapshot $exceptionHandlers;

    #[Override]
    protected function setUp(): void
    {
        // trigger_deprecation() silences its notice with @, which PHPUnit ignores; record it directly.
        set_error_handler(function (int $level, string $message): bool {
            if (str_contains($message, 'msstc4symfony/')) {
                $this->bundleDeprecations[] = $message;
            }

            return false;
        }, E_USER_DEPRECATED);
        (new Filesystem())->remove(TestKernel::cacheRoot());
        $this->exceptionHandlers = ExceptionHandlerSnapshot::take();
        $this->kernel = new TestKernel('test', false);
        $this->kernel->boot();
    }

    #[Override]
    protected function tearDown(): void
    {
        restore_error_handler();
        // Guarded so that a failing setUp() is reported instead of an uninitialised property.
        if (isset($this->kernel)) {
            $this->kernel->shutdown();
        }

        // FrameworkBundle::boot() on symfony/error-handler before 7.4.17 leaves its exception handler
        // installed when another error handler is active, which PHPUnit reports as risky.
        if (isset($this->exceptionHandlers)) {
            $this->exceptionHandlers->restore();
        }

        (new Filesystem())->remove(TestKernel::cacheRoot());
    }

    public function testAnActionCallIsRecordedWithTheApplicationLabels(): void
    {
        $action = $this->container()->get(EchoAction::class);
        self::assertIsCallable($action);
        $action(new EchoInput('hi'));

        self::assertSame([[['shop', 'api', EchoAction::class, 'success'], 1.0]], InMemoryRegistry::counts($this->registry(), self::METRIC));
        self::assertSame([], $this->bundleDeprecations);
    }

    public function testAFailingActionIsRecordedAsError(): void
    {
        $action = $this->container()->get(FailingAction::class);
        self::assertIsCallable($action);

        try {
            $action(new EchoInput());
            self::fail('The Action must throw.');
        } catch (RuntimeException) {
        }

        self::assertSame([[['shop', 'api', FailingAction::class, 'error'], 1.0]], InMemoryRegistry::counts($this->registry(), self::METRIC));
    }

    public function testMetricsBundleKnowsTheMetric(): void
    {
        $enums = $this->kernel->getContainer()->getParameter('msstc4symfony_metrics.metric_enums');
        self::assertIsArray($enums);
        self::assertContains(LogicMetric::class, $enums);
    }

    public function testTheMiddlewareRunsOutsideLogging(): void
    {
        $classes = array_map(static fn (object $middleware): string => $middleware::class, $this->middlewares());

        $metrics = array_search(MetricsMiddleware::class, $classes, true);
        $logging = array_search(LoggingMiddleware::class, $classes, true);
        self::assertIsInt($metrics);
        self::assertIsInt($logging);
        self::assertLessThan($logging, $metrics);
    }

    public function testTheCollectorLogsToTheUndecoratedMetricsChannel(): void
    {
        // Reaches the private collector: a monolog-bundle change could drop the channel silently.
        $middleware = $this->container()->get(MetricsMiddleware::class);
        self::assertInstanceOf(MetricsMiddleware::class, $middleware);
        $collector = (new ReflectionProperty($middleware, 'collector'))->getValue($middleware);
        self::assertInstanceOf(ActionDurationCollector::class, $collector);
        $logger = (new ReflectionProperty($collector, 'logger'))->getValue($collector);
        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame(LogChannel::NAME, $logger->getName());
    }

    private function registry(): CollectorRegistry
    {
        $registry = $this->container()->get(RegistryInterface::class);
        self::assertInstanceOf(CollectorRegistry::class, $registry);

        return $registry;
    }

    private function container(): ContainerInterface
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    /**
     * @return list<ActionMiddlewareInterface>
     */
    private function middlewares(): array
    {
        if (!interface_exists(MiddlewareChainInterface::class)) {
            self::markTestSkipped('logic-bundle exposes the middleware order from 2.1 on.');
        }

        $action = $this->container()->get(EchoAction::class);
        self::assertInstanceOf(MiddlewareChainInterface::class, $action);

        return $action->middlewares();
    }
}
