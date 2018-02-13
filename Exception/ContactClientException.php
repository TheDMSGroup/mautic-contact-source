<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Exception;

use Mautic\LeadBundle\Entity\Lead as Contact;

/**
 * Class ContactServerException
 *
 * @package MauticPlugin\MauticContactServerBundle\Exception
 */
class ContactServerException extends \Exception
{
    /** @var string */
    private $contactId;

    /** @var Contact */
    private $contact;

    /** @var string */
    private $statType;

    /**
     * ContactServerException constructor.
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     * @param null $statType
     */
    public function __construct(
        $message = 'Contact Server error',
        $code = 0,
        \Exception $previous = null,
        $statType = null
    ) {
        if ($statType) {
            $this->setStatType($statType);
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return mixed
     */
    public function getContactId()
    {
        return $this->contactId;
    }

    /**
     * @param mixed $contactId
     *
     * @return ContactServerException
     */
    public function setContactId($contactId)
    {
        $this->contactId = $contactId;

        return $this;
    }

    /**
     * @return string
     */
    public function getStatType()
    {
        return $this->statType;
    }

    /**
     * @param string $statType
     *
     * @return ContactServerException
     */
    public function setStatType($statType)
    {
        $this->statType = $statType;

        return $this;
    }

    /**
     * @return Contact
     */
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * @param Contact $contact
     *
     * @return ContactServerException
     */
    public function setContact(Contact $contact)
    {
        $this->contact = $contact;

        return $this;
    }
}
