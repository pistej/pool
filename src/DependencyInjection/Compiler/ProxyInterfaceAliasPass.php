<?php

declare(strict_types=1);

namespace Sfrpc\Pool\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ProxyInterfaceAliasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // We look for all services we registered as proxies.
        // We didn't tag them explicitly, but we can iterate over definitions
        // that were registered by our extension.
        // Alternatively, let's tag them in the Extension.

        $taggedServices = $container->findTaggedServiceIds('sfrpc_pool.proxy');

        foreach ($taggedServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass();

            if (!$class || !class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getInterfaceNames() as $interface) {
                // Sfrpc interfaces should be aliased.
                // We skip common system interfaces if needed.
                if (!$container->has($interface) && !$container->hasAlias($interface)) {
                    $container->setAlias($interface, $id)->setPublic(true);
                }
            }
        }
    }
}
