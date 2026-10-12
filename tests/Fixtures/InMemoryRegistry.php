<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures;

use Prometheus\CollectorRegistry;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\InMemory;

final class InMemoryRegistry
{
    public static function create(): CollectorRegistry
    {
        return new CollectorRegistry(new InMemory(), false);
    }

    /**
     * Label values and value of every `_count` sample of the histogram.
     *
     * @return list<array{list<string>, float}>
     */
    public static function counts(CollectorRegistry $registry, string $metricName): array
    {
        return self::samples($registry, $metricName, '_count');
    }

    /**
     * @return list<array{list<string>, float}>
     */
    public static function sums(CollectorRegistry $registry, string $metricName): array
    {
        return self::samples($registry, $metricName, '_sum');
    }

    /**
     * @return list<array{list<string>, float}>
     */
    private static function samples(CollectorRegistry $registry, string $metricName, string $suffix): array
    {
        $found = [];
        foreach ($registry->getMetricFamilySamples() as $family) {
            if (!$family instanceof MetricFamilySamples || $family->getName() !== $metricName) {
                continue;
            }

            foreach ($family->getSamples() as $sample) {
                if ($sample->getName() === $metricName . $suffix) {
                    $found[] = [array_values(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $sample->getLabelValues())), (float) $sample->getValue()];
                }
            }
        }

        return $found;
    }
}
