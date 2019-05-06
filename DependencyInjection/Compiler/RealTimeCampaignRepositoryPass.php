<?php

namespace MauticPlugin\MauticContactSourceBundle\DependencyInjection\Compiler;

use MauticPlugin\MauticContactSourceBundle\Entity\RealTimeCampaignRepository;
use Mautic\CampaignBundle\Entity\Campaign;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\Mapping\ClassMetadata;

class RealTimeCampaignRepositoryPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $container->getDefinition('mautic.campaign.repository.campaign')
                                ->setFactory(null)
                                ->setArguments([
                                    new Reference('doctrine.orm.entity_manager')
                                ])
                                ->setClass(RealTimeCampaignRepository::class);
    }
}
