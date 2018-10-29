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
use Mautic\LeadBundle\Form\Type\LeadImportType;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;

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

    /**
     * Add a custom 'object' type to write to a corresponding table for that new custom value.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (isset($_GET['source'])) {
            $sourceEntity      = $this->em->getRepository('MauticContactSourceBundle:ContactSource')->find(
                $_GET['source']
            );
            $sourceEntityLabel = $sourceEntity->getName();
        } else {
            $sourceEntityLabel = 'Select A Source';
            $sourceEntity      = [];
        }

        if (isset($_GET['campaign'])) {
            $campaignEntity      = $this->em->getRepository('MauticCampaignBundle:Campaign')->find($_GET['campaign']);
            $campaignEntityLabel = $campaignEntity->getName();
        } else {
            $campaignEntityLabel = 'Select A Source';
            $campaignEntity      = [];
        }

        $builder->add(
            'source',
            EntityType::class,
            [
                'class'        => 'MauticContactSourceBundle:ContactSource',
                'data'         => $sourceEntity,
                'placeholder'  => $sourceEntityLabel,
                'choice_label' => function ($sourceEntity) {
                    return $sourceEntity->getName();
                },
            ]
        );

        $builder->get('source')
            ->addModelTransformer(
                new CallbackTransformer(
                    function ($source) {
                        // transform the ID back to an Object
                        return $source ? $this->em->getRepository('MauticContactSourceBundle:ContactSource')->find(
                            $source
                        ) : null;
                    },
                    function ($source) {
                        // transform the object to a ID
                        return $source ? $source->getID() : null;
                    }
                )
            );

        $builder->add(
            'campaign',
            EntityType::class,
            [
                'class'        => 'MauticCampaignBundle:Campaign',
                'data'         => $campaignEntity,
                'empty_value'  => $campaignEntityLabel,
                'choice_label' => function ($campaignEntity) {
                    return $campaignEntity->getName();
                },
            ]
        );

        $builder->get('campaign')
            ->addModelTransformer(
                new CallbackTransformer(
                    function ($campaign) {
                        // transform the ID back to an Object
                        return $campaign ? $this->em->getRepository('MauticCampaignBundle:Campaign')->find(
                            $campaign
                        ) : null;
                    },
                    function ($campaign) {
                        // transform the object to a ID
                        return $campaign ? $campaign->getID() : null;
                    }
                )
            );
    }
}
