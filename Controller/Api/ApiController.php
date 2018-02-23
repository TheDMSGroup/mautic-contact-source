<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Controller\Api;

use Mautic\ApiBundle\Controller\CommonApiController;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Util\Codes;

/**
 * Class ContactSourceApiController.
 *
 * @todo - This controller now contains too much business logic. Refactor offloading the logic to a model.
 */
class ApiController extends CommonApiController
{


    /**
     * Primary API endpoint for sources to post contacts.
     *
     * @param Request $request
     * @param null $sourceId
     * @param $main
     * @param null $campaignId
     * @param $object
     * @param $action
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handlerAction(
        Request $request,
        $sourceId = null,
        $main,
        $campaignId = null,
        $object,
        $action
    ) {
        $start = microtime(true);

        $object = strtolower($object);
        if ($object == 'contact') {

            /** @var \MauticPlugin\MauticContactSourceBundle\Model\Api $ApiModel */
            $ApiModel = $this->get('mautic.contactsource.model.api')
                ->setRequest($request)
                ->setContainer($this->container)
                ->setSourceId((int)$sourceId)
                ->setCampaignId((int)$campaignId)
                ->setDebug((bool)$request->headers->get('debug'))
                ->validateAndImportContact();

            $result = $ApiModel->getResult();

        } elseif ($object == 'contacts') {
            $result = [
                'errors' => ['Sorry, the bulk import option is not yet available.'],
                'statusCode' => Codes::HTTP_NOT_IMPLEMENTED,
            ];
        } else {
            $result = [
                'errors' => ['Sorry, posting a ' . $object . ' is not yet available. Did you mean "contact"?'],
                'statusCode' => Codes::HTTP_NOT_IMPLEMENTED,
            ];
        }

        $result['time'] = [
            'completed' => new \DateTime(),
            'duration' => microtime(true) - $start,
        ];
        ksort($result);

        $view = $this->view($result, $result['statusCode'] ? $result['statusCode'] : Codes::HTTP_OK);

        // By default we'll always respond with JSON.
        // @todo - Support any inbound format automatically.
        $view->setFormat('json');

        return $this->handleView($view);
    }

}
