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
use Mautic\LeadBundle\Form\Type\LeadImportFieldType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

class LeadImportFieldExtension extends AbstractTypeExtension
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
        return LeadImportFieldType::class;
    }

    /**
     * Add a custom 'object' type to write to a corresponding table for that new custom value.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $specialFields = [
            'dateAdded'      => 'mautic.lead.import.label.dateAdded',
            'createdByUser'  => 'mautic.lead.import.label.createdByUser',
            'dateModified'   => 'mautic.lead.import.label.dateModified',
            'modifiedByUser' => 'mautic.lead.import.label.modifiedByUser',
            'lastActive'     => 'mautic.lead.import.label.lastActive',
            'dateIdentified' => 'mautic.lead.import.label.dateIdentified',
            'ip'             => 'mautic.lead.import.label.ip',
            'points'         => 'mautic.lead.import.label.points',
            'stage'          => 'mautic.lead.import.label.stage',
            'doNotEmail'     => 'mautic.lead.import.label.doNotEmail',
            'ownerusername'  => 'mautic.lead.import.label.ownerusername',
        ];

        $utmFields = [
            'utm_campaign' => 'mautic.lead.field.utmcampaign',
            'utm_content'  => 'mautic.lead.field.utmcontent',
            'utm_medium'   => 'mautic.lead.field.utmmedium',
            'utm_term'     => 'mautic.lead.field.utmterm',
            'utm_source'   => 'UTM Source (can be overridden by Source)',
        ];

        $importChoiceFields = [
            'mautic.contactsource.choice.utmtags' => $utmFields,
            'mautic.lead.contact'                 => $options['lead_fields'],
            'mautic.lead.company'                 => $options['company_fields'],
            'mautic.lead.special_fields'          => $specialFields,
        ];

        if ('lead' !== $options['object']) {
            unset($importChoiceFields['mautic.lead.contact']);
        }

        foreach ($options['import_fields'] as $field => $label) {
            $builder->add(
                $field,
                'choice',
                [
                    'choices'    => $importChoiceFields,
                    'label'      => $label,
                    'required'   => false,
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => ['class' => 'form-control'],
                    'data'       => $this->getDefaultValue($field, $options['import_fields']),
                ]
            );
        }
    }

    /**
     * @param string $fieldName
     * @param array  $importFields
     *
     * @return string
     */
    public function getDefaultValue($fieldName, array $importFields)
    {
        if (isset($importFields[$fieldName])) {
            return $importFields[$fieldName];
        }

        return null;
    }
}
