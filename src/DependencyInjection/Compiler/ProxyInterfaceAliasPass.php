<?php

declare(strict_types=1);

namespace Sfrpc\Pool\DependencyInjection\Compiler;

use ReflectionClass;
use Sfrpc\Pool\Grpc\SfrpcClientInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

            $reflection = new ReflectionClass($class);
            foreach ($reflection->getInterfaceNames() as $interface) {
                // Only alias actual generated sfrpc client interfaces (identified via
                // the SfrpcClientInterface marker), not every interface the proxy
                // happens to implement. Aliasing everything (including inherited or
                // system interfaces like Stringable, or unrelated app interfaces -
                // even ones that happen to also be named "*ClientInterface") could
                // hijack unrelated autowiring in the host application.
                if (!is_subclass_of($interface, SfrpcClientInterface::class)) {
                    continue;
                }

                if (!$container->has($interface) && !$container->hasAlias($interface)) {
                    $container->setAlias($interface, $id)->setPublic(true);
                }
            }
        }
    }
}
