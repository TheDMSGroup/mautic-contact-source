<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Model;

use MauticPlugin\MauticContactServerBundle\Exception\ContactServerException;
use MauticPlugin\MauticContactServerBundle\Entity\ContactServer;
use MauticPlugin\MauticContactServerBundle\Helper\JSONHelper;
use MauticPlugin\MauticContactServerBundle\Entity\Stat;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class Schedule
 * @package MauticPlugin\MauticContactServerBundle\Model
 */
class Schedule
{

    /**
     * @var \DateTimeZone $timezone
     */
    protected $timezone;

    /** @var \Datetime $now */
    protected $now;

    /** @var ContactServer $contactServer */
    protected $contactServer;

    /** @var Container */
    protected $container;

    /**
     * Schedule constructor.
     * @param ContactServer $contactServer
     * @param $container
     */
    public function __construct(ContactServer $contactServer, $container)
    {
        $this->contactServer = $contactServer;
        $this->container = $container;
        $this->setTimezone();
    }

    /**
     * Set Server timezone, defaulting to Mautic or System as is relevant.
     */
    private function setTimezone()
    {
        if (!$this->timezone) {
            $timezone = $this->contactServer->getScheduleTimezone();
            if (!$timezone) {
                $timezone = $this->container->get('mautic.helper.core_parameters')->getParameter(
                    'default_timezone'
                );
                $timezone = !empty($timezone) ? $timezone : date_default_timezone_get();
            }
            $this->timezone = new \DateTimeZone($timezone);
        }
    }

    /**
     * @param ContactServer $contactServer
     * @throws ContactServerException
     * @throws \Exception
     */
    public function evaluateHours(ContactServer $contactServer)
    {
        $jsonHelper = new JSONHelper();
        $hours = $jsonHelper->decodeArray($contactServer->getScheduleHours(), 'ScheduleHours');

        if ($hours) {
            $now = $this->getNow();
            $timezone = $this->getTimezone();

            $day = intval($now->format('N')) - 1;
            if (isset($hours[$day])) {
                if (
                    isset($hours[$day]->isActive)
                    && !$hours[$day]->isActive
                ) {
                    throw new ContactServerException(
                        'This contact server does not allow contacts on a '.$now->format('l').'.',
                        0,
                        null,
                        Stat::TYPE_SCHEDULE
                    );
                } else {
                    $timeFrom = !empty($hours[$day]->timeFrom) ? $hours[$day]->timeFrom : '00:00';
                    $timeTill = !empty($hours[$day]->timeTill) ? $hours[$day]->timeTill : '23:59';
                    $startDate = \DateTime::createFromFormat('H:i', $timeFrom, $timezone);
                    $endDate = \DateTime::createFromFormat('H:i', $timeTill, $timezone);
                    if (!($now > $startDate && $now < $endDate)) {
                        throw new ContactServerException(
                            'This contact server does not allow contacts during this time of day.',
                            0,
                            null,
                            Stat::TYPE_SCHEDULE
                        );
                    }
                }
            }
        }
    }

    /**
     * @return \Datetime
     */
    private function getNow()
    {
        if (!$this->now) {
            $now = new \Datetime();
            $now->setTimezone($this->timezone);
            $this->now = $now;
        }

        return $this->now;
    }

    /**
     * @return \DateTimeZone
     */
    private function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * @param ContactServer $contactServer
     * @throws ContactServerException
     * @throws \Exception
     */
    public function evaluateExclusions(ContactServer $contactServer)
    {

        // Check dates of exclusion (if there are any).
        $jsonHelper = new JSONHelper();
        $exclusions = $jsonHelper->decodeArray($contactServer->getScheduleExclusions(), 'ScheduleExclusions');
        if ($exclusions) {
            $now = $this->getNow();

            // Fastest way to compare dates is by string.
            $todaysDateString = $now->format('Y-m-d');
            foreach ($exclusions as $exclusion) {
                if (!empty($exclusion->value)) {
                    $dateString = trim(str_ireplace('yyyy-', '', $exclusion->value));
                    $segments = explode('-', $dateString);
                    $segmentCount = count($segments);
                    if ($segmentCount == 3) {
                        $year = !empty($segments[0]) ? str_pad($segments[0], 4, '0', STR_PAD_LEFT) : $now->format('Y');
                        $month = !empty($segments[1]) ? str_pad($segments[1], 2, '0', STR_PAD_LEFT) : $now->format('m');
                        $day = !empty($segments[2]) ? str_pad($segments[2], 2, '0', STR_PAD_LEFT) : $now->format('d');
                    } elseif ($segmentCount == 2) {
                        $year = $now->format('Y');
                        $month = !empty($segments[0]) ? str_pad($segments[0], 2, '0', STR_PAD_LEFT) : $now->format('m');
                        $day = !empty($segments[1]) ? str_pad($segments[1], 2, '0', STR_PAD_LEFT) : $now->format('d');
                    } else {
                        continue;
                    }
                    $dateString = $year.'-'.$month.'-'.$day;
                    if ($dateString == $todaysDateString) {
                        throw new ContactServerException(
                            'This contact server does not allow contacts on the date '.$dateString.'.',
                            0,
                            null,
                            Stat::TYPE_SCHEDULE
                        );
                    }
                }
            }
        }
    }
}