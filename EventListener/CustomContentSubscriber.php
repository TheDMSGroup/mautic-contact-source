<?php
/**
 * Created by PhpStorm.
 * User: nbush
 * Date: 3/31/18
 * Time: 8:39 PM.
 */

namespace MauticPlugin\MauticContactSourceBundle\EventListener;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;

class CustomContentSubscriber extends CommonSubscriber
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * CustomContentSubscriber constructor.
     *
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['injectCustomChart', 0],
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['getContentInjection', 0],
        ];
    }

    public function injectCustomChart(CustomContentEvent $event)
    {
        switch ($event->getViewName()) {
            case 'MauticCampaignBundle:Campaign:details.html.php':
                if ('left.section.top' === $event->getContext()) {
                    $vars = $event->getVars();

                    /** @var \Symfony\Component\Form\FormView $dateRangeForm */
                    $dateRangeForm = $vars['dateRangeForm'];

                    $dateFrom = new \DateTime($dateRangeForm->children['date_from']->vars['value']);
                    $dateTo   = new \DateTime($dateRangeForm->children['date_to']->vars['value']);

                    $builder = $this->em->getConnection()->createQueryBuilder();
                    $builder
                        ->select(
                            'DATE_FORMAT(c.date_added, "%Y%m%d") as label',
                            's.name',
                            'COUNT(*) as contacts'
                        )
                        ->from('contactsource', 's')
                        ->join('s', 'contactsource_stats', 'c', 's.id=c.contactsource_id')
                        ->where(
                            $builder->expr()->eq('?', 'c.campaign_id'),
                            $builder->expr()->lte('?', 'c.date_added'),
                            $builder->expr()->gt('?', 'c.date_added')
                        )
                        ->groupBy(['label', 's.id']);

                    $results = [];

                    try {
                        $stmt = $this->em->getConnection()->prepare(
                            $builder->getSQL()
                        );
                        // query the database
                        $stmt->bindValue(1, $vars['campaign']->getId(), Type::INTEGER);
                        $stmt->bindValue(2, $dateFrom, Type::DATETIME);
                        $stmt->bindValue(3, $dateTo, Type::DATETIME);
                        $stmt->execute();
                        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    } catch (DBALException $e) {
                    }

                    $datasets = $sources = [];
                    foreach ($results as $result) {
                        $sources[$result['name']]  = 1;
                        $datasets[$result['name']] = [];
                    }

                    $chartData = [
                        'labels'   => [],
                        'datasets' => [],
                    ];

                    for (
                        $current = \DateTime::createFromFormat('Y-m-d H:i:s', $dateFrom->format('Y-m-d H:i:s'));
                        $current < $dateTo;
                        $current->modify('+1 day')
                    ) {
                        $chartData['labels'][] = $current->format('m/d/y');
                        foreach ($sources as $source => $flag) {
                            $datasets[$source][] = '0';
                        }
                    }

                    $datasets = [];
                    foreach ($results as $result) {
                        $index                             = array_search($result['label'], $chartData['labels']);
                        $datasets[$result['name']][$index] = $result['contacts'];
                    }

                    foreach ($datasets as $label => $data) {
                        $temp = [
                            'label' => $label,
                            'data'  => $data,
                        ];
                        //color?
                        $chartData['datasets'][] = $temp;
                    }

                    $event->addTemplate(
                        'MauticContactSourceBundle:Charts:campaigncontactsbysource.html.php',
                        ['campaignSourceData' => $chartData]
                    );
                }
                break;
        }
    }

    public function getContentInjection(CustomContentEvent $event)
    {
        switch ($event->getViewName()) {
            case 'MauticCampaignBundle:Campaign:details.html.php':
                $vars = $event->getVars();
                if ('tabs' === $event->getContext()) {
                    $tabTemplate = 'MauticContactSourceBundle:Tabs:campaign_source_tabs.html.php';
                    $event->addTemplate(
                        $tabTemplate,
                        [
                            'tabData' => $vars,
                        ]
                    );
                }
                if ('tabs.content' === $event->getContext()) {
                    $tabContentTemplate = 'MauticContactSourceBundle:Tabs:campaign_source_tab_content.html.php';
                    $event->addTemplate(
                        $tabContentTemplate,
                        [
                            'tabData'  => $vars,
                            'campaign' => $vars['campaign'],
                        ]
                    );
                }
                break;
        }
    }
}
