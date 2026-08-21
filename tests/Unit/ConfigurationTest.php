<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Tests\Unit;

use Ossm\MagicLinkBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testConfigTreeBuildsWithExpectedRootName(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        self::assertSame('magic_link', $treeBuilder->buildTree()->getName());
    }
}
