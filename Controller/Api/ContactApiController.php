<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Controller\Api;

use FOS\RestBundle\Util\Codes;
use Mautic\ApiBundle\Controller\CommonApiController;
use Mautic\CoreBundle\Helper\InputHelper;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class ContactServerApiController.
 */
class ContactApiController extends CommonApiController
{
    public function initialize(FilterControllerEvent $event)
    {
        // @todo - override default authentication for our /servers/ api so that it simply accepts tokens...
        parent::initialize($event);
    }

    public function postContactAction()
    {
        $response = [];
        $start = microtime(true);

        // @todo - Check Server existence and published status.

        // @todo - Check authentication token against the server.

        // @todo - Check Campaign existence and published status.

        // @todo - Evaluate Server+Campaign required fields.

        // @todo - Evaluate Server+Campaign limits.

        // @todo - Evaluate Server duplicates.

        // @todo - Generate a new contact.

        // @todo - Evaluate scrub rate, and if scrubbed return negative status.

        // @todo - Async: Accept the contact by return status.

        // @todo - Sync: If this Server+Campaign is set to synchronous (and wasn't scrubbed), push the contact through the campaign now.

        // @todo - Sync: Evaluate the result of the campaign workflow and return status.

        $response['execution_time'] = microtime(true) - $start;
        $response['success'] = 1;
        $response['data'] = [];

        $view = $this->view($response, Codes::HTTP_OK);

        return $this->handleView($view);
    }
}
