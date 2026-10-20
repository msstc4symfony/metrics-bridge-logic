<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic;

use Msstc4Symfony\MetricsBridgeLogic\DependencyInjection\CompilerPass\RegisterLogicMetricsPass;
use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class MetricsBridgeLogicBundle extends AbstractBundle
{
    protected string $extensionAlias = 'msstc4symfony_metrics_bridge_logic';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterLogicMetricsPass());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/Resources/config/services.php');
    }
}
