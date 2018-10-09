<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class ContactSource.
 *
 * Entity is used to contain all the rules necessary to create a dynamic integration called a Contact Source.
 */
class ContactSource extends FormEntity
{
    /** @var int */
    private $id;

    /** @var string */
    private $utmSource;

    /** @var string */
    private $description;

    /** @var string */
    private $descriptionPublic;

    /** @var string */
    private $name;

    /** @var */
    private $category;

    /** @var string */
    private $token;

    /** @var bool */
    private $documentation;

    /** @var string */
    private $campaign_settings;

    /** @var \DateTime */
    private $publishUp;

    /** @var \DateTime */
    private $publishDown;

    /** @var */
    private $campaignList;

    /**
     * ContactSource constructor.
     */
    public function __construct()
    {
        if (!$this->token) {
            $this->token = sha1(uniqid());
        }

        if (null === $this->documentation) {
            $this->documentation = true;
        }
    }

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'name',
            new NotBlank(
                ['message' => 'mautic.core.name.required']
            )
        );

        $metadata->addPropertyConstraint(
            'token',
            new NotBlank(
                ['message' => 'mautic.contactsource.error.token']
            )
        );
    }

    /**
     * Allow these entities to be cloned like core entities.
     */
    public function __clone()
    {
        $this->id    = null;
        $this->token = sha1(uniqid());

        parent::__clone();
    }

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('contactsource')
            ->setCustomRepositoryClass('MauticPlugin\MauticContactSourceBundle\Entity\ContactSourceRepository');

        $builder->addIdColumns();

        $builder->addCategory();

        $builder->addPublishDates();

        $builder->addNamedField('utmSource', 'string', 'utm_source', true);

        $builder->addNullableField('descriptionPublic', 'string', 'description_public');

        $builder->addField('token', 'string');

        $builder->addField('documentation', 'boolean');

        $builder->addNullableField('campaign_settings', 'text');
    }

    /**
     * Prepares the metadata for API usage.
     *
     * @param $metadata
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata)
    {
        $metadata->setGroupPrefix('contactsource')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'category',
                ]
            )
            ->addProperties(
                [
                    'description',
                    'descriptionPublic',
                    'utmSource',
                    'token',
                    'documentation',
                    'publishUp',
                    'publishDown',
                    'campaignList',
                ]
            )
            ->setGroupPrefix('contactsourceBasic')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'category',
                    'description',
                    'descriptionPublic',
                    'utmSource',
                    'campaignList',
                ]
            )
            ->build();
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     *
     * @return $this
     */
    public function setToken($token)
    {
        $token = trim($token);

        $this->isChanged('token', $token);

        $this->token = $token;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDocumentation()
    {
        return $this->documentation;
    }

    /**
     * @param bool $documentation
     *
     * @return $this
     */
    public function setDocumentation($documentation)
    {
        $this->isChanged('documentation', $documentation);

        $this->documentation = $documentation;

        return $this;
    }

    /**
     * @return string
     */
    public function getCampaignSettings()
    {
        return $this->campaign_settings;
    }

    /**
     * @param string $campaign_settings
     *
     * @return $this
     */
    public function setCampaignSettings($campaign_settings)
    {
        $this->isChanged('campaignSettings', $campaign_settings);

        $this->campaign_settings = $campaign_settings;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return get_object_vars($this);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return ContactSource
     */
    public function setDescription($description)
    {
        $this->isChanged('description', $description);

        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescriptionPublic()
    {
        return $this->descriptionPublic;
    }

    /**
     * @param string $descriptionPublic
     *
     * @return ContactSource
     */
    public function setDescriptionPublic($descriptionPublic)
    {
        $this->isChanged('descriptionPublic', $descriptionPublic);

        $this->descriptionPublic = $descriptionPublic;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return ContactSource
     */
    public function setName($name)
    {
        $this->isChanged('name', $name);

        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUtmSource()
    {
        return $this->utmSource;
    }

    /**
     * @param mixed $utmSource
     *
     * @return ContactSource
     */
    public function setUtmSource($utmSource)
    {
        $this->isChanged('utmSource', $utmSource);

        $this->utmSource = $utmSource;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param mixed $category
     *
     * @return ContactSource
     */
    public function setCategory($category)
    {
        $this->isChanged('category', $category);

        $this->category = $category;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPublishUp()
    {
        return $this->publishUp;
    }

    /**
     * @param mixed $publishUp
     *
     * @return ContactSource
     */
    public function setPublishUp($publishUp)
    {
        $this->isChanged('publishUp', $publishUp);

        $this->publishUp = $publishUp;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPublishDown()
    {
        return $this->publishDown;
    }

    /**
     * @param mixed $publishDown
     *
     * @return ContactSource
     */
    public function setPublishDown($publishDown)
    {
        $this->isChanged('publishDown', $publishDown);

        $this->publishDown = $publishDown;

        return $this;
    }

    /**
     * @param $list
     *
     * @return $this
     */
    public function setCampaignList($list)
    {
        $this->campaignList = $list;

        return $this;
    }

    /**
     * @return int
     */
    public function getPermissionUser()
    {
        return $this->getCreatedBy();
    }
}
