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

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * Class StatRepository.
 */
class StatRepository extends CommonRepository
{
    /**
     * @param int $id contact ID to search by
     */
    public function getStatsByContactId($id)
    {
        $q    = $this->createQueryBuilder('s');
        $expr = $q->expr()->andX(
            $q->expr()->eq('IDENTITY(s.contactsource)', (int) $id),
            $q->expr()->eq('s.type', ':type')
        );

        return $q->getQuery()->getArrayResult();
    }

    /**
     * Fetch the base stat data from the database.
     *
     * @param int  $id
     * @param      $type
     * @param null $fromDate
     *
     * @return mixed
     */
    public function getStats($id, $type, $fromDate = null)
    {
        $q = $this->createQueryBuilder('s');

        $expr = $q->expr()->andX(
            $q->expr()->eq('IDENTITY(s.contactsource)', (int) $id),
            $q->expr()->eq('s.type', ':type')
        );

        if ($fromDate) {
            $expr->add(
                $q->expr()->gte('s.dateAdded', ':fromDate')
            );
            $q->setParameter('fromDate', $fromDate);
        }

        $q->where($expr)
            ->setParameter('type', $type);

        return $q->getQuery()->getArrayResult();
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function getCampaignBudgetsData($params = [])
    {
        $results    =[];
        $today      = $params['dateFrom']->format('Y-m-d 00:00:00');
        $monthStart = $params['dateFrom']->format('Y-m-01 00:00:00');
        $monthEnd   = $params['dateFrom']->format('Y-m-t 23:59:59');

        // get financials from ledger based on returned Lead list
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select("css.contactsource_id, SUM(css.type = 'limited') as daily")
        ->from(MAUTIC_TABLE_PREFIX.'contactsource_stats', 'css');

        // join Contact Source table to get source name
        $q->join('css', MAUTIC_TABLE_PREFIX.'contactsource', 'cs', 'css.contactsource_id = cs.id');

        $q->where(
            $q->expr()->andX(
                $q->expr()->gte('css.date_added', ':today'),
                $q->expr()->eq('css.campaign_id', ':campaign_id')
            )
        );

        $q->setParameter('today', $today);
        $q->setParameter('campaign_id', $params['campaignId']);

        $q->groupBy('css.contactsource_id');

        $q->orderBy('(cs.name)', 'ASC');

        $todayCount = $q->execute()->fetchAll();

        $q->resetQueryParts(['select', 'where'])
            ->select("css.contactsource_id, cs.name as source, SUM(css.type = 'limited') as mtd")
            ->where(
                $q->expr()->andX(
                    $q->expr()->gte('css.date_added', ':start'),
                    $q->expr()->lte('css.date_added', ':end'),
                    $q->expr()->eq('css.campaign_id', ':campaign_id')
                )
            );
        $q->setParameter('start', $monthStart);
        $q->setParameter('end', $monthEnd);
        $q->setParameter('campaign_id', $params['campaignId']);
        $mtdCount = $q->execute()->fetchAll();
        if (!empty($mtdCount)) {
            $daily = $this->idToKey($todayCount);
            $mtd   = $this->idToKey($mtdCount);

            foreach ($mtd as $row) {
                $dataRow           = [];
                $dataRow[]         = $row['source'];
                $dataRow[]         = '[Cap Name]';
                $dataRow[]         = isset($daily[$row['contactsource_id']]['daily']) ? $daily[$row['contactsource_id']]['daily'] : 0;
                $dataRow[]         = '0';
                $dataRow[]         = '0';
                $dataRow[]         = $row['mtd'];
                $dataRow[]         = '0';
                $dataRow[]         = '0';
                $results['rows'][] = $dataRow;
            }
        }

        return $results;
    }

    public function idToKey($arr)
    {
        $result = [];
        foreach ($arr as $item) {
            $result[$item['contactsource_id']] = $item;
        }

        return $result;
    }
}
