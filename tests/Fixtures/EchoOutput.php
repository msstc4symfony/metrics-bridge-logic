<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeLogic\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;

final readonly class EchoOutput implements OutputInterface
{
    public function __construct(public string $text)
    {
    }
}
