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
        $repo         = $this->em->getRepository('MauticContactSourceBundle:Event');
        $sourceEvents = $repo->getEventsByContactId($event->getLeadId());

        if (!is_array($sourceEvents)) {
            return;
        }

        foreach ($sourceEvents as $srcEvent) {
            $srcEvent['eventLabel'] = [
                'label' => 'Contact Source: '.$srcEvent['sourceName'],
                'href'  => "/s/contactsource/view/{$srcEvent['contactsource_id']}",
            ];
            $srcEvent['event']     = '';
            $srcEvent['eventType'] = ucfirst($srcEvent['type']);
            $srcEvent['extra']     = [
                'logs'    => $srcEvent['logs'],
                'message' => $srcEvent['message'],
            ];
            $srcEvent['contentTemplate'] = 'MauticContactSourceBundle:Timeline:sourceevent.html.php';
            $srcEvent['icon']            = 'fa-plus-square-o contact-source-button';
            $event->addEvent($srcEvent);
        }
    }
}
