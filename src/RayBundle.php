<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony;

use Dock\Ray\Symfony\DependencyInjection\RayExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class RayBundle extends Bundle
{
    public const VERSION = '1.1.0';

    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new RayExtension;
    }
}
