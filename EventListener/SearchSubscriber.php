<?php


namespace MauticPlugin\MauticContactSourceBundle\EventListener;


use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Event\GlobalSearchEvent;
use Doctrine\ORM\EntityManager;
use MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel;

class SearchSubscriber extends CommonSubscriber
{

    /**
     * @var ContactSourceModel
     */
    protected $sourceModel;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::GLOBAL_SEARCH              => ['onGlobalSearch', 0],
        ];
    }

    /**
     * SearchSubscriber constructor.
     *
     * @param ContactSourceModel $sourceModel
     */
    public function __construct(ContactSourceModel $sourceModel)
    {
        $this->sourceModel       = $sourceModel;
    }

    /**
     * @param GlobalSearchEvent $event
     */
    public function onGlobalSearch(GlobalSearchEvent $event)
    {
        $str = $event->getSearchString();
        if (empty($str)) {
            return;
        }

        $filter    = ['string' => $str, 'force' => ''];

        $permissions = $this->security->isGranted(
            ['plugin:contactsource:items:view'],
            'RETURN_ARRAY'
        );

        if ($permissions) {

            $results = $this->sourceModel->getEntities(
                [
                    'limit'          => 5,
                    'filter'         => $filter,
                    'withTotalCount' => true,
                ]);

            $count = $results->count();

            if ($count > 0) {
                $sources       = $results->getQuery()->execute();
                $sourceResults = [];

                foreach ($sources as $source) {
                    $sourceResults[] = $this->templating->renderResponse(
                        'MauticContactSourceBundle:SubscribedEvents\Search:global.html.php',
                        ['source' => $source]
                    )->getContent();
                }

                if ($count > 5) {
                    $sourceResults[] = $this->templating->renderResponse(
                        'MauticContactSourceBundle:SubscribedEvents\Search:global.html.php',
                        [
                            'source'       => $source,
                            'showMore'     => true,
                            'searchString' => $str,
                            'remaining'    => ($count - 5),
                        ]
                    )->getContent();
                }
                $sourceResults['count'] = $count;
                $event->addResults('mautic.contactsource', $sourceResults);
            }
        }
    }

}