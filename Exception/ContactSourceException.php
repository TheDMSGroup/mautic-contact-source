<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Exception;

use Mautic\LeadBundle\Entity\Lead as Contact;

/**
 * Class ContactSourceException.
 *
 * This form of exception indicates that we may re-try the send at a later date or time.
 * Also can indicate a Stat type for logging.
 */
class ContactSourceException extends \Exception
{
    /** @var string */
    private $contactId;

    /** @var Contact */
    private $contact;

    /** @var string */
    private $statType;

    /** @var array */
    private $data;

    /** @var string */
    private $field;

    /**
     * ContactSourceException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Exception|null $previous
     * @param null            $statType
     * @param null            $field
     * @param array           $data
     */
    public function __construct(
        $message = 'Contact Source error',
        $code = 0,
        \Exception $previous = null,
        $statType = null,
        $field = null,
        $data = []
    ) {
        if ($statType) {
            $this->setStatType($statType);
        }
        if ($field) {
            $this->setField($field);
        }
        $this->data = $data;
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
     * @return ContactSourceException
     */
    public function setContactId($contactId)
    {
        $this->contactId = $contactId;

        return $this;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
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
     * @return ContactSourceException
     */
    public function setStatType($statType)
    {
        $this->statType = $statType;

        return $this;
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param string $field
     *
     * @return ContactSourceException
     */
    public function setField($field)
    {
        $this->field = $field;

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
     * @return ContactSourceException
     */
    public function setContact(Contact $contact)
    {
        $this->contact = $contact;

        return $this;
    }
}
