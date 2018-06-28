<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Form\Extension;

use Doctrine\ORM\EntityManager;
use Mautic\CampaignBundle\Entity\Campaign;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use Symfony\Component\Form\AbstractTypeExtension;
use Mautic\LeadBundle\Form\Type\LeadImportType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class LeadImportExtension extends AbstractTypeExtension
{
    /** @var EntityManager */
    protected $em;

    public function __construct(EntityManager $em)
    {
        /* @var EntityManager $em */
        $this->em = $em;
    }

    /**
     * Returns the name of the type being extended.
     *
     * @return string The name of the type being extended
     */
    public function getExtendedType()
    {
        return LeadImportType::class;
    }

    // public function configureOptions(OptionsResolver $resolver)
    // {
    //
    // }
    //
    // public function buildView(FormView $view, FormInterface $form, array $options)
    // {
    //
    // }

    /**
     * Add a custom 'object' type to write to a corresponding table for that new custom value.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
          'source',
          EntityType::class,
          array(
              'class'       => 'MauticContactSourceBundle:ContactSource',
              'empty_value' => 'Select A Source',
              'choice_label' => function ($source) {
                  return $source->getName();
              },
              'choice_value' => function (ContactSource $source = null) {
                  return $source ? $source->getId() : '';
              },
          )
        );

        $builder->add(
            'campaign',
            EntityType::class,
            array(
                'class'       => 'MauticCampaignBundle:Campaign',
                'empty_value' => 'Select A Campaign',
                'choice_label' => function ($campaign) {
                    return $campaign->getName();
                },
                'choice_value' => function (Campaign $campaign = null) {
                    return $campaign ? $campaign->getId() : '';
                },
            )
        );

    }
}