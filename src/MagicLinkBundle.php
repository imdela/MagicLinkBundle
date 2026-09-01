<?php

declare(strict_types=1);

namespace Mosl\MagicLinkBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MagicLinkBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Make the shipped MagicLink entity persistable by the consuming app's
        // Doctrine without any app-side mapping: registering the mapping here
        // means a consumer only has to run a migration for the mosl_magic_link
        // table. In apps without DoctrineBundle the config stays inert.
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'MagicLinkBundle' => [
                        'is_bundle' => true,
                        'dir' => 'src/Entity',
                        'prefix' => 'Mosl\\MagicLinkBundle\\Entity',
                        'type' => 'attribute',
                    ],
                ],
            ],
        ]);
    }
}
