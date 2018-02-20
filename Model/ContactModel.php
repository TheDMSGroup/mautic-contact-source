<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Model;

use Mautic\LeadBundle\Model\LeadModel as OriginalContactModel;
use Mautic\LeadBundle\Entity\Lead as Contact;
use Mautic\LeadBundle\Entity\LeadEventLog as ContactEventLog;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Helper\DateTimeHelper;

/**
 * Class ContactModel
 *
 * This local copy of LeadModel is intended to do import/create operations,
 * while intentionally skipping heavy queries that would prevent a speedy ingestion.
 */
class ContactModel extends OriginalContactModel
{

    /**
     * This is a copy of the function found in Mautic\LeadBundle\Model\LeadModel
     * With some exclusions for the sake of performance.
     *
     * @param array $fields
     * @param array $data
     * @param null $owner
     * @param null $list
     * @param null $tags
     * @param bool $persist
     * @param ContactEventLog|null $eventLog
     * @return bool|Contact|null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public function import(
        $fields,
        $data,
        $owner = null,
        $list = null,
        $tags = null,
        $persist = false,
        ContactEventLog $eventLog = null
    ) {
        $fields = array_flip($fields);
        $fieldData = [];

        // Servers will not be able to set this on creation: Companies and their linkages.

        foreach ($fields as $leadField => $importField) {
            // Prevent overwriting existing data with empty data
            if (array_key_exists($importField, $data) && !is_null($data[$importField]) && $data[$importField] != '') {
                $fieldData[$leadField] = InputHelper::_($data[$importField], 'string');
            }
        }

        // These contacts are always going to be new.
        $lead = new Contact();
        $merged = false;

        if (!empty($fields['dateAdded']) && !empty($data[$fields['dateAdded']])) {
            $dateAdded = new DateTimeHelper($data[$fields['dateAdded']]);
            $lead->setDateAdded($dateAdded->getUtcDateTime());
        }
        unset($fieldData['dateAdded']);

        if (!empty($fields['dateModified']) && !empty($data[$fields['dateModified']])) {
            $dateModified = new DateTimeHelper($data[$fields['dateModified']]);
            $lead->setDateModified($dateModified->getUtcDateTime());
        }
        unset($fieldData['dateModified']);

        if (!empty($fields['lastActive']) && !empty($data[$fields['lastActive']])) {
            $lastActive = new DateTimeHelper($data[$fields['lastActive']]);
            $lead->setLastActive($lastActive->getUtcDateTime());
        }
        unset($fieldData['lastActive']);

        if (!empty($fields['dateIdentified']) && !empty($data[$fields['dateIdentified']])) {
            $dateIdentified = new DateTimeHelper($data[$fields['dateIdentified']]);
            $lead->setDateIdentified($dateIdentified->getUtcDateTime());
        }
        unset($fieldData['dateIdentified']);

        // Servers will not be able to set this on creation: createdByUser
        unset($fieldData['createdByUser']);

        // Servers will not be able to set this on creation: modifiedByUser
        unset($fieldData['modifiedByUser']);

        if (!empty($fields['ip']) && !empty($data[$fields['ip']])) {
            $addresses = explode(',', $data[$fields['ip']]);
            foreach ($addresses as $address) {
                $ipAddress = new IpAddress();
                $ipAddress->setIpAddress(trim($address));
                $lead->addIpAddress($ipAddress);
            }
        }
        unset($fieldData['ip']);

        // Servers will not be able to set this on creation: points

        // Servers will not be able to set this on creation: stage
        unset($fieldData['stage']);

        // Servers will not be able to set this on creation: doNotEmail
        unset($fieldData['doNotEmail']);

        // Servers will not be able to set this on creation: ownerusername
        unset($fieldData['ownerusername']);

        if ($owner !== null) {
            $lead->setOwner($this->em->getReference('MauticUserBundle:User', $owner));
        }

        if ($tags !== null) {
            $this->modifyTags($lead, $tags, null, false);
        }

        if (empty($this->leadFields)) {
            $this->leadFields = $this->leadFieldModel->getEntities(
                [
                    'filter' => [
                        'force' => [
                            [
                                'column' => 'f.isPublished',
                                'expr' => 'eq',
                                'value' => true,
                            ],
                            [
                                'column' => 'f.object',
                                'expr' => 'eq',
                                'value' => 'lead',
                            ],
                        ],
                    ],
                    'hydration_mode' => 'HYDRATE_ARRAY',
                ]
            );
        }

        $fieldErrors = [];

        foreach ($this->leadFields as $leadField) {
            if (isset($fieldData[$leadField['alias']])) {
                if ('NULL' === $fieldData[$leadField['alias']]) {
                    $fieldData[$leadField['alias']] = null;

                    continue;
                }

                try {
                    $this->cleanFields($fieldData, $leadField);
                } catch (\Exception $exception) {
                    $fieldErrors[] = $leadField['alias'].': '.$exception->getMessage();
                }

                if ('email' === $leadField['type'] && !empty($fieldData[$leadField['alias']])) {
                    try {
                        $this->emailValidator->validate($fieldData[$leadField['alias']], false);
                    } catch (\Exception $exception) {
                        $fieldErrors[] = $leadField['alias'].': '.$exception->getMessage();
                    }
                }

                // Skip if the value is in the CSV row
                continue;
            } elseif ($leadField['defaultValue']) {
                // Fill in the default value if any
                $fieldData[$leadField['alias']] = ('multiselect' === $leadField['type']) ? [$leadField['defaultValue']] : $leadField['defaultValue'];
            }
        }

        if ($fieldErrors) {
            $fieldErrors = implode("\n", $fieldErrors);

            throw new \Exception($fieldErrors);
        }

        // All clear
        foreach ($fieldData as $field => $value) {
            $lead->addUpdatedField($field, $value);
        }

        $lead->imported = true;

        if ($eventLog) {
            $action = $merged ? 'updated' : 'inserted';
            $eventLog->setAction($action);
            $lead->addEventLog($eventLog);
        }

        if ($persist) {
            $this->saveEntity($lead);

            if ($list !== null) {
                $this->addToLists($lead, [$list]);
            }

            // Servers will not be able to set this on creation: company
        } else {
            // Rather than return a boolean, we need the contact.
            return $lead;
        }

        return $merged;
    }
}
