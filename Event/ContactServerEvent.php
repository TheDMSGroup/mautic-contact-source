<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use MauticPlugin\MauticContactServerBundle\Entity\ContactServer;

/**
 * Class ContactServerEvent
 * @package MauticPlugin\MauticContactServerBundle\Event
 */
class ContactServerEvent extends CommonEvent
{
    /**
     * @param ContactServer      $contactserver
     * @param bool|false $isNew
     */
    public function __construct(ContactServer $contactserver, $isNew = false)
    {
        $this->entity = $contactserver;
        $this->isNew  = $isNew;
    }

    /**
     * Returns the ContactServer entity.
     *
     * @return ContactServer
     */
    public function getContactServer()
    {
        return $this->entity;
    }

    /**
     * Sets the ContactServer entity.
     *
     * @param ContactServer $contactserver
     */
    public function setContactServer(ContactServer $contactserver)
    {
        $this->entity = $contactserver;
    }
}
