<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;

class PublicController extends CommonController
{
    // @todo - Add documentation autogenerator.
    public function getDocumentationAction($serverId = null, $campaignId = null){

        // @todo - Check Server existence and published status.

        // @todo - Check if documentation is turned on, if not 403.

        // @todo - Get list of assigned and published Campaigns.

        // @todo - Get list of Server+Campaign required fields.

        // @todo - Get list of Server+Campaign limits.

        // @todo - Get sync status (async/sync).

        // @todo - Generate document.

        return $this->render(
            'MauticContactServerBundle:Documentation:details.html.php',
            [
                'documentation' => 'documentation to go here'
            ]
        );
    }
}