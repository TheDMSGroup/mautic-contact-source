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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class ContactSourceShowType.
 */
class ContactSourceShowType extends AbstractType
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'contactsource',
            'contactsource_list',
            [
                'label'       => 'mautic.contactsource.contactsourceitem.selectitem',
                'label_attr'  => ['class' => 'control-label'],
                'attr'        => [
                    'class'    => 'form-control',
                    'tooltip'  => 'mautic.contactsource.contactsourceitem.selectitem_descr',
                    'onchange' => 'Mautic.disabledContactSourceActions()',
                ],
                'multiple'    => false,
                'required'    => true,
                'constraints' => [
                    new NotBlank(
                        ['message' => 'mautic.contactsource.choosecontactsource.notblank']
                    ),
                ],
            ]
        );

        if (!empty($options['update_select'])) {
            $windowUrl = $this->router->generate(
                'mautic_contactsource_action',
                [
                    'objectAction' => 'new',
                    'contentOnly'  => 1,
                    'updateSelect' => $options['update_select'],
                ]
            );

            $builder->add(
                'newContactSourceButton',
                'button',
                [
                    'attr'  => [
                        'class'   => 'btn btn-primary btn-nospin',
                        'onclick' => 'Mautic.loadNewWindow({
                        "windowUrl": "'.$windowUrl.'"
                    })',
                        'icon'    => 'fa fa-plus',
                    ],
                    'label' => 'mautic.contactsource.show.new.item',
                ]
            );

            // create button edit contactsource
            $windowUrlEdit = $this->router->generate(
                'mautic_contactsource_action',
                [
                    'objectAction' => 'edit',
                    'objectId'     => 'contactsourceId',
                    'contentOnly'  => 1,
                    'updateSelect' => $options['update_select'],
                ]
            );

            $builder->add(
                'editContactSourceButton',
                'button',
                [
                    'attr'  => [
                        'class'    => 'btn btn-primary btn-nospin',
                        'onclick'  => 'Mautic.loadNewWindow(Mautic.standardContactSourceUrl({"windowUrl": "'.$windowUrlEdit.'"}))',
                        'disabled' => !isset($options['data']['contactsource']),
                        'icon'     => 'fa fa-edit',
                    ],
                    'label' => 'mautic.contactsource.show.edit.item',
                ]
            );
        }
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefined(['update_select']);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'contactsourceshow_list';
    }
}
