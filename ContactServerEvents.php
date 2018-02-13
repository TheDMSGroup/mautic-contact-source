<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle;

/**
 * Class ContactServerEvents.
 *
 * Events available for MauticContactServerBundle
 */
final class ContactServerEvents
{
    /**
     * The mautic.contactserver_pre_save event is dispatched right before a contactserver is persisted.
     *
     * The event listener receives a MauticPlugin\MauticContactServerBundle\Event\ContactServerEvent instance.
     *
     * @var string
     */
    const PRE_SAVE = 'mautic.contactserver_pre_save';

    /**
     * The mautic.contactserver_post_save event is dispatched right after a contactserver is persisted.
     *
     * The event listener receives a MauticPlugin\MauticContactServerBundle\Event\ContactServerEvent instance.
     *
     * @var string
     */
    const POST_SAVE = 'mautic.contactserver_post_save';

    /**
     * The mautic.contactserver_pre_delete event is dispatched before a contactserver is deleted.
     *
     * The event listener receives a MauticPlugin\MauticContactServerBundle\Event\ContactServerEvent instance.
     *
     * @var string
     */
    const PRE_DELETE = 'mautic.contactserver_pre_delete';

    /**
     * The mautic.contactserver_post_delete event is dispatched after a contactserver is deleted.
     *
     * The event listener receives a MauticPlugin\MauticContactServerBundle\Event\ContactServerEvent instance.
     *
     * @var string
     */
    const POST_DELETE = 'mautic.contactserver_post_delete';

    /**
     * The mautic.contactserver_timeline_on_generate event is dispatched when generating a timeline view.
     *
     * The event listener receives a
     * MauticPlugin\MauticContactServerBundle\Event\LeadTimelineEvent instance.
     *
     * @var string
     */
    const TIMELINE_ON_GENERATE = 'mautic.contactserver_timeline_on_generate';

}
