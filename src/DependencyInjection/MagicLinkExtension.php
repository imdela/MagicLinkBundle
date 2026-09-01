<?php

declare(strict_types=1);

namespace Mosl\MagicLinkBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MagicLinkExtension extends Extension
{
    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $tokenTtl = $config['token_ttl'];
        if (! is_int($tokenTtl)) {
            // The Configuration tree guarantees an integer; this guards the
            // type PHPStan cannot see through processConfiguration().
            throw new \LogicException('magic_link.token_ttl must be an integer.');
        }
        $container->setParameter('mosl_magic_link.token_ttl', $tokenTtl);
    }
}
