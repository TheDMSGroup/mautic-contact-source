<?php

namespace MauticPlugin\MauticContactServerBundle\Form\Type;

use MauticPlugin\MauticContactServerBundle\Model\ContactServerModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ContactServerListType
 * @package MauticPlugin\MauticContactServerBundle\Form\Type
 */
class ContactServerListType extends AbstractType
{
    /**
     * @var ContactServerModel
     */
    protected $contactServerModel;

    private $repo;

    /**
     * @param ContactServerModel $contactServerModel
     */
    public function __construct(ContactServerModel $contactServerModel)
    {
        $this->contactServerModel = $contactServerModel;
        $this->repo       = $this->contactServerModel->getRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'choices' => function (Options $options) {
                    $choices = [];

                    $list = $this->repo->getContactServerList($options['data']);
                    foreach ($list as $row) {
                        $choices[$row['id']] = $row['name'];
                    }

                    //sort by language
                    ksort($choices);

                    return $choices;
                },
                'expanded'    => false,
                'multiple'    => true,
                'required'    => false,
                'empty_value' => function (Options $options) {
                    return (empty($options['choices'])) ? 'mautic.contactserver.no.contactserveritem.note' : 'mautic.core.form.chooseone';
                },
                'disabled' => function (Options $options) {
                    return empty($options['choices']);
                },
                'top_level'      => 'variant',
                'variant_parent' => null,
                'ignore_ids'     => [],
            ]
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'contactserver_list';
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return 'choice';
    }
}
