<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Test\Unit;

use Msstc4Symfony\MetricsBridgeLogic\DependencyInjection\CompilerPass\RegisterLogicMetricsPass;
use Msstc4Symfony\MetricsBridgeLogic\MetricsBridgeLogicBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(MetricsBridgeLogicBundle::class)]
final class MetricsBridgeLogicBundleTest extends TestCase
{
    public function testExtensionAliasFollowsTheFamilyNaming(): void
    {
        self::assertSame('msstc4symfony_metrics_bridge_logic', (new MetricsBridgeLogicBundle())->getContainerExtension()?->getAlias());
    }

    public function testBuildRegistersTheMetricPass(): void
    {
        $container = new ContainerBuilder();

        (new MetricsBridgeLogicBundle())->build($container);

        $passes = array_map(get_class(...), $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        self::assertContains(RegisterLogicMetricsPass::class, $passes);
    }
}
