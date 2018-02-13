<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Lead as Contact;

/**
 * Class Stat.
 *
 * Entity is used to track statistics around Contact Servers.
 */
class Stat
{
    // Used for querying stats
    const TYPE_QUEUED = 'queue';
    const TYPE_DUPLICATE = 'duplicate';
    const TYPE_FILTER = 'filter';
    const TYPE_LIMITS = 'limits';
    const TYPE_SUCCESS = 'success';
    const TYPE_REJECT = 'reject';
    const TYPE_ERROR = 'error';

    /** @var int $id */
    private $id;

    /** @var ContactServer $contactServer */
    private $contactServer;

    /** @var string $type */
    private $type;

    /** @var \DateTime $dateAdded */
    private $dateAdded;

    /** @var Contact $contact */
    private $contact;

    /** @var float $attribution */
    private $attribution;

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('contactserver_stats')
            ->setCustomRepositoryClass('MauticPlugin\MauticContactServerBundle\Entity\StatRepository');

        $builder->addId();

        $builder->createManyToOne('contactServer', 'ContactServer')
            ->addJoinColumn('contactserver_id', 'id', false, false, 'CASCADE')
            ->build();

        $builder->addField('type', 'string');

        $builder->addNamedField('dateAdded', 'datetime', 'date_added');

        $builder->createField('attribution', 'decimal')
            ->columnDefinition('DECIMAL(19, 4) DEFAULT NULL')
            ->build();

        $builder->addContact(true, 'SET NULL');

        $builder->addIndex(
            ['contactserver_id', 'type', 'date_added'],
            'contactserver_type_date_added'
        );
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getContactServer()
    {
        return $this->contactServer;
    }

    /**
     * @param mixed $contactServer
     *
     * @return Stat
     */
    public function setContactServer($contactServer)
    {
        $this->contactServer = $contactServer;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     *
     * @return Stat
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return float
     */
    public function getAttribution()
    {
        return $this->attribution;
    }

    /**
     * @param $attribution
     * @return $this
     */
    public function setAttribution($attribution)
    {
        $this->attribution = $attribution;

        return $this;
    }

    /**
     * @return array
     */
    public function getAllTypes()
    {
        $result = [];
        try {
            $reflection = new \ReflectionClass(__CLASS__);
            $result = $reflection->getConstants();
        } catch (\ReflectionException $e) {
        };

        return $result;
    }

    /**
     * @return mixed
     */
    public function getDateAdded()
    {
        return $this->dateAdded;
    }

    /**
     * @param mixed $dateAdded
     *
     * @return Stat
     */
    public function setDateAdded($dateAdded)
    {
        $this->dateAdded = $dateAdded;

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
     * @return Stat
     */
    public function setContact(Contact $contact)
    {
        $this->contact = $contact;

        return $this;
    }
}
