<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Infrastructure\Metrics;

use Closure;
use Msstc4Symfony\LogicBundle\Application\Action\ActionNameResolver;
use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute\AsActionMiddleware;
use Override;
use Throwable;

/**
 * Outside logging, so the duration covers validation and the lock wait the caller sees.
 *
 * @internal
 */
#[AsActionMiddleware(priority: self::PRIORITY)]
final readonly class MetricsMiddleware implements ActionMiddlewareInterface
{
    public const PRIORITY = 400;

    public function __construct(
        private ActionDurationCollector $collector,
        private ActionNameResolver $names,
    ) {
    }

    #[Override]
    public function supports(ActionContext $context): bool
    {
        return true;
    }

    #[Override]
    public function handle(InputInterface $input, ActionContext $context, Closure $next): OutputInterface
    {
        $start = hrtime(true);
        $failure = null;

        try {
            return $next($input);
        } catch (Throwable $exception) {
            $failure = $exception;

            throw $exception;
        } finally {
            $this->record($context, $failure, $start);
        }
    }

    private function record(ActionContext $context, ?Throwable $failure, int|float $start): void
    {
        $seconds = (hrtime(true) - $start) / 1e9;

        try {
            $this->collector->observe($this->names->resolve($context), ActionOutcome::of($failure), $seconds);
        } catch (Throwable $exception) {
            // A throwing name builder must neither fail the Action nor replace its exception.
            $this->collector->reportFailure($context->actionClass, $exception);
        }
    }
}
