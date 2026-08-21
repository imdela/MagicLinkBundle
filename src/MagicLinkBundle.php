<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle;

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
        // means a consumer only has to run a migration for the ossm_magic_link
        // table. In apps without DoctrineBundle the config stays inert.
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'magic_link_bundle' => [
                        'is_bundle' => true,
                        'dir' => 'src/Entity',
                        'prefix' => 'Ossm\\MagicLinkBundle\\Entity',
                        'type' => 'attribute',
                    ],
                ],
            ],
        ]);
    }
}
