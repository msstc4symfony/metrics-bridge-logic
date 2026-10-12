<?php

declare(strict_types=1);

use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\ActionDurationCollector;
use Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics\MetricsMiddleware;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    $services->set(ActionDurationCollector::class)
        ->arg('$applicationName', '%msstc4symfony_metrics.application_name%')
        ->arg('$componentName', '%msstc4symfony_metrics.component_name%')
    ;
    $services->set(MetricsMiddleware::class);
};
