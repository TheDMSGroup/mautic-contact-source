<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Model;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Event\CampaignDecisionEvent;
use Mautic\CampaignBundle\Model\EventModel as OriginalCampaignEventModel;


/**
 * Class CampaignEventModel
 *
 * Method of triggering campaign events with one or more leads at run-time for real-time sources.
 * Overrides some default behavior to reduce query count for small batches.
 */
class CampaignEventModel extends OriginalCampaignEventModel
{
    /**
     * Trigger the root level action(s) in campaign with one (or more) lead(s)
     * and process the workflow as far as possible in real-time, returning the result statuses per contact.
     *
     * Changes from the original:
     * - No cron/console debug output or logging.
     * - Takes an array of loaded contact entities to reduce queries (future proofing for batch source support).
     * - No batchSleep, since we need real-time speed here.
     * - Does not clear/detach contacts.
     * - No limit or max event count (intentionally at the mercy of the campaign events).
     * - Returns contactEvents (events ran)
     *
     * @todo - Remove console output.
     * @todo - Remove any nonessential event dispatches.
     * @todo - Output usable array by contacts.
     *
     * @param Campaign $campaign
     * @param $totalEventCount
     * @param array $contacts
     * @return array|int
     * @throws \Doctrine\ORM\ORMException
     */
    public function triggerContactStartingEvents(
        Campaign $campaign,
        &$totalEventCount,
        array $contacts
    ) {
        defined('MAUTIC_CAMPAIGN_SYSTEM_TRIGGERED') or define('MAUTIC_CAMPAIGN_SYSTEM_TRIGGERED', 1);

        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $this->dispatcher->getContainer()->get('session');

        $contactEvents = [];
        $contactClientEvents = [];
        $decisionChildren = [];
        $campaignId = $campaign->getId();

        $repo = $this->getRepository();
        $logRepo = $this->getLeadEventLogRepository();

        if ($this->dispatcher->hasListeners(CampaignEvents::ON_EVENT_DECISION_TRIGGER)) {
            // Include decisions if there are listeners
            $events = $repo->getRootLevelEvents($campaignId, true);

            // Filter out decisions
            foreach ($events as $event) {
                if ($event['eventType'] == 'decision') {
                    $decisionChildren[$event['id']] = $repo->getEventsByParent($event['id']);
                }
            }
        } else {
            $events = $repo->getRootLevelEvents($campaignId);
        }

        $rootEventCount = count($events);

        if (empty($rootEventCount)) {

            return [
                'events' => 0,
                'evaluated' => 0,
                'executed' => 0,
                'totalEvaluated' => 0,
                'totalExecuted' => 0,
                'contactEvents' => [],
            ];
        }

        // Event settings
        $eventSettings = $this->campaignModel->getEvents();

        // Get a total number of events that will be processed
        $totalStartingEvents = $rootEventCount;

        $evaluatedEventCount = $executedEventCount = $rootEvaluatedCount = $rootExecutedCount = 0;

        // Try to save some memory
        gc_enable();

        /** @var \Mautic\LeadBundle\Entity\Lead $contact */
        foreach ($contacts as $contact) {

            // Set lead in case this is triggered by the system
            $this->leadModel->setSystemCurrentLead($contact);

            foreach ($events as $event) {
                ++$rootEvaluatedCount;

                if ($event['eventType'] == 'decision') {
                    ++$evaluatedEventCount;
                    ++$totalEventCount;

                    $event['campaign'] = [
                        'id' => $campaign->getId(),
                        'name' => $campaign->getName(),
                        'createdBy' => $campaign->getCreatedBy(),
                    ];

                    if (isset($decisionChildren[$event['id']])) {
                        $decisionEvent = [
                            $campaignId => [
                                array_merge(
                                    $event,
                                    ['children' => $decisionChildren[$event['id']]]
                                ),
                            ],
                        ];
                        $decisionTriggerEvent = new CampaignDecisionEvent(
                            $contact,
                            $event['type'],
                            null,
                            $decisionEvent,
                            $eventSettings,
                            true
                        );
                        $this->dispatcher->dispatch(
                            CampaignEvents::ON_EVENT_DECISION_TRIGGER,
                            $decisionTriggerEvent
                        );
                        if ($decisionTriggerEvent->wasDecisionTriggered()) {
                            ++$executedEventCount;
                            ++$rootExecutedCount;

                            // Decision has already been triggered by the lead so process the associated events
                            $decisionLogged = false;
                            foreach ($decisionEvent['children'] as $childEvent) {
                                if ($this->executeEvent(
                                        $childEvent,
                                        $campaign,
                                        $contact,
                                        $eventSettings,
                                        false,
                                        null,
                                        null,
                                        false,
                                        $evaluatedEventCount,
                                        $executedEventCount,
                                        $totalEventCount
                                    )
                                    && !$decisionLogged
                                ) {
                                    // Log the decision
                                    $log = $this->getLogEntity(
                                        $decisionEvent['id'],
                                        $campaign,
                                        $contact,
                                        null,
                                        true
                                    );
                                    $log->setDateTriggered(new \DateTime());
                                    $log->setNonActionPathTaken(true);
                                    $logRepo->saveEntity($log);
                                    $this->em->detach($log);
                                    unset($log);

                                    $decisionLogged = true;
                                }
                            }
                        }

                        unset($decisionEvent);
                    }
                } else {
                    if ($this->executeEvent(
                        $event,
                        $campaign,
                        $contact,
                        $eventSettings,
                        false,
                        null,
                        null,
                        false,
                        $evaluatedEventCount,
                        $executedEventCount,
                        $totalEventCount
                    )
                    ) {
                        ++$rootExecutedCount;
                    }
                }
                // Break if a valid client is found at root.
                if ($session->get('contactclient_valid')) {
                    break;
                }
            }
            // Process stack of triggers for the recent contact added during executeEvents.
            // @todo - Break if a valid client is found in triggers.
            $this->triggerConditions($campaign, $evaluatedEventCount, $executedEventCount, $totalEventCount);

            // Event array is not all-inclusive. Only contains parents.
            // $contactEvents[$contact->getId()] = $events;

            $contactClientEvents[$contact->getId()] = $session->get('contactclient_events', []);
            $session->set('contactclient_events', []);
            $session->set('contactclient_valid', null);

            unset($event);
        }

        unset($contacts);

        // Free some memory
        gc_collect_cycles();

        return [
            'events' => $totalStartingEvents,
            'evaluated' => $rootEvaluatedCount,
            'executed' => $rootExecutedCount,
            'totalEvaluated' => $evaluatedEventCount,
            'totalExecuted' => $executedEventCount,
            'contactEvents' => $contactEvents,
            'contactClientEvents' => $contactClientEvents,
        ];
    }

}
