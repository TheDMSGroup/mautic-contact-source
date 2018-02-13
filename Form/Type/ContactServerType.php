<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Form\Type;

use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class ContactServerType
 * @package MauticPlugin\MauticContactServerBundle\Form\Type
 */
class ContactServerType extends AbstractType
{
    /**
     * @var CorePermissions
     */
    private $security;

    /**
     * ContactServerType constructor.
     *
     * @param CorePermissions $security
     */
    public function __construct(CorePermissions $security)
    {
        $this->security = $security;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new CleanFormSubscriber(['website' => 'url']));
        $builder->addEventSubscriber(new FormExitSubscriber('contactserver', $options));

        $builder->add(
            'name',
            'text',
            [
                'label'      => 'mautic.core.name',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control'],
            ]
        );

        $builder->add(
            'description',
            'textarea',
            [
                'label'      => 'mautic.core.description',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control editor'],
                'required'   => false,
            ]
        );

        $builder->add(
            'api_payload',
            'textarea',
            [
                'label'      => 'mautic.contactserver.form.api_payload',
                'label_attr' => ['class' => 'control-label api-payload'],
                'attr'       => [
                    'class'        => 'form-control api-payload',
                    'rows'         => 12,
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'file_payload',
            'textarea',
            [
                'label'      => 'mautic.contactserver.form.file_payload',
                'label_attr' => ['class' => 'control-label file-payload'],
                'attr'       => [
                    'class'        => 'form-control file-payload',
                    'rows'         => 12,
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'website',
            'url',
            [
                'label'      => 'mautic.contactserver.form.website',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.contactserver.form.website.tooltip',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'attribution_default',
            'number',
            [
                'label'      => 'mautic.contactserver.form.attribution.default',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    'preaddon' => 'fa fa-money',
                    'tooltip' => 'mautic.contactserver.form.attribution.default.tooltip',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'attribution_settings',
            'textarea',
            [
                'label'      => 'mautic.contactserver.form.attribution.settings',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control',
                    'rows'         => 12,
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'duplicate',
            'textarea',
            [
                'label'      => 'mautic.contactserver.form.duplicate',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control',
                    'rows'         => 12,
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'exclusive',
            'textarea',
            [
                'label'      => 'mautic.contactserver.form.exclusive',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control',
                    'rows'         => 12,
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'filter',
            'textarea',
            [
                'label'      => 'mautic.contactserver.form.filter',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control',
                    'rows'         => 12,
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'limits',
            'textarea',
            [
                'label'      => 'mautic.contactserver.form.limits',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control',
                    'rows'         => 12,
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'schedule_timezone',
            'timezone',
            [
                'label'      => 'mautic.contactserver.form.schedule_timezone',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip'      => 'mautic.contactserver.form.schedule_timezone.tooltip',
                ],
                'multiple'    => false,
                'empty_value' => 'mautic.user.user.form.defaulttimezone',
                'required'    => false,
            ]
        );

        $builder->add(
            'schedule_hours',
            'textarea',
            [
                'label'      => 'mautic.contactserver.form.schedule_hours',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control',
                    'rows'         => 12,
                    'tooltip'      => 'mautic.contactserver.form.schedule_hours.tooltip',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'schedule_exclusions',
            'textarea',
            [
                'label'      => 'mautic.contactserver.form.schedule_exclusions',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control',
                    'rows'         => 12,
                    'tooltip'      => 'mautic.contactserver.form.schedule_exclusions.tooltip',
                ],
                'required' => false,
            ]
        );

        //add category
        $builder->add(
            'category',
            'category',
            [
                'bundle' => 'plugin:contactserver',
            ]
        );

        if (!empty($options['data']) && $options['data']->getId()) {
            $readonly = !$this->security->isGranted('plugin:contactserver:items:publish');
            $data     = $options['data']->isPublished(false);
        } elseif (!$this->security->isGranted('plugin:contactserver:items:publish')) {
            $readonly = true;
            $data     = false;
        } else {
            $readonly = false;
            $data     = false;
        }

        $builder->add(
            'isPublished',
            'yesno_button_group',
            [
                'read_only' => $readonly,
                'data'      => $data,
            ]
        );

        $builder->add(
            'publishUp',
            'datetime',
            [
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishup',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime',
                ],
                'format'   => 'yyyy-MM-dd HH:mm',
                'required' => false,
            ]
        );

        $builder->add(
            'publishDown',
            'datetime',
            [
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishdown',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime',
                ],
                'format'   => 'yyyy-MM-dd HH:mm',
                'required' => false,
            ]
        );

        $builder->add(
            'type',
            'button_group',
            [
                'label' => 'mautic.contactserver.form.type',
                'label_attr' => ['class' => 'control-label contactserver-type'],
                'choices' => [
                    'mautic.contactserver.form.type.api' => 'api',
                    'mautic.contactserver.form.type.file' => 'file',
                ],
                'choices_as_values' => true,
                'required' => true,
                'attr'       => [
                    'class'       => 'form-control',
                    'tooltip'     => 'mautic.contactserver.form.type.tooltip',
                    'onchange'    => 'Mautic.contactserverTypeChange(this);'
                ],
            ]
        );

        if (!empty($options['action'])) {
            $builder->setAction($options['action']);
        }

        $customButtons = [];

        if (!empty($options['update_select'])) {
            $builder->add(
                'buttons',
                'form_buttons',
                [
                    'apply_text'        => false,
                    'pre_extra_buttons' => $customButtons,
                ]
            );
            $builder->add(
                'updateSelect',
                'hidden',
                [
                    'data'   => $options['update_select'],
                    'mapped' => false,
                ]
            );
        } else {
            $builder->add(
                'buttons',
                'form_buttons',
                [
                    'pre_extra_buttons' => $customButtons,
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'MauticPlugin\MauticContactServerBundle\Entity\ContactServer',
            ]
        );
        $resolver->setDefined(['update_select']);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'contactserver';
    }
}
