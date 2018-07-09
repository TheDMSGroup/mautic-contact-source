<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;

/**
 * Class ContactSourceEvent.
 */
class ContactSourceEvent extends CommonEvent
{
    /**
     * @param ContactSource $contactsource
     * @param bool|false    $isNew
     */
    public function __construct(ContactSource $contactsource, $isNew = false)
    {
        $this->entity = $contactsource;
        $this->isNew  = $isNew;
    }

    /**
     * Returns the ContactSource entity.
     *
     * @return ContactSource
     */
    public function getContactSource()
    {
        return $this->entity;
    }

    /**
     * Sets the ContactSource entity.
     *
     * @param ContactSource $contactsource
     */
    public function setContactSource(ContactSource $contactsource)
    {
        $this->entity = $contactsource;
    }
}
