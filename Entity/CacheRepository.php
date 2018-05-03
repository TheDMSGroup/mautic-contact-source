<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\CoreBundle\Helper\PhoneNumberHelper;
use Mautic\LeadBundle\Entity\Lead as Contact;

/**
 * Class CacheRepository.
 */
class CacheRepository extends CommonRepository
{
    const MATCHING_ADDRESS  = 16;

    const MATCHING_EMAIL    = 2;

    const MATCHING_EXPLICIT = 1;

    const MATCHING_MOBILE   = 8;

    const MATCHING_PHONE    = 4;

    const SCOPE_CAMPAIGN    = 1;

    const SCOPE_CATEGORY    = 2;

    const SCOPE_UTM_SOURCE  = 4;

    /** @var PhoneNumberHelper */
    protected $phoneHelper;

    /** @var \Symfony\Component\DependencyInjection\Container */
    protected $container;

    /**
     * Evaluate limits (aka caps/budgets) for this Source.
     *
     * @param ContactSource $contactSource
     * @param array         $rules
     * @param int           $campaignId
     * @param null          $timezone
     * @param bool          $break
     * @param bool          $name
     *
     * @return array
     *
     * @throws \Exception
     */
    public function findLimits(
        ContactSource $contactSource,
        $rules = [],
        $campaignId = 0,
        $timezone = null,
        $break = true,
        $name = false
    ) {
        $results = [];
        foreach ($rules as $rule) {
            $filters  = [];
            $andx     = [];
            $value    = $rule['value'];
            $scope    = $rule['scope'];
            $duration = $rule['duration'];
            $quantity = $rule['quantity'];

            // Scope Campaign
            if ($scope & self::SCOPE_CAMPAIGN) {
                if ($campaignId) {
                    $andx['campaign_id'] = $campaignId;
                }
            }

            // Scope Category
            if ($scope & self::SCOPE_CATEGORY) {
                $category = intval($value);
                if ($category) {
                    $andx['category_id'] = $category;
                }
            }

            // Scope UTM Source
            if ($scope & self::SCOPE_UTM_SOURCE) {
                $utmSource = trim($value);
                if (!empty($utmSource)) {
                    $andx['utm_source'] = $utmSource;
                }
            }

            // Always add the contactsource.
            $andx['contactsource_id'] = $contactSource->getId();

            // Match duration (always, including campaign scope)
            $filters[] = [
                'andx'       => $andx,
                'date_added' => $this->oldestDateAdded($duration, $timezone),
            ];

            // Run the query to get the count.
            $count     = $this->applyFilters($filters, true);
            $hit       = $count >= $quantity;
            $percent   = 100 / $quantity * $count;
            $percent   = round($percent > 100 ? 100 : $percent, 2);
            $results[] = [
                'logCount'   => $count,
                'hit'        => $hit,
                'percent'    => $percent,
                'yesPercent' => $percent,
                'noPercent'  => 0,
                'rule'       => $rule,
                'name'       => $name ? $this->translateRule($rule) : '',
                'eventType'  => 'action',
            ];
            if ($hit && $break) {
                // Break at the first limit found (for fast assessment during ingestion).
                break;
            }
        }

        return $results;
    }

    /**
     * Support non-rolling durations when P is not prefixing.
     *
     * @param      $duration
     * @param null $timezone
     *
     * @return string
     *
     * @throws \Exception
     */
    public function oldestDateAdded($duration, $timezone = null)
    {
        if (0 === strpos($duration, 'P')) {
            // Standard rolling interval.
            $oldest = new \DateTime();
        } else {
            // Non-rolling interval, go to previous interval segment.
            // Will only work for simple (singular) intervals.
            if (!$timezone) {
                $timezone = date_default_timezone_get();
            }
            $timezone = new \DateTimeZone($timezone);
            switch (strtoupper(substr($duration, -1))) {
                case 'Y':
                    $oldest = new \DateTime('next year jan 1 midnight', $timezone);
                    break;
                case 'M':
                    $oldest = new \DateTime('first day of next month midnight', $timezone);
                    break;
                case 'W':
                    $oldest = new \DateTime('sunday next week midnight', $timezone);
                    break;
                case 'D':
                    $oldest = new \DateTime('tomorrow midnight', $timezone);
                    break;
                default:
                    $oldest = new \DateTime();
            }
            // Add P so that we can now use standard interval
            $duration = 'P'.$duration;
        }
        try {
            $interval = new \DateInterval($duration);
        } catch (\Exception $e) {
            // Default to monthly if the interval is faulty.
            $interval = new \DateInterval('P1M');
        }
        $oldest->sub($interval);
        $oldest->setTimezone(new \DateTimeZone('UTC'));

        return $oldest->format('Y-m-d H:i:s');
    }

    /**
     * @param array $filters
     * @param bool  $returnCount
     *
     * @return mixed|null
     */
    private function applyFilters($filters = [], $returnCount = false)
    {
        $result = null;
        // Convert our filters into a query.
        if ($filters) {
            $alias = $this->getTableAlias();
            $query = $this->getEntityManager()->getConnection()->createQueryBuilder();
            if ($returnCount) {
                $query->select('COUNT(*)');
            } else {
                $query->select('*');
                $query->setMaxResults(1);
            }
            $query->from(MAUTIC_TABLE_PREFIX.$this->getTableName(), $alias);

            foreach ($filters as $k => $set) {
                // Expect orx, anx, or neither.
                if (isset($set['orx'])) {
                    if (count($set['orx'])) {
                        $expr = $query->expr()->orX();
                    }
                    $properties = $set['orx'];
                } elseif (isset($set['andx'])) {
                    if (count($set['andx'])) {
                        $expr = $query->expr()->andX();
                    }
                    $properties = $set['andx'];
                } else {
                    $expr       = $query->expr();
                    $properties = $set;
                }
                if (isset($expr)) {
                    foreach ($properties as $property => $value) {
                        if (is_array($value)) {
                            $expr->add(
                                $query->expr()->in($alias.'.'.$property, $value)
                            );
                        } else {
                            $expr->add(
                                $query->expr()->eq($alias.'.'.$property, ':'.$property.$k)
                            );
                            $query->setParameter($property.$k, $value);
                        }
                    }
                }
                if (isset($set['date_added'])) {
                    $query->add(
                        'where',
                        $query->expr()->andX(
                            $query->expr()->gte($alias.'.date_added', ':dateAdded'.$k),
                            (isset($expr) ? $expr : null)
                        )
                    );
                    $query->setParameter('dateAdded'.$k, $set['date_added']);
                }
                $result = $query->execute()->fetch();
                if ($returnCount) {
                    $result = intval(reset($result));
                }
            }
        }

        return $result;
    }

    /**
     * @param        $rule
     * @param string $mode Must be 'limit', 'duplicate', or 'exclusive'
     *
     * @return mixed
     */
    public function translateRule($rule, $mode = 'limit')
    {
        if (!$this->container) {
            return null;
        }
        $translator = $this->container->get('translator');

        $quantity = isset($rule['quantity']) ? $rule['quantity'] : 0;
        $value    = isset($rule['value']) ? $rule['value'] : null;

        // Generate scope string.
        $scope       = isset($rule['scope']) ? $rule['scope'] : 0;
        $scopeString = '';
        if ($scope) {
            $scopes       = [
                self::SCOPE_CAMPAIGN,
                self::SCOPE_CATEGORY,
                self::SCOPE_UTM_SOURCE,
            ];
            $scopeStrings = [];
            foreach ($scopes as $scopeBitwise) {
                if ($scope & $scopeBitwise) {
                    $scopeStrings[$scopeBitwise] = $translator->trans(
                        'mautic.contactsource.rule.scope.'.$scopeBitwise
                    );
                }
            }
            $scopeString = implode(' & ', $scopeStrings);
        }

        // Generate matching string.
        $matching       = isset($rule['matching']) ? $rule['matching'] : 0;
        $matchingString = '';
        if ($matching) {
            $matches         = [
                self::MATCHING_ADDRESS,
                self::MATCHING_EMAIL,
                self::MATCHING_EXPLICIT,
                self::MATCHING_MOBILE,
                self::MATCHING_PHONE,
            ];
            $matchingStrings = [];
            foreach ($matches as $matchingBitwise) {
                if ($matching & $matchingBitwise) {
                    $matchingStrings[$matchingBitwise] = $translator->trans(
                        'mautic.contactsource.rule.matching.'.self::SCOPE_CAMPAIGN
                    );
                }
            }
            $matchingString = implode(' & ', $matchingStrings);
            if ($value) {
                $matchingString .= $translator->trans(
                    'mautic.contactsource.rule.value'
                );
            }
        }

        // Generate duration string.
        $duration       = isset($rule['duration']) ? $rule['duration'] : null;
        $durationString = '';
        if ($duration) {
            $durationString = $translator->trans('mautic.contactsource.rule.duration.'.$duration);
            if ($durationString == 'mautic.contactsource.rule.duration.'.$duration) {
                $durationString = $duration;
            }
        }

        // Combine the string and token values.
        $result = $translator->trans('mautic.contactsource.rule.'.$mode);

        return str_replace(
            ['{{scope}}', '{{matching}}', '{{value}}', '{{quantity}}', '{{duration}}'],
            [$scopeString, $matchingString, $value, $quantity, $durationString],
            $result
        );
    }

    /**
     * Given a matching pattern and a contact, discern if there is a match in the cache.
     *
     * @param Contact       $contact
     * @param ContactSource $contactSource
     * @param array         $rules
     * @param string        $timezone
     *
     * @return mixed|null
     *
     * @throws \Exception
     */
    public function findDuplicate(
        Contact $contact,
        ContactSource $contactSource,
        $rules = [],
        $timezone = null
    ) {
        // Generate our filters based on the rules provided.
        $filters = [];
        foreach ($rules as $rule) {
            $orx      = [];
            $matching = $rule['matching'];
            $scope    = $rule['scope'];
            $duration = $rule['duration'];

            // Match explicit
            if ($matching & self::MATCHING_EXPLICIT) {
                $orx['contact_id'] = (int) $contact->getId();
            }

            // Match email
            if ($matching & self::MATCHING_EMAIL) {
                $email = trim($contact->getEmail());
                if ($email) {
                    $orx['email'] = $email;
                }
            }

            // Match phone
            if ($matching & self::MATCHING_PHONE) {
                $phone = $this->phoneValidate($contact->getPhone());
                if (!empty($phone)) {
                    $orx['phone'] = $phone;
                }
            }

            // Match mobile
            if ($matching & self::MATCHING_MOBILE) {
                $mobile = $this->phoneValidate($contact->getMobile());
                if (!empty($mobile)) {
                    $orx['mobile'] = $mobile;
                }
            }

            // Match address
            if ($matching & self::MATCHING_ADDRESS) {
                $address1 = trim(ucwords($contact->getAddress1()));
                if (!empty($address1)) {
                    $city    = trim(ucwords($contact->getCity()));
                    $zipcode = trim(ucwords($contact->getZipcode()));

                    // Only support this level of matching if we have enough for a valid address.
                    if (!empty($zipcode) || !empty($city)) {
                        $orx['address1'] = $address1;

                        $address2 = trim(ucwords($contact->getAddress2()));
                        if (!empty($address2)) {
                            $orx['address2'] = $address2;
                        }

                        if (!empty($city)) {
                            $orx['city'] = $city;
                        }

                        $state = trim(ucwords($contact->getState()));
                        if (!empty($state)) {
                            $orx['state'] = $state;
                        }

                        if (!empty($zipcode)) {
                            $orx['zipcode'] = $zipcode;
                        }

                        $country = trim(ucwords($contact->getCountry()));
                        if (!empty($country)) {
                            $orx['country'] = $country;
                        }
                    }
                }
            }

            // Scope UTM Source
            if ($scope & self::SCOPE_UTM_SOURCE && $this->container) {
                // get the original / first utm source code for contact
                $utmHelper = $this->container->get('mautic.contactsource.helper.utmsource');
                $utmSource = $utmHelper->getFirstUtmSource($contact);
                if (!empty($utmSource)) {
                    $orx['utm_source'] = $utmSource;
                }
            }

            // Scope Category
            if ($scope & self::SCOPE_CATEGORY) {
                $category = $contactSource->getCategory();
                if ($category) {
                    $category = $category->getId();
                    if (!empty($category)) {
                        $orx['category_id'] = $category;
                    }
                }
            }

            if ($orx) {
                // Match duration (always), once all other aspects of the query are ready.
                $filters[] = [
                    'orx'              => $orx,
                    'date_added'       => $this->oldestDateAdded($duration, $timezone),
                    'contactsource_id' => $contactSource->getId(),
                ];
            }
        }

        return $this->applyFilters($filters);
    }

    /**
     * @param $phone
     *
     * @return string
     *
     * @todo - dedupe this method.
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
     * @param \Symfony\Component\DependencyInjection\Container $container
     *
     * @return $this
     */
    public function setContainer($container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Delete all Cache entities that are no longer needed for duplication/exclusivity/limit checks.
     *
     * @return mixed
     */
    public function deleteExpired()
    {
        // 32 days old, since the maximum limiter is 1m/30d.
        $oldest = date('Y-m-d H:i:s', time() - (32 * 24 * 60 * 60));
        $q      = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $q->delete(MAUTIC_TABLE_PREFIX.$this->getTableName());
        $q->where(
            $q->expr()->lt('date_added', ':oldest')
        );
        $q->setParameter('oldest', $oldest);
        $q->execute();
    }

}
