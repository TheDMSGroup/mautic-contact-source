<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;

class PublicController extends CommonController
{
    // @todo - Add documentation autogenerator.
    public function getDocumentationAction($sourceId = null, $campaignId = null){

        // @todo - Check Source existence and published status.

        // @todo - Check if documentation is turned on, if not 403.

        // @todo - Get list of assigned and published Campaigns.

        // @todo - Get list of Source+Campaign required fields.

        // @todo - Get list of Source+Campaign limits.

        // @todo - Get sync status (async/sync).

        // @todo - Generate document.

        return $this->render(
            'MauticContactSourceBundle:Documentation:details.html.php',
            [
                'documentation' => 'documentation to go here'
            ]
        );
    }
}