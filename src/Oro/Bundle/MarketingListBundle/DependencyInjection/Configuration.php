<?php

namespace Oro\Bundle\MarketingListBundle\DependencyInjection;

use Oro\Bundle\ConfigBundle\DependencyInjection\SettingsBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('oro_marketing_list');

        SettingsBuilder::append(
            $rootNode,
            [
                'feature_enabled' => ['value' => true],
            ]
        );

        return $treeBuilder;
    }
}
