<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Form\Type;

use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class ContactSourceType.
 */
class ContactSourceType extends AbstractType
{
    /**
     * @var CorePermissions
     */
    private $security;

    /**
     * ContactSourceType constructor.
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
        $builder->addEventSubscriber(new FormExitSubscriber('contactsource', $options));

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
                'label'      => 'mautic.contactsource.form.description',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control editor'],
                'required'   => false,
            ]
        );

        $builder->add(
            'description_public',
            'textarea',
            [
                'label'      => 'mautic.contactsource.form.description_public',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control editor',
                    'tooltip' => 'mautic.contactsource.form.description_public.tooltip',
                ],
                'required'   => false,
            ]
        );

        $builder->add(
            'documentation',
            'boolean',
            [
                'label'      => 'mautic.contactsource.form.documentation',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.contactsource.form.website.tooltip',
                ],
                'required'   => false,
            ]
        );

        $builder->add(
            'campaign_settings',
            'textarea',
            [
                'label'      => 'mautic.contactsource.form.campaign.settings',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class' => 'form-control',
                    'rows'  => 12,
                ],
                'required'   => false,
            ]
        );

        //add category
        $builder->add(
            'category',
            'category',
            [
                'bundle' => 'plugin:contactsource',
            ]
        );

        if (!empty($options['data']) && $options['data']->getId()) {
            $readonly = !$this->security->isGranted('plugin:contactsource:items:publish');
            $data     = $options['data']->isPublished(false);
        } elseif (!$this->security->isGranted('plugin:contactsource:items:publish')) {
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
                'format'     => 'yyyy-MM-dd HH:mm',
                'required'   => false,
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
                'format'     => 'yyyy-MM-dd HH:mm',
                'required'   => false,
            ]
        );

        $builder->add(
            'documentation',
            'yesno_button_group',
            [
                'label'             => 'mautic.contactsource.form.documentation',
                'label_attr'        => ['class' => 'control-label'],
                'choices_as_values' => true,
                'required'          => true,
                'attr'              => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.contactsource.form.documentation.tooltip',
                ],
            ]
        );

        $builder->add(
            'token',
            'text',
            [
                'label'      => 'mautic.contactsource.form.token',
                'label_attr' => ['class' => 'control-label'],
                'required'   => false,
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.contactsource.form.token.tooltip',
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
                'data_class' => 'MauticPlugin\MauticContactSourceBundle\Entity\ContactSource',
            ]
        );
        $resolver->setDefined(['update_select']);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'contactsource';
    }
}
