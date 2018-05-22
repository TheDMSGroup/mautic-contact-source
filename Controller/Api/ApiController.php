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

use FOS\RestBundle\Util\Codes;
use Mautic\ApiBundle\Controller\CommonApiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class ContactSourceApiController.
 */
class ApiController extends CommonApiController
{

    public function initialize(FilterControllerEvent $event)
    {
        $this->model            = $this->getModel('contactsource');
        $this->entityClass      = 'Mautic\ContactSourceBundle\Entity\ContactSource';
        $this->entityNameOne    = 'contactsource';
        $this->entityNameMulti  = 'contactsources';
        $this->serializerGroups = ['contactsourceDetails', 'utmSourceList', 'campaign_settingsList', 'categoryList', 'publishDetails'];

        parent::initialize($event);
    }

    /**
     * Primary API endpoint for sources to post contacts.
     *
     * @param Request $request
     * @param null    $sourceId
     * @param         $main
     * @param null    $campaignId
     * @param         $object
     * @param         $action
     *
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
        if ('contact' == $object) {
            /** @var \MauticPlugin\MauticContactSourceBundle\Model\Api $ApiModel */
            $ApiModel = $this->get('mautic.contactsource.model.api');
            $ApiModel
                ->setRequest($request)
                ->setContainer($this->container)
                ->setSourceId((int) $sourceId)
                ->setCampaignId((int) $campaignId)
                ->validateAndImportContact();

            $result = $ApiModel->getResult();
        } elseif ('contacts' == $object) {
            $result = [
                'errors'     => ['Sorry, the bulk import option is not yet available.'],
                'statusCode' => Codes::HTTP_NOT_IMPLEMENTED,
            ];
        } else {
            $result = [
                'errors'     => ['Sorry, posting a '.$object.' is not yet available. Did you mean "contact"?'],
                'statusCode' => Codes::HTTP_NOT_IMPLEMENTED,
            ];
        }

        $result['time'] = [
            'completed' => new \DateTime(),
            'duration'  => microtime(true) - $start,
        ];
        ksort($result);

        $view = $this->view($result, $result['statusCode'] ? $result['statusCode'] : Codes::HTTP_OK);

        // By default we'll always respond with JSON.
        // @todo - Support any inbound format automatically.
        $view->setFormat('json');

        return $this->handleView($view);
    }
}
