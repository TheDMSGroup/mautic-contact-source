<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Entity;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\LeadBundle\Entity\TimelineTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class EventRepository.
 */
class EventRepository extends CommonRepository
{
    use TimelineTrait;

    /**
     * Fetch the base event data from the database.
     *
     * @param                $contactSourceId
     * @param                $eventType
     * @param \DateTime|null $dateAdded
     *
     * @return array
     */
    public function getEvents($contactSourceId, $eventType = null, \DateTime $dateAdded = null)
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->from(MAUTIC_TABLE_PREFIX.'contactsource_events', 'c')
            ->select('c.*');

        $expr = $q->expr()->eq('c.contactsource_id', ':contactSource');
        $q->where($expr)
            ->setParameter('contactSource', (int) $contactSourceId);

        if ($dateAdded) {
            $expr->add(
                $q->expr()->gte('c.date_added', ':dateAdded')
            );
            $q->setParameter('dateAdded', $dateAdded);
        }

        if ($eventType) {
            $expr->add(
                $q->expr()->eq('c.type', ':type')
            );
            $q->setParameter('type', $eventType);
        }

        return $q->execute()->fetchAll();
    }

    /**
     * @param       $contactSourceId
     * @param array $options
     *
     * @return array
     */
    public function getEventsForTimeline($contactSourceId, array $options = [], $count = false)
    {
        $query = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->from(MAUTIC_TABLE_PREFIX.'contactsource_events', 'c')
            ->select('c.*');

        $query->where(
            $query->expr()->eq('c.contactsource_id', ':contactSourceId')
        )
            ->setParameter('contactSourceId', $contactSourceId);

        if (null != Request::createFromGlobals()->get('campaign') && !empty(Request::createFromGlobals()->get('campaign'))) {
            $campaignId = Request::createFromGlobals()->get('campaign');
        } elseif (isset($options['filters']['campaignId']) && !empty($options['filters']['campaignId'])) {
            $campaignId = $options['filters']['campaignId'];
        }
        if (isset($campaignId) && !empty($campaignId)) {
            $query->join(
                'c',
                'contactsource_stats', 's',
                'c.contactsource_id = s.contactsource_id AND c.contact_id = s.contact_id'
            )
            ->andWhere($query->expr()->eq('s.campaign_id', ':campaignId'))
            ->setParameter('campaignId', $campaignId);
        }

        if (isset($options['filters']['message']) && !empty($options['filters']['message'])) {
            $query->andWhere('c.message LIKE :message')
                ->setParameter('message', '%'.trim($options['filters']['message']).'%');
        }
        if (isset($options['filters']['contact_id']) && !empty($options['filters']['contact_id'])) {
            $query->andWhere('c.contact_id = :contact')
                ->setParameter('contact', trim($options['filters']['contact_id']));
        }
        if (isset($options['filters']['type']) && !empty($options['filters']['type'])) {
            $query->andWhere('c.type = :type')
                ->setParameter('type', trim($options['filters']['type']));
        }

        if (isset($options['dateFrom'])) {
            $query->andWhere('c.date_added >= :dateFrom')
                ->setParameter(
                    'dateFrom',
                    $options['dateFrom']->setTimeZone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
                );
        }

        if (!empty($options['fromDate']) && !empty($options['toDate'])) {
            $query->andWhere('c.date_added BETWEEN FROM_UNIXTIME(:dateFrom) AND FROM_UNIXTIME(:dateTo)')
                ->setParameter('dateFrom', $options['fromDate']->getTimestamp())
                ->setParameter('dateTo', $options['toDate']->getTimestamp());
        } elseif (!empty($options['fromDate'])) {
            $query->andWhere($query->expr()->gte('c.date_added', 'FROM_UNIXTIME(:dateFrom)'))
                ->setParameter('dateFrom', $options['fromDate']->getTimestamp());
        } elseif (!empty($options['toDate'])) {
            $query->andWhere($query->expr()->lte('c.date_added', 'FROM_UNIXTIME(:dateTo)'))
                ->setParameter('dateTo', $options['toDate']->getTimestamp());
        }

        if (isset($options['order']) && !empty($options['order'])) {
            list($orderBy, $orderByDir) = $options['order'];
            $query->orderBy('c.'.$orderBy, $orderByDir);
        }

        if (!empty($options['limit'])) {
            $query->setMaxResults($options['limit']);
            if (!empty($options['start'])) {
                $query->setFirstResult($options['start']);
            }
        }

        $results = $query->execute()->fetchAll();

        if (!empty($options['paginated'])) {
            // Get a total count along with results
            $query->resetQueryParts(['select', 'orderBy'])
                ->setFirstResult(null)
                ->setMaxResults(null)
                ->select('count(*)');
            // unjoin if possible for perf reasons
            if ($count && (!isset($campaignId) || empty($campaignId))) {
                $query->resetQueryParts(['join']);
            }

            $total = $query->execute()->fetchColumn();

            return [
                'total'   => $total,
                'results' => $results,
            ];
        }

        return $results;
    }

    /**
     * @param       $contactSourceId
     * @param int   $contactId
     * @param array $options
     *
     * @return array
     */
    public function getEventsForTimelineExport($contactSourceId, array $options = [], $count)
    {
        $query = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->from(MAUTIC_TABLE_PREFIX.'contactsource_events', 'c');
        if ($count) {
            $query->select('COUNT(c.id) as count');
        } else {
            $query->select('c.id, c.type, c.date_added, c.message, c.contact_id, c.logs');
        }

        $query->where(
            $query->expr()->eq('c.contactsource_id', ':contactSourceId')
        )
            ->setParameter('contactSourceId', $contactSourceId);

        if (!empty($options['dateFrom']) && !empty($options['dateTo'])) {
            $query->andWhere('c.date_added BETWEEN FROM_UNIXTIME(:dateFrom) AND FROM_UNIXTIME(:dateTo)')
                ->setParameter('dateFrom', $options['dateFrom']->setTime(00, 00, 00)->getTimestamp())
                ->setParameter('dateTo', $options['dateTo']->setTime(23, 59, 59)->getTimestamp());
        } elseif (!empty($options['dateFrom'])) {
            $query->andWhere($query->expr()->gte('c.date_added', 'FROM_UNIXTIME(:dateFrom)'))
                ->setParameter('dateFrom', $options['dateFrom']->setTime(00, 00, 00)->getTimestamp());
        } elseif (!empty($options['dateTo'])) {
            $query->andWhere($query->expr()->lte('c.date_added', 'FROM_UNIXTIME(:dateTo)'))
                ->setParameter('dateTo', $options['dateTo']->setTime(23, 59, 59)->getTimestamp());
        }

        if (isset($options['message']) && !empty($options['message'])) {
            $query->andWhere('c.message LIKE :message')
                ->setParameter('message', '%'.trim($options['message']).'%');
        }

        if (isset($options['contact_id']) && !empty($options['contact_id'])) {
            $query->andWhere('c.contact_id = :contact')
                ->setParameter('contact', trim($options['contact_id']));
        }

        if (isset($options['type']) && !empty($options['type'])) {
            $query->andWhere('c.type = :type')
                ->setParameter('type', trim($options['type']));
        }

        if (isset($options['campaignId']) && !empty($options['campaignId'])) {
            $query->join(
                'c',
                'contactsource_stats', 's',
                'c.contactsource_id = s.contactsource_id AND c.contact_id = s.contact_id'
            )
                ->andWhere($query->expr()->eq('s.campaign_id', ':campaignId'))
                ->setParameter('campaignId', $options['campaignId']);
        }

        $query->orderBy('c.date_added', 'DESC');

        if (!empty($options['limit'])) {
            $query->setMaxResults($options['limit']);
            if (!empty($options['start'])) {
                $query->andWhere('c.id > :offset')
                    ->setParameter('offset', $options['start']);
            }
        }

        $results = $query->execute()->fetchAll();

        return $results;
    }

    /**
     * Get a list of entities.
     *
     * @param array $args
     *
     * @return Paginator
     */
    public function getEntities(array $args = [])
    {
        $alias = $this->getTableAlias();

        $q = $this->_em
            ->createQueryBuilder()
            ->select($alias)
            ->from('MauticContactSourceBundle:Event', $alias, $alias.'.id');

        $args['qb'] = $q;

        return parent::getEntities($args);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getTableAlias()
    {
        return 'c';
    }

    /**
     * @return array
     */
    public function getSearchCommands()
    {
        return $this->getStandardSearchCommands();
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder|\Doctrine\DBAL\Query\QueryBuilder $q
     * @param                                                              $filter
     *
     * @return array
     */
    protected function addCatchAllWhereClause($q, $filter)
    {
        return $this->addStandardCatchAllWhereClause(
            $q,
            $filter,
            ['c.type', 'c.logs']
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder|\Doctrine\DBAL\Query\QueryBuilder $q
     * @param                                                              $filter
     *
     * @return array
     */
    protected function addSearchCommandWhereClause($q, $filter)
    {
        return $this->addStandardSearchCommandWhereClause($q, $filter);
    }

    /**
     * @return string
     */
    protected function getDefaultOrder()
    {
        return [
            [$this->getTableAlias().'.addedDate', 'ASC'],
        ];
    }
}
