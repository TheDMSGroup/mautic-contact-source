<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\MauticContactServerBundle\Entity\Stat;
use MauticPlugin\MauticContactServerBundle\Model\ContactServerModel;

/**
 * Class StatSubscriber
 * @package MauticPlugin\MauticContactServerBundle\EventListener
 */
class StatSubscriber extends CommonSubscriber
{
    /**
     * @var ContactServerModel
     */
    protected $model;

    /**
     * FormSubscriber constructor.
     *
     * @param ContactServerModel $model
     */
    public function __construct(ContactServerModel $model)
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
