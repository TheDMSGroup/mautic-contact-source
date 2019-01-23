<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactsourceBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\ReportEvents;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\LeadBundle\Report\FieldsBuilder;

/**
 * Class ReportSubscriber.
 */
class ReportSubscriber extends CommonSubscriber
{

    const CONTEXT_CONTACT_SOURCE_LEADCAMPAIGN_STATS = "contactsource_leadcampaign_stats";

    /**
     * @var LeadModel
     */
    protected $leadModel;

    /**
     * @var CampaignModel
     */
    protected $campaignModel;

    /**
     * @var FieldsBuilder
     */
    private $fieldsBuilder;

    /**
     * @param LeadModel         $leadModel
     * @param CampaignModel     $campaignModel
     * @param FieldsBuilder     $fieldsBuilder
     */
    public function __construct(
        LeadModel $leadModel,
        CampaignModel $campaignModel,
        FieldsBuilder $fieldsBuilder
    ) {
        $this->leadModel         = $leadModel;
        $this->campaignModel     = $campaignModel;
        $this->fieldsBuilder     = $fieldsBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ReportEvents::REPORT_ON_BUILD    => ['onReportBuilder', 0],
            ReportEvents::REPORT_ON_GENERATE => ['onReportGenerate', 0],
        ];
    }

    /**
     * Add available tables and columns to the report builder lookup.
     *
     * @param ReportBuilderEvent $event
     */
    public function onReportBuilder(ReportBuilderEvent $event)
    {
        $campaignPrefix      = 'c.';
        $campaignAliasPrefix = 'c_';
        $campaignLeadPrefix        = 'cl.';
        $campaignLeadAliasPrefix   = 'cl_';


        $columns = [
            $campaignPrefix.'name' => [
                'label' => 'mautic.contactsource.leadcampaign.header.name',
                'type'  => 'string',
                'alias' => $campaignAliasPrefix.'name',
            ],

            $campaignPrefix.'id' => [
                'label' => 'mautic.contactsource.leadcampaign.header.id',
                'type'  => 'string',
                'alias' => $campaignAliasPrefix.'id',
            ],

            $campaignPrefix.'is_published' => [
                'label' => 'mautic.contactsource.leadcampaign.header.ispublished',
                'type'  => 'int',
                'alias' => $campaignAliasPrefix.'is_published',
            ],

            $campaignLeadPrefix.'rotation' => [
                'label' => 'mautic.contactsource.leadcampaign.header.rotation',
                'type'  => 'string',
                'alias' => $campaignLeadAliasPrefix.'rotation',
            ],

            $campaignLeadPrefix.'manually_removed' => [
                'label' => 'mautic.contactsource.leadcampaign.header.manually_removed',
                'type'  => 'string',
                'alias' => $campaignLeadAliasPrefix.'manually_removed',
            ],

            $campaignLeadPrefix.'manually_added' => [
                'label' => 'mautic.contactsource.leadcampaign.header.manually_added',
                'type'  => 'string',
                'alias' => $campaignLeadAliasPrefix.'manually_added',
            ],

            $campaignLeadPrefix.'date_added' => [
                'label' => 'mautic.contactsource.leadcampaign.header.date_added',
                'type'  => 'string',
                'alias' => $campaignLeadAliasPrefix.'date_added',
            ],
        ];

        $mergedColumns = array_merge(
            $this->fieldsBuilder->getLeadFieldsColumns('l.'), $columns
        );

        $data = [
            'display_name' => 'mautic.widget.leadcampaign.stats',
            'columns'      => $mergedColumns,
        ];
        $event->addTable(self::CONTEXT_CONTACT_SOURCE_LEADCAMPAIGN_STATS, $data, 'contacts');

    }

    /**
     * @param ReportGeneratorEvent $event
     *
     * @throws \Exception
     *
     */
    public function onReportGenerate(ReportGeneratorEvent $event)
    {
        $qb       = $event->getQueryBuilder();
        $dateFrom = $event->getOptions()['dateFrom'];
        $dateTo   = $event->getOptions()['dateTo'];

        $dateOffset = [
            'DAILY'   => '-1 day',
            'WEEKLY'  => '-7 days',
            'MONTHLY' => '- 30 days',
        ];
        if (empty($event->getReport()->getScheduleUnit())) {
            $dateShift = '- 30 days';
        } else {
            $dateShift = $dateOffset[$event->getReport()->getScheduleUnit()];
        }

        if ($event->checkContext(self::CONTEXT_CONTACT_source_CLIENT_STATS)) {
            $qb->select('SUM(cls.revenue) / SUM(cls.received) as rpu, SUM(cls.revenue / 1000) AS rpm');
            $qb->leftJoin('cls', MAUTIC_TABLE_PREFIX.'contactclient', 'cc', 'cc.id = cls.contact_client_id');
            $catPrefix = 'cc';
            $from      = 'contact_source_campaign_client_stats';
        } elseif ($event->checkContext(self::CONTEXT_CONTACT_source_SOURCE_STATS)) {
            $qb->leftJoin('cls', MAUTIC_TABLE_PREFIX.'contactsource', 'cs', 'cs.id = cls.contact_source_id');
            $catPrefix = 'cs';
            $from      = 'contact_source_campaign_source_stats';
        } else {
            return;
        }

        if (empty($dateFrom)) {
            $dateFrom = new \DateTime();
            $dateFrom->modify($dateShift);
        }

        if (empty($dateTo)) {
            $dateTo = new \DateTime();
        }

        $qb->andWhere('cls.date_added BETWEEN :dateFrom AND :dateTo')
            ->setParameter('dateFrom', $dateFrom->format('Y-m-d H:i:s'))
            ->setParameter('dateTo', $dateTo->format('Y-m-d H:i:s'));

        $qb->from(MAUTIC_TABLE_PREFIX.$from, 'cls')
            ->leftJoin('cls', MAUTIC_TABLE_PREFIX.'campaigns', 'c', 'c.id = cls.campaign_id');

        $event->addCategoryLeftJoin($qb, $catPrefix, 'cat');

        $event->setQueryBuilder($qb);
    }
}
