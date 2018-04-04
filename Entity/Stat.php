<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Lead as Contact;

/**
 * Class Stat.
 *
 * Entity is used to track statistics around Contact Sources.
 */
class Stat
{
    /**
     * Real-time:     Indicates that the contact was accepted by one or more clients.
     * Not real-time: Indicates we accepted the contact outright.
     *
     * Internal status:     accepted
     * External status:     accepted / rejected (if scrubbed)
     *
     * Contact saved:       Yes
     * Added to campaign:   Yes
     */
    const TYPE_ACCEPTED = 'accepted';

    /**
     * Indicates that the contact has already come in via this source.
     *
     * Internal status:     duplicate
     * External status:     duplicate
     *
     * Contact saved:       Yes
     * Added to campaign:   No
     */
    const TYPE_DUPLICATE = 'duplicate';

    /**
     * Indicates that something went wrong on our side during processing at the contact source level.
     *
     * Internal status:     error
     * External status:     error
     *
     * Contact saved:       Yes / No (depends on where)
     * Added to campaign:   No
     */
    const TYPE_ERROR = 'error';

    /**
     * Indicates that the parameters sent to us are invalid, such as an unpublished campaign, or invalid token.
     *
     * Internal status:     invalid
     * External status:     invalid
     *
     * Contact saved:       No
     * Added to campaign:   No
     */
    const TYPE_INVALID = 'invalid';

    /**
     * Indicates we hit a budget/cap and could not accept this contact.
     *
     * Internal status:     limited
     * External status:     limited
     *
     * Contact saved:       Yes
     * Added to campaign:   No
     */
    const TYPE_LIMITED = 'limited';

    /**
     * Indicates we could not find a buyer for the contact, including ourselves (not real-time).
     *
     * Internal status:     rejected
     * External status:     rejected
     *
     * Contact saved:       Yes
     * Added to campaign:   Yes
     */
    const TYPE_REJECT = 'rejected';

    /**
     * Real-time:     Indicates we could not find a buyer for the contact.
     * Not real-time: The contact was scrubbed.
     *
     * Internal status:     scrubbed
     * External status:     rejected
     *
     * Contact saved:       Yes
     * Added to campaign:   Yes
     */
    const TYPE_SCRUBBED = 'scrubbed';

    /** @var int $id */
    private $id;

    /** @var ContactSource $contactSource */
    private $contactSource;

    /** @var string $type */
    private $type;

    /** @var \DateTime $dateAdded */
    private $dateAdded;

    /** @var int $contact */
    private $contact;

    /** @var float $attribution */
    private $attribution;

    /** @var int $campaign */
    private $campaign_id;

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('contactsource_stats')
            ->setCustomRepositoryClass('MauticPlugin\MauticContactSourceBundle\Entity\StatRepository');

        $builder->addId();

        $builder->createManyToOne('contactSource', 'ContactSource')
            ->addJoinColumn('contactsource_id', 'id', true, false, null)
            ->build();

        $builder->addNullableField('type', 'string');

        $builder->addNamedField('dateAdded', 'datetime', 'date_added');

        $builder->createField('attribution', 'decimal')
            ->precision(19)
            ->scale(4)
            ->nullable()
            ->build();

        $builder->addNullableField('campaign_id', 'integer');

        $builder->addNamedField('contact', 'integer', 'contact_id', true);

        $builder->addIndex(
            ['contactsource_id', 'type', 'date_added'],
            'contactsource_type_date_added'
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
    public function getContactSource()
    {
        return $this->contactSource;
    }

    /**
     * @param mixed $contactSource
     *
     * @return Stat
     */
    public function setContactSource($contactSource)
    {
        $this->contactSource = $contactSource;

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
     *
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
            $result     = $reflection->getConstants();
        } catch (\ReflectionException $e) {
        }

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
     * @return int
     */
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * @param Contact|int $contact
     *
     * @return Stat
     */
    public function setContact($contact)
    {
        if ($contact instanceof Contact) {
            $contact = $contact->getId();
        }
        $this->contact = $contact;

        return $this;
    }

    /**
     * @return int
     */
    public function getCampaign()
    {
        return $this->campaign_id;
    }

    /**
     * @param int $campaign_id
     *
     * @return Stat
     */
    public function setCampaign(int $campaign_id)
    {
        $this->campaign_id = $campaign_id;

        return $this;
    }
}
