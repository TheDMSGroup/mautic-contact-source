<?php

namespace MauticPlugin\MauticContactSourceBundle\Form\Type;

use MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ContactSourceListType.
 */
class ContactSourceListType extends AbstractType
{
    /**
     * @var ContactSourceModel
     */
    protected $contactSourceModel;

    private $repo;

    /**
     * @param ContactSourceModel $contactSourceModel
     */
    public function __construct(ContactSourceModel $contactSourceModel)
    {
        $this->contactSourceModel = $contactSourceModel;
        $this->repo               = $this->contactSourceModel->getRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'choices'        => function (Options $options) {
                    $choices = [];

                    $list = $this->repo->getContactSourceList($options['data']);
                    foreach ($list as $row) {
                        $choices[$row['id']] = $row['name'];
                    }

                    //sort by language
                    ksort($choices);

                    return $choices;
                },
                'expanded'       => false,
                'multiple'       => true,
                'required'       => false,
                'empty_value'    => function (Options $options) {
                    return (empty($options['choices'])) ? 'mautic.contactsource.no.contactsourceitem.note' : 'mautic.core.form.chooseone';
                },
                'disabled'       => function (Options $options) {
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
        return 'contactsource_list';
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return 'choice';
    }
}
