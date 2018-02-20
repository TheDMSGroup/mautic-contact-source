<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\MauticContactSourceBundle\Entity\Stat;
use MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel;

/**
 * Class StatSubscriber
 * @package MauticPlugin\MauticContactSourceBundle\EventListener
 */
class StatSubscriber extends CommonSubscriber
{
    /**
     * @var ContactSourceModel
     */
    protected $model;

    /**
     * FormSubscriber constructor.
     *
     * @param ContactSourceModel $model
     */
    public function __construct(ContactSourceModel $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [];
    }

}
