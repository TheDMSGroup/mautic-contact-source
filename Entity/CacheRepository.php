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
    /**
     * Bitwise operators for $matching.
     */
    const MATCHING_EXPLICIT = 1;

    const MATCHING_EMAIL    = 2;

    const MATCHING_PHONE    = 4;

    const MATCHING_MOBILE   = 8;

    const MATCHING_ADDRESS  = 16;

    /**
     * Bitwise operators for $scope.
     */
    const SCOPE_GLOBAL   = 1;

    const SCOPE_CATEGORY = 2;

    // For limits only:
    const SCOPE_UTM_SOURCE = 3;

    /** @var PhoneNumberHelper */
    protected $phoneHelper;

    /**
     * Given a matching pattern and a contact, discern if there is a match in the cache.
     * Used for exclusivity and duplicate checking.
     *
     * @param Contact       $contact
     * @param ContactSource $contactSource
     * @param array         $rules
     *
     * @return bool|mixed
     *
     * @throws \Exception
     */
    public function findDuplicate(
        Contact $contact,
        ContactSource $contactSource,
        $rules = []
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
            if ($scope & self::SCOPE_UTM_SOURCE) {
                // get the original / first utm source code for contact
                $utmHelper = $this->factory->get('mautic.contactsource.helper.utmsource');
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
                // Match duration (always), once all other aspecs of the query are ready.
                $oldest = new \DateTime();
                $oldest->sub(new \DateInterval($duration));
                $filters[] = [
                    'orx'        => $orx,
                    'date_added' => $oldest->format('Y-m-d H:i:s'),
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
     * @param array $filters
     *
     * @return mixed|null
     */
    private function applyFilters($filters = [])
    {
        $result = null;
        // Convert our filters into a query.
        if ($filters) {
            $alias = $this->getTableAlias();
            $query = $this->getEntityManager()->getConnection()->createQueryBuilder();
            $query
                ->select('*')
                ->setMaxResults(1)
                ->from(MAUTIC_TABLE_PREFIX.'contactsource_cache', $alias);

            foreach ($filters as $k => $set) {
                // Expect orx, anx, or neither.
                if (isset($set['orx'])) {
                    $expr       = $query->expr()->orX();
                    $properties = $set['orx'];
                } elseif (isset($set['andx'])) {
                    $expr       = $query->expr()->andX();
                    $properties = $set['andx'];
                } else {
                    $expr       = $query->expr();
                    $properties = $set;
                }
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
                if (isset($set['date_added'])) {
                    $query->add(
                        'where',
                        $query->expr()->andX(
                            $query->expr()->gte($alias.'.date_added', ':dateAdded'.$k),
                            $expr
                        )
                    );
                    $query->setParameter('dateAdded'.$k, $set['date_added']);
                }
            }

            $result = $query->execute()->fetch();
        }

        return $result;
    }
}
