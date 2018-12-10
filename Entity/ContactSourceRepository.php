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
 * Class ContactSourceRepository.
 */
class ContactSourceRepository extends CommonRepository
{
    /**
     * Get a list of entities.
     *
     * @param array $args
     *
     * @return \Doctrine\ORM\Tools\Pagination\Paginator
     */
    public function getEntities(array $args = [])
    {
        $alias = $this->getTableAlias();

        $q = $this->_em
            ->createQueryBuilder()
            ->select($alias)
            ->from('MauticContactSourceBundle:ContactSource', $alias, $alias.'.id');

        if (empty($args['iterator_mode'])) {
            $q->leftJoin($alias.'.category', 'c');
        }

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
        return 'f';
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
            ['f.name', 'f.description', 'f.descriptionPublic', 'f.token', 'f.utmSource']
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
     * @return array
     */
    public function getSearchCommands()
    {
        return $this->getStandardSearchCommands();
    }

    /**
     * @return array
     */
    protected function getDefaultOrder()
    {
        return [
            [$this->getTableAlias().'.name', 'ASC'],
        ];
    }

    /**
     * @return array
     */
    public function getContactSourceList($currentId)
    {
        $q = $this->createQueryBuilder('f');
        $q->select('partial f.{id, name, description}')->orderBy('f.name');

        return $q->getQuery()->getArrayResult();
    }

    /**
     * @param $campaignId
     *
     * @return mixed
     */
    public function getSourcesByCampaign($campaignId)
    {
        // WHERE campaign_settings REGEXP '\"campaignId\":\s*\"' . campaing_id . '\",';
        //       $where = 'l.'.$field.' REGEXP  :value';

        $q = $this->_em->getConnection()->createQueryBuilder()
            ->select('cs.*')
            ->from(MAUTIC_TABLE_PREFIX.'contactsource', 'cs');

        $where = 'REPLACE(cs.campaign_settings, " ", "") REGEXP  :regex';
        $q->where(
                $q->expr()->eq('cs.is_published', true),
                $q->expr()->andX($where)
        );

        $q->setParameter('regex', '"campaignId\":"'.$campaignId.'"');

        $results = $q->execute()->fetchAll();

        return $results;
    }

    /**
     * @return int
     */
    public function getDefaultUTMSource()
    {
        $q = $this->_em->getConnection()->createQueryBuilder()->from(MAUTIC_TABLE_PREFIX.'contactsource', 'cs');
        $q->select('cs.utm_source');
        $q->where('cs.utm_source REGEXP :regexp')->setParameter('regexp', '^[0-9]*$');
        $q->orderBy('cs.utm_source', 'DESC')->setMaxResults(1);

        $result           = $q->execute()->fetch();
        $defaultUtmSource = (int) $result['utm_source'] + 1;

        return $defaultUtmSource;
    }
}
