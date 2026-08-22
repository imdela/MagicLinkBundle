<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Tests\Unit;

use Ossm\MagicLinkBundle\DependencyInjection\Configuration;
use Ossm\MagicLinkBundle\Manager\MagicLinkManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testConfigTreeBuildsWithExpectedRootName(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        self::assertSame('magic_link', $treeBuilder->buildTree()->getName());
    }

    public function testTokenTtlDefaultsToTheManagerDefault(): void
    {
        $processed = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertSame(MagicLinkManager::DEFAULT_TTL, $processed['token_ttl']);
    }

    public function testTokenTtlAcceptsAnExplicitValue(): void
    {
        $processed = (new Processor())->processConfiguration(new Configuration(), [
            [
                'token_ttl' => 120,
            ],
        ]);

        self::assertSame(120, $processed['token_ttl']);
    }

    public function testTokenTtlRejectsZero(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            [
                'token_ttl' => 0,
            ],
        ]);
    }

    public function testTokenTtlRejectsNegativeValues(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            [
                'token_ttl' => -1,
            ],
        ]);
    }
}
