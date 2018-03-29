<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Model;

use FOS\RestBundle\Util\Codes;
use Mautic\CoreBundle\Helper\PhoneNumberHelper;
use Mautic\CoreBundle\Model\AbstractCommonModel;
use Mautic\LeadBundle\Entity\Lead as Contact;
use MauticPlugin\MauticContactSourceBundle\Entity\Cache as CacheEntity;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use MauticPlugin\MauticContactSourceBundle\Entity\Stat;
use MauticPlugin\MauticContactSourceBundle\Exception\ContactSourceException;
use MauticPlugin\MauticContactSourceBundle\Helper\JSONHelper;

/**
 * Class Cache.
 */
class Cache extends AbstractCommonModel
{
    /** @var ContactSource $contactSource */
    protected $contactSource;

    /** @var Contact */
    protected $contact;

    /** @var PhoneNumberHelper */
    protected $phoneHelper;

    /** @var \Symfony\Component\DependencyInjection\Container */
    protected $container;

    /** @var string */
    protected $timezone;

    /**
     * Create all necessary cache entities for the given Contact and Contact Source.
     *
     * @param int $campaignId
     *
     * @throws \Exception
     */
    public function create($campaignId = 0)
    {
        $entities   = [];
        $entities[] = $this->createEntity($campaignId);
        if (count($entities)) {
            $this->getRepository()->saveEntities($entities);
        }
    }

    /**
     * Create a new cache entity with the existing Contact and contactSource.
     * Normalize the fields as much as possible to aid in exclusive/duplicate/limit correlation.
     *
     * @param int $campaignId
     *
     * @return CacheEntity
     *
     * @throws \Exception
     */
    private function createEntity($campaignId = 0)
    {
        $entity = new CacheEntity();
        $entity->setAddress1(trim(ucwords($this->contact->getAddress1())));
        $entity->setAddress2(trim(ucwords($this->contact->getAddress2())));
        $category = $this->contactSource->getCategory();
        if ($category) {
            $entity->setCategory($category->getId());
        }
        if ($campaignId) {
            $entity->setCampaign($campaignId);
        }
        $entity->setCity(trim(ucwords($this->contact->getCity())));
        $entity->setContact($this->contact->getId());
        $entity->setContactSource($this->contactSource->getId());
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
        // get the original / first utm source code for contact
        $utmHelper = $this->getContainer()->get('mautic.contactsource.helper.utmsource');
        $utmSource = $utmHelper->getFirstUtmSource($this->contact);
        if (!empty($utmSource)) {
            $entity->setUtmSource(trim($utmSource));
        }

        return $entity;
    }

    /**
     * @param $phone
     *
     * @return string
     */
    private function phoneValidate($phone)
    {
        $result = null;
        $phone  = trim($phone);
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
     * @return \Symfony\Component\DependencyInjection\Container
     */
    private function getContainer()
    {
        if (!$this->container) {
            $this->container = $this->dispatcher->getContainer();
        }

        return $this->container;
    }

    /**
     * @return \MauticPlugin\MauticContactSourceBundle\Entity\CacheRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository('MauticContactSourceBundle:Cache');
    }

    /**
     * Using the duplicate rules, evaluate if the current contact matches any entry in the cache.
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    public function evaluateDuplicate()
    {
        $duplicate = $this->getRepository()->findDuplicate(
            $this->contact,
            $this->contactSource,
            $this->getDuplicateRules(),
            $this->getTimezone()
        );
        if ($duplicate) {
            throw new ContactSourceException(
                'Rejecting duplicate Contact.',
                Codes::HTTP_CONFLICT,
                null,
                Stat::TYPE_DUPLICATE,
                false,
                $duplicate
            );
        }
    }

    /**
     * Given the Contact and Contact Source, get the rules used to evaluate duplicates.
     *
     * @throws \Exception
     */
    public function getDuplicateRules()
    {
        $jsonHelper = new JSONHelper();
        $duplicate  = $jsonHelper->decodeObject($this->contactSource->getDuplicate(), 'Duplicate');

        return $this->mergeRules($duplicate);
    }

    /**
     * Validate and merge the rules object (exclusivity/duplicate/limits).
     *
     * @param      $rules
     * @param bool $requireMatching
     *
     * @return array
     */
    private function mergeRules($rules, $requireMatching = true)
    {
        $newRules = [];
        if (isset($rules->rules) && is_array($rules->rules)) {
            foreach ($rules->rules as $rule) {
                // Exclusivity and Duplicates have matching, Limits may not.
                if (
                    (!$requireMatching || !empty($rule->matching))
                    && !empty($rule->scope)
                    && !empty($rule->duration)
                ) {
                    $duration = $rule->duration;
                    $scope    = intval($rule->scope);
                    $value    = isset($rule->value) ? strval($rule->value) : '';
                    $key      = $duration.'-'.$scope.'-'.$value;
                    if (!isset($newRules[$key])) {
                        $newRules[$key] = [];
                        if (!empty($rule->matching)) {
                            $newRules[$key]['matching'] = intval($rule->matching);
                        }
                        $newRules[$key]['scope']    = $scope;
                        $newRules[$key]['duration'] = $duration;
                        $newRules[$key]['value']    = $value;
                    } elseif (!empty($rule->matching)) {
                        $newRules[$key]['matching'] += intval($rule->matching);
                    }
                    if (isset($rule->quantity)) {
                        if (!isset($newRules[$key]['quantity'])) {
                            $newRules[$key]['quantity'] = intval($rule->quantity);
                        } else {
                            $newRules[$key]['quantity'] = min($newRules[$key]['quantity'], intval($rule->quantity));
                        }
                    }
                }
            }
        }
        krsort($newRules);

        return $newRules;
    }

    /**
     * Get the global timezone setting.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    private function getTimezone()
    {
        if (!$this->timezone) {
            $this->timezone = $this->getContainer()->get('mautic.helper.core_parameters')->getParameter(
                'default_timezone'
            );
        }

        return $this->timezone;
    }

    /**
     * Using the duplicate rules, evaluate if the current contact matches any entry in the cache.
     *
     * @param array $limitRules
     * @param int   $campaignId
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    public function evaluateLimits($limitRules = [], $campaignId = 0)
    {
        $limitRules = $this->mergeRules($limitRules, false);
        $limits     = $this->getRepository()->findLimit(
            $this->contactSource,
            $limitRules,
            $campaignId,
            $this->getTimezone()
        );
        if ($limits) {
            throw new ContactSourceException(
                'A cap has been exceeded.',
                Codes::HTTP_TOO_MANY_REQUESTS,
                null,
                Stat::TYPE_LIMITS,
                false,
                $limits
            );
        }
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
     * @return ContactSource
     */
    public function getContactSource()
    {
        return $this->contactSource;
    }

    /**
     * @param ContactSource $contactSource
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setContactSource(ContactSource $contactSource)
    {
        $this->contactSource = $contactSource;

        return $this;
    }
}
