<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Test\Unit;

use LogicException;
use Msstc4Symfony\MetricsBridgeLogic\DependencyInjection\Compiler\RegisterLogicMetricsPass;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\LogicMetric;
use Msstc4Symfony\MetricsBundle\MetricsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(RegisterLogicMetricsPass::class)]
final class RegisterLogicMetricsPassTest extends TestCase
{
    public function testAppendsTheMetricOnce(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('msstc4symfony_metrics.metric_enums', ['App\Metric']);

        (new RegisterLogicMetricsPass())->process($container);
        (new RegisterLogicMetricsPass())->process($container);

        self::assertSame(['App\Metric', LogicMetric::class], $container->getParameter('msstc4symfony_metrics.metric_enums'));
    }

    public function testFailsWithoutMetricsBundle(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('MetricsBridgeLogicBundle needs MetricsBundle: register %s in config/bundles.php.', MetricsBundle::class));

        (new RegisterLogicMetricsPass())->process(new ContainerBuilder());
    }

    public function testRejectsANonListParameter(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('msstc4symfony_metrics.metric_enums', 'oops');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must be a list of enum classes, got string');

        (new RegisterLogicMetricsPass())->process($container);
    }
}
