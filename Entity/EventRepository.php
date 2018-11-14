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
     * @param int   $contactId
     * @param array $options
     *
     * @return array
     */
    public function getEventsForTimeline($contactSourceId, $contactId = null, array $options = [])
    {
        $query = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->from(MAUTIC_TABLE_PREFIX.'contactsource_events', 'c')
            ->select('c.*');

        $query->where(
            $query->expr()->eq('c.contactsource_id', ':contactSourceId')
        )
            ->setParameter('contactSourceId', $contactSourceId);

        if ($contactId) {
            $query->andWhere('c.contact_id = :contact_id');
            $query->setParameter('contact_id', $contactId);
        }

        $campaignId = Request::createFromGlobals()->get('campaign');
        if ($campaignId) {
            $query->join(
                'c',
                'contactsource_stats', 's',
                'c.contactsource_id = s.contactsource_id AND c.contact_id = s.contact_id'
            )
            ->andWhere($query->expr()->eq('s.campaign_id', ':campaignId'))
            ->setParameter('campaignId', $campaignId);
        }

        if (isset($options['search']) && $options['search']) {
            if (is_numeric($options['search']) && !$contactId) {
                $expr = $query->expr()->orX(
                    $query->expr()->eq('c.contact_id', (int) $options['search'])
                );
            } else {
                $expr = $query->expr()->orX(
                    $query->expr()->eq('c.type', ':search'),
                    $query->expr()->like('c.message', $query->expr()->literal('%'.$options['search'].'%'))
                );
            }
            $query->andWhere($expr);
            $query->setParameter('search', $options['search']);
        }

        if (!empty($options['fromDate']) && !empty($options['toDate'])) {
            $query->andWhere('c.date_added BETWEEN :dateFrom AND :dateTo')
                ->setParameter('dateFrom', $options['fromDate']->format('Y-m-d H:i:s'))
                ->setParameter('dateTo', $options['toDate']->format('Y-m-d 23:59:59'));
        } elseif (!empty($options['fromDate'])) {
            $query->andWhere($query->expr()->gte('c.date_added', ':dateFrom'))
                ->setParameter('dateFrom', $options['fromDate']->format('Y-m-d H:i:s'));
        } elseif (!empty($options['toDate'])) {
            $query->andWhere($query->expr()->lte('c.date_added', ':dateTo'))
                ->setParameter('dateTo', $options['toDate']->format('Y-m-d 23:59:59'));
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

            $total = $query->execute()->fetchColumn();

            return [
                'total'   => $total,
                'results' => $results,
            ];
        }

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
