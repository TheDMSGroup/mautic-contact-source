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

use FOS\RestBundle\Util\Codes;
use Mautic\CoreBundle\Controller\CommonController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicController extends CommonController
{
    // @todo - Add documentation autogenerator.
    public function getDocumentationAction($sourceId = null, $campaignId = null)
    {
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
                'documentation' => 'documentation to go here',
            ]
        );
    }

    public function handlerAction(
        Request $request,
        $sourceId = null,
        $main,
        $campaignId = null,
        $object,
        $action
    ) {
        /** @var \MauticPlugin\MauticContactSourceBundle\Model\Api $ApiModel */
        $ApiModel = $this->get('mautic.contactsource.model.api')
            ->setRequest($request)
            ->setContainer($this->container)
            ->setSourceId((int) $sourceId)
            ->setCampaignId((int) $campaignId)
            ->setVerbose((bool) $request->headers->get('debug'))
            ->handleInputPublic();

        $result = $ApiModel->getResult(true);

        $response = new Response('', $result['statusCode'] ? $result['statusCode'] : Codes::HTTP_OK);

        return $this->render(
            'MauticContactSourceBundle:Documentation:details.html.php',
            $result,
            $response
        );
    }
}
