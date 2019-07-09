<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

/**
 * Class SourceIntegration.
 *
 * This plugin does not add integrations. This is here purely for name/logo/etc.
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
     * @param \Symfony\Component\Form\FormBuilder $builder
     * @param array                               $data
     * @param string                              $formArea
     */
    public function appendToForm(&$builder, $data, $formArea)
    {
        if ('features' == $formArea) {
            $builder->add(
                'verbose',
                'text',
                [
                    'label'      => 'mautic.contactsource.api.verbose',
                    'data'       => !isset($data['verbose']) ? '1' : $data['verbose'],
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'tooltip' => 'mautic.contactsource.api.verbose.tooltip',
                    ],
                    'required'   => false,
                ]
            );
            $builder->add(
                'domain',
                'text',
                [
                    'label'      => 'mautic.contactsource.api.docs.domain',
                    'data'       => !isset($data['domain']) ? null : $data['domain'],
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'tooltip' => 'mautic.contactsource.api.docs.domain.tooltip',
                    ],
                    'required'   => false,
                ]
            );
            $builder->add(
                'assistance',
                'text',
                [
                    'label'      => 'mautic.contactsource.api.docs.assistance',
                    'data'       => !isset($data['assistance']) ? null : $data['assistance'],
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'tooltip' => 'mautic.contactsource.api.docs.assistance.tooltip',
                    ],
                    'required'   => false,
                ]
            );
            $builder->add(
                'introduction',
                'textarea',
                [
                    'label'      => 'mautic.contactsource.api.docs.introduction',
                    'data'       => !isset($data['introduction']) ? null : $data['introduction'],
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class'   => 'form-control editor editor-advanced',
                        'tooltip' => 'mautic.contactsource.api.docs.introduction.tooltip',
                    ],
                    'required'   => false,
                ]
            );
            $builder->add(
                'parallel_realtime',
                'yesno_button_group',
                [
                    'label'             => 'mautic.contactsource.form.parallel_realtime',
                    'label_attr'        => ['class' => 'control-label'],
                    'choices_as_values' => true,
                    'required'          => false,
                    'data'              => !isset($data['parallel_realtime']) ? false : (bool) $data['parallel_realtime'],
                    'attr'              => [
                        'class'   => 'form-control',
                        'tooltip' => 'mautic.contactsource.form.parallel_realtime.tooltip',
                    ],
                ]
            );
            $builder->add(
                'parallel_offline',
                'yesno_button_group',
                [
                    'label'             => 'mautic.contactsource.form.parallel_offline',
                    'label_attr'        => ['class' => 'control-label'],
                    'choices_as_values' => true,
                    'required'          => false,
                    'data'              => !isset($data['parallel_offline']) ? false : (bool) $data['parallel_offline'],
                    'attr'              => [
                        'class'   => 'form-control',
                        'tooltip' => 'mautic.contactsource.form.parallel_offline.tooltip',
                    ],
                ]
            );
            $builder->add(
                'parallel_import',
                'yesno_button_group',
                [
                    'label'             => 'mautic.contactsource.form.parallel_import',
                    'label_attr'        => ['class' => 'control-label'],
                    'choices_as_values' => true,
                    'required'          => false,
                    'data'              => !isset($data['parallel_import']) ? false : (bool) $data['parallel_import'],
                    'attr'              => [
                        'class'   => 'form-control',
                        'tooltip' => 'mautic.contactsource.form.parallel_import.tooltip',
                    ],
                ]
            );
            $builder->add(
                'parallel_schedule',
                'yesno_button_group',
                [
                    'label'             => 'mautic.contactsource.form.parallel_schedule',
                    'label_attr'        => ['class' => 'control-label'],
                    'choices_as_values' => true,
                    'required'          => false,
                    'data'              => !isset($data['parallel_schedule']) ? false : (bool) $data['parallel_schedule'],
                    'attr'              => [
                        'class'   => 'form-control',
                        'tooltip' => 'mautic.contactsource.form.parallel_schedule.tooltip',
                    ],
                ]
            );
            $builder->add(
                'parallel_batch',
                'yesno_button_group',
                [
                    'label'             => 'mautic.contactsource.form.parallel_batch',
                    'label_attr'        => ['class' => 'control-label'],
                    'choices_as_values' => true,
                    'required'          => false,
                    'data'              => !isset($data['parallel_batch']) ? false : (bool) $data['parallel_batch'],
                    'attr'              => [
                        'class'   => 'form-control',
                        'tooltip' => 'mautic.contactsource.form.parallel_batch.tooltip',
                    ],
                ]
            );
            $builder->add(
                'email_dns_check',
                'yesno_button_group',
                [
                    'label'             => 'mautic.contactsource.form.email_dns_check',
                    'label_attr'        => ['class' => 'control-label'],
                    'choices_as_values' => true,
                    'required'          => false,
                    'data'              => !isset($data['email_dns_check']) ? false : (bool) $data['email_dns_check'],
                    'attr'              => [
                        'class'   => 'form-control',
                        'tooltip' => 'mautic.contactsource.form.email_dns_check.tooltip',
                    ],
                ]
            );
            $builder->add(
                'field_group_exclusions',
                'text',
                [
                    'label'      => 'mautic.contactsource.field_group.exclusions',
                    'data'       => isset($data['field_group_exclusions']) ? $data['field_group_exclusions'] : 'system,enhancement',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'tooltip' => 'mautic.contactsource.field_group_exclusions.tooltip',
                    ],
                    'required'   => false,
                ]
            );
        }
    }
}
