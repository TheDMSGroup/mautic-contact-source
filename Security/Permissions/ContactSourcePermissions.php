<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Security\Permissions;

use Mautic\CoreBundle\Security\Permissions\AbstractPermissions;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class ContactSourcePermissions.
 */
class ContactSourcePermissions extends AbstractPermissions
{
    /**
     * {@inheritdoc}
     */
    public function __construct($params)
    {
        parent::__construct($params);
        $this->addStandardPermissions('categories');
        $this->addExtendedPermissions('items');
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getName()
    {
        return 'contactsource';
    }

    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface &$builder, array $options, array $data)
    {
        $this->addStandardFormFields('contactsource', 'categories', $builder, $data);
        $this->addExtendedFormFields('contactsource', 'items', $builder, $data);
    }
}
