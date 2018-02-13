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

use Mautic\CoreBundle\Model\AbstractCommonModel;
// use MauticPlugin\MauticContactServerBundle\Entity\CacheRepository;
use MauticPlugin\MauticContactServerBundle\Entity\CacheRepository;
use MauticPlugin\MauticContactServerBundle\Entity\ContactServer;
use Mautic\LeadBundle\Entity\Lead as Contact;
use MauticPlugin\MauticContactServerBundle\Entity\Cache as CacheEntity;
use MauticPlugin\MauticContactServerBundle\Helper\JSONHelper;
use Mautic\CoreBundle\Helper\PhoneNumberHelper;
use MauticPlugin\MauticContactServerBundle\Exception\ContactServerException;
use MauticPlugin\MauticContactServerBundle\Entity\Stat;

/**
 * Class Cache
 * @package MauticPlugin\MauticContactServerBundle\Model
 */
class Cache extends AbstractCommonModel
{

    /** @var ContactServer $contactServer */
    protected $contactServer;

    /** @var Contact */
    protected $contact;

    /** @var PhoneNumberHelper */
    protected $phoneHelper;

    /**
     * Create all necessary cache entities for the given Contact and Contact Server.
     * @throws \Exception
     */
    public function create()
    {
        $entities = [];
        $exclusive = $this->getExclusiveRules();
        if (count($exclusive)) {
            // Create an entry for *each* exclusivity rule as they will end up with different dates of exclusivity
            // expiration. Any of these entries will suffice for duplicate checking and limit checking.
            foreach ($exclusive as $rule) {
                if (!isset($entity)) {
                    $entity = $this->new();
                } else {
                    // No need to re-run all the getters and setters.
                    $entity = clone $entity;
                }
                // Each entry may have different exclusion expiration.
                $expireDate = new \DateTime();
                $expireDate->add(new \DateInterval($rule['duration']));
                $entity->setExclusiveExpireDate($expireDate);
                $entity->setExclusivePattern($rule['matching']);
                $entity->setExclusiveScope($rule['scope']);
                $entities[] = $entity;
            }
        } else {
            // A single entry will suffice for all duplicate checking and limit checking.
            $entities[] = $this->new();
        }
        if (count($entities)) {
            $this->getRepository()->saveEntities($entities);
        }
    }

    /**
     * Given the Contact and Contact Server, discern which exclusivity entries need to be cached.
     *
     * @throws \Exception
     */
    public function getExclusiveRules()
    {
        $jsonHelper = new JSONHelper();
        $exclusive = $jsonHelper->decodeObject($this->contactServer->getExclusive(), 'Exclusive');

        return $this->mergeRules($exclusive);
    }

    /**
     * Validate and merge the rules object (exclusivity/duplicate/limits)
     *
     * @param $rules
     * @return array
     */
    private function mergeRules($rules)
    {
        $newRules = [];
        if (isset($rules->rules) && is_array($rules->rules)) {
            foreach ($rules->rules as $rule) {
                if (
                    !empty($rule->matching)
                    && !empty($rule->scope)
                    && !empty($rule->duration)
                ) {
                    $duration = $rule->duration;
                    $scope = intval($rule->scope);
                    $key = $duration.'-'.$scope;
                    if (!isset($newRules[$key])) {
                        $newRules[$key] = [];
                        $newRules[$key]['matching'] = intval($rule->matching);
                        $newRules[$key]['scope'] = $scope;
                        $newRules[$key]['duration'] = $duration;
                    } else {
                        $newRules[$key]['matching'] += intval($rule->matching);
                    }
                }
            }
        }
        krsort($newRules);

        return $newRules;
    }

    /**
     * Create a new cache entity with the existing Contact and contactServer.
     * Normalize the fields as much as possible to aid in exclusive/duplicate/limit correlation.
     *
     * @return CacheEntity
     */
    private function new()
    {
        $entity = new CacheEntity();
        $entity->setAddress1(trim(ucwords($this->contact->getAddress1())));
        $entity->setAddress2(trim(ucwords($this->contact->getAddress2())));
        $category = $this->contactServer->getCategory();
        if ($category) {
            $entity->setCategory($category->getId());
        }
        $entity->setCity(trim(ucwords($this->contact->getCity())));
        $entity->setContact($this->contact->getId());
        $entity->setContactServer($this->contactServer->getId());
        $entity->setState(trim(ucwords($this->contact->getStage())));
        $entity->setCountry(trim(ucwords($this->contact->getCountry())));
        $entity->setZipcode(trim($this->contact->getZipcode()));
        $entity->setEmail(trim($this->contact->getEmail()));
        $phone = $this->phoneValidate($this->contact->getPhone());
        if (!empty($phone)) {
            $entity->setPhone($phone);
        }
        $mobile = $this->phoneValidate($this->contact->getMobile());
        if (!empty($mobile)) {
            $entity->setMobile($mobile);
        }
        $utmTags = $this->contact->getUtmTags();
        if ($utmTags) {
            $utmTags = $utmTags->toArray();
            if (isset($utmTags[0])) {
                $utmSource = $utmTags[0]->getUtmSource();
                if (!empty($utmSource)) {
                    $entity->setUtmSource(trim($utmSource));
                }
            }
        }

        return $entity;
    }

    /**
     * @param $phone
     * @return string
     */
    private function phoneValidate($phone)
    {
        $result = null;
        $phone = trim($phone);
        if (!empty($phone)) {
            if (!$this->phoneHelper) {
                $this->phoneHelper = new PhoneNumberHelper();
            }
            try {
                $phone = $this->phoneHelper->format($phone);
                if (!empty($phone)) {
                    $result = $phone;
                }
            } catch (\Exception $e) {
            }
        }

        return $result;
    }

    /**
     * @return \MauticPlugin\MauticContactServerBundle\Entity\CacheRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository('MauticContactServerBundle:Cache');
    }

    /**
     * Given a contact, evaluate exclusivity rules of all cache entries against it.
     *
     * @throws ContactServerException
     * @throws \Exception
     */
    public function evaluateExclusive()
    {
        $exclusive = $this->getRepository()->findExclusive(
            $this->contact,
            $this->contactServer
        );
        if ($exclusive) {
            throw new ContactServerException(
                'Skipping exclusive. A contact matching this has been accepted by a competing server: '.
                json_encode($exclusive),
                0,
                null,
                Stat::TYPE_EXCLUSIVE
            );
        }
    }

    /**
     * Using the duplicate rules, evaluate if the current contact matches any entry in the cache.
     *
     * @throws ContactServerException
     * @throws \Exception
     */
    public function evaluateDuplicate()
    {
        $duplicate = $this->getRepository()->findDuplicate(
            $this->contact,
            $this->contactServer,
            $this->getDuplicateRules()
        );
        if ($duplicate) {
            throw new ContactServerException(
                'Skipping duplicate. A contact matching this one was already accepted by this server: '.
                json_encode($duplicate),
                0,
                null,
                Stat::TYPE_DUPLICATE,
                false
            );
        }
    }

    /**
     * Given the Contact and Contact Server, get the rules used to evaluate duplicates.
     *
     * @throws \Exception
     */
    public function getDuplicateRules()
    {
        $jsonHelper = new JSONHelper();
        $duplicate = $jsonHelper->decodeObject($this->contactServer->getDuplicate(), 'Duplicate');

        return $this->mergeRules($duplicate);
    }

    /**
     * @return Contact
     */
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * @param Contact $contact
     *
     * @return $this
     */
    public function setContact(Contact $contact)
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * @return ContactServer
     */
    public function getContactServer()
    {
        return $this->contactServer;
    }

    /**
     * @param ContactServer $contactServer
     * @return $this
     * @throws \Exception
     */
    public function setContactServer(ContactServer $contactServer)
    {
        $this->contactServer = $contactServer;

        return $this;
    }

}