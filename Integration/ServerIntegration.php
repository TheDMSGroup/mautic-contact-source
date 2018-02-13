<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

/**
 * Class ServerIntegration
 * @package MauticPlugin\MauticContactServerBundle\Integration
 */
class ServerIntegration extends AbstractIntegration
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
        return 'Server';
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return 'Servers';
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
                    'label' => $this->translator->trans('mautic.contactserver.domain'),
                    'data'  => !isset($data['domain']) ? false : $data['domain'],
                    'attr'  => [
                        'tooltip' => $this->translator->trans('mautic.contactserver.domain.tooltip'),
                    ]
                ]
            );
        }
    }
}
