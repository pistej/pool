<?php

declare(strict_types=1);

namespace Sfrpc\Pool;

use Sfrpc\Pool\DependencyInjection\Compiler\ProxyInterfaceAliasPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SfrpcPoolBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ProxyInterfaceAliasPass());
    }
}
