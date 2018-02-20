<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

/**
 * Class SourceIntegration
 *
 * This plugin does not add integrations. This is here purely for name/logo/etc.
 *
 * @package MauticPlugin\MauticContactSourceBundle\Integration
 */
class SourceIntegration extends AbstractIntegration
{

    /**
     * @return string
     */
    public function getAuthenticationType()
    {
        return 'none';
    }

    /**
     * @return array
     */
    public function getSupportedFeatures()
    {
        return [];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Source';
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return 'Sources';
    }

    /**
     * @param \Mautic\PluginBundle\Integration\Form|\Symfony\Component\Form\FormBuilder $builder
     * @param array $data
     * @param string $formArea
     */
    public function appendToForm(&$builder, $data, $formArea)
    {
        if ($formArea == 'features') {
            $builder->add(
                'domain',
                'text',
                [
                    'label' => $this->translator->trans('mautic.contactsource.domain'),
                    'data'  => !isset($data['domain']) ? false : $data['domain'],
                    'attr'  => [
                        'tooltip' => $this->translator->trans('mautic.contactsource.domain.tooltip'),
                    ]
                ]
            );
        }
    }
}
