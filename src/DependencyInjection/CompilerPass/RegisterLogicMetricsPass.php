<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\DependencyInjection\CompilerPass;

use LogicException;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\LogicMetric;
use Msstc4Symfony\MetricsBundle\MetricsBundle;
use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
final class RegisterLogicMetricsPass implements CompilerPassInterface
{
    private const METRIC_ENUMS_PARAMETER = 'msstc4symfony_metrics.metric_enums';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::METRIC_ENUMS_PARAMETER)) {
            throw new LogicException(sprintf('MetricsBridgeLogicBundle needs MetricsBundle: register %s in config/bundles.php.', MetricsBundle::class));
        }

        $enums = $container->getParameter(self::METRIC_ENUMS_PARAMETER);
        if (!is_array($enums)) {
            throw new LogicException(sprintf('Parameter "%s" must be a list of enum classes, got %s.', self::METRIC_ENUMS_PARAMETER, get_debug_type($enums)));
        }

        if (!in_array(LogicMetric::class, $enums, true)) {
            $enums[] = LogicMetric::class;
        }

        $container->setParameter(self::METRIC_ENUMS_PARAMETER, $enums);
    }
}
