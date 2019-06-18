<?php

namespace MauticPlugin\MauticContactSourceBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\LeadEvents;

class LeadTimelineSubscriber extends CommonSubscriber
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            LeadEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    /**
     * @param LeadTimelineEvent $event
     */
    public function onTimelineGenerate(LeadTimelineEvent $event)
    {
        $eventTypeKey  = 'contact_source.send';
        $eventTypeName = 'Contact Source';
        $event->addEventType($eventTypeKey, $eventTypeName);

        // Determine if this event has been filtered out
        if (!$event->isApplicable($eventTypeKey)) {
            return;
        }

        $repo         = $this->em->getRepository('MauticContactSourceBundle:Event');

        // $event->getQueryOptions() provide timeline filters, etc.
        // This method should use DBAL to obtain the events to be injected into the timeline based on pagination
        // but also should query for a total number of events and return an array of ['total' => $x, 'results' => []].
        // There is a TimelineTrait to assist with this. See repository example.$repo         = $this->em->getRepository('MauticContactSourceBundle:Event');
        $stats = $repo->getTimelineStats($event->getLeadId(), $event->getQueryOptions());

        // If isEngagementCount(), this event should only inject $stats into addToCounter() to append to data to generate
        // the engagements graph. Not all events are engagements if they are just informational so it could be that this
        // line should only be used when `!$event->isEngagementCount()`. Using TimelineTrait will determine the appropriate
        // return value based on the data included in getQueryOptions() if used in the stats method above.
        $event->addToCounter($eventTypeKey, $stats);

        if (!$event->isEngagementCount()) {
            // Add the events to the event array
            foreach ($stats['results'] as $stat) {
                if ($stat['date_added']) {
                    $event->addEvent(
                        [
                            // Event key type
                            'event'           => $eventTypeKey,
                            // Event name/label - can be a string or an array as below to convert to a link
                            'eventLabel'      => [
                                'label' => 'Source: '.$stat['sourceName'],
                                'href'  => "/s/contactsource/view/{$stat['contactsource_id']}",
                            ],
                            // Translated string displayed in the Event Type column
                            'eventType'       => ucfirst($stat['type']),
                            // \DateTime object for the timestamp column
                            'timestamp'       => $stat['date_added'],
                            // Optional details passed through to the contentTemplate
                            'extra'           => [
                                'stat'            => $stat,
                                'logs'            => json_decode($stat['logs']),
                                'message'         => $stat['message'],
                            ],
                            // Optional template to customize the details of the event in the timeline
                            'contentTemplate' => 'MauticContactSourceBundle:Timeline:sourceevent.html.php',
                            // Font Awesome class to display as the icon
                            'icon'            => 'fa-plus-square-o contact-source-button',
                        ]
                    );
                }
            }
        }
    }
}
