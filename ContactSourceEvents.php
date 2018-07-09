<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle;

/**
 * Class ContactSourceEvents.
 *
 * Events available for MauticContactSourceBundle
 */
final class ContactSourceEvents
{
    /**
     * The mautic.contactsource_pre_save event is dispatched right before a contactsource is persisted.
     *
     * The event listener receives a MauticPlugin\MauticContactSourceBundle\Event\ContactSourceEvent instance.
     *
     * @var string
     */
    const PRE_SAVE = 'mautic.contactsource_pre_save';

    /**
     * The mautic.contactsource_post_save event is dispatched right after a contactsource is persisted.
     *
     * The event listener receives a MauticPlugin\MauticContactSourceBundle\Event\ContactSourceEvent instance.
     *
     * @var string
     */
    const POST_SAVE = 'mautic.contactsource_post_save';

    /**
     * The mautic.contactsource_pre_delete event is dispatched before a contactsource is deleted.
     *
     * The event listener receives a MauticPlugin\MauticContactSourceBundle\Event\ContactSourceEvent instance.
     *
     * @var string
     */
    const PRE_DELETE = 'mautic.contactsource_pre_delete';

    /**
     * The mautic.contactsource_post_delete event is dispatched after a contactsource is deleted.
     *
     * The event listener receives a MauticPlugin\MauticContactSourceBundle\Event\ContactSourceEvent instance.
     *
     * @var string
     */
    const POST_DELETE = 'mautic.contactsource_post_delete';

    /**
     * The mautic.contactsource_timeline_on_generate event is dispatched when generating a timeline view.
     *
     * The event listener receives a
     * MauticPlugin\MauticContactSourceBundle\Event\LeadTimelineEvent instance.
     *
     * @var string
     */
    const TIMELINE_ON_GENERATE = 'mautic.contactsource_timeline_on_generate';
}
