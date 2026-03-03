<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\SfrpcPoolBundle;
use Sfrpc\Pool\DependencyInjection\Compiler\ProxyInterfaceAliasPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SfrpcPoolBundleTest extends TestCase
{
    public function testBuildRegistersCompilerPass(): void
    {
        $container = new ContainerBuilder();
        $bundle = new SfrpcPoolBundle();

        $bundle->build($container);

        $found = false;
        foreach ($container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof ProxyInterfaceAliasPass) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Bundle should register ProxyInterfaceAliasPass');
    }
}
