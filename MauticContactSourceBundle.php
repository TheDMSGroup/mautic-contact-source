<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle;

use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use MauticPlugin\MauticContactSourceBundle\DependencyInjection\Compiler\RealTimeCampaignRepositoryPass;

class MauticContactSourceBundle extends PluginBundleBase
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container); 
        $container->addCompilerPass(new RealTimeCampaignRepositoryPass());
    } 
}
