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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class ApiController.
 */
class ApiController extends CommonApiController
{
    public function initialize(FilterControllerEvent $event)
    {
        $this->model            = $this->getModel('contactsource');
        $this->entityClass      = 'MauticPlugin\MauticContactSourceBundle\Entity\ContactSource';
        $this->entityNameOne    = 'contactsource';
        $this->entityNameMulti  = 'contactsources';
        $this->serializerGroups = [
            'contactsourceDetails',
            'utmSourceList',
            'campaign_settingsList',
            'categoryList',
            'publishDetails',
        ];

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

    /**
     * @param $campaignId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listCampaignSourcesAction($campaignId)
    {
        $campaignId     = intval($campaignId);
        $table_alias    = $this->model->getRepository()->getTableAlias();
        $campaignFilter = [
            'column' => $table_alias.'.campaign_settings',
            'expr'   => 'like', //like
            'value'  => '%"campaignId": "'.$campaignId.'"%',
        ];

        $this->extraGetEntitiesArguments = [
            'filter' => [
                'where' => [
                    $campaignFilter,
                ],
            ],
        ];

        return $this->getEntitiesAction();
    }

    /**
     * Convert posted parameters into what the form needs in order to successfully bind.
     *
     * @param $parameters
     * @param $entity
     * @param $action
     *
     * @return mixed
     */
    protected function prepareParametersForBinding($parameters, $entity, $action)
    {
        if (false == isset($parameters['campaign_settings'])) { // only do this for new / edit sources API calls
            // there is no defaultValues for token, so grab it from the __construct supplied instance of the entity
            if (!empty($entity->getToken())) {
                $parameters['token'] = $entity->getToken();
            }

            // documentation (boolean public page) can not be null because of SQL constraint. Add it here if it is.
            if (null == $parameters['documentation'] || '' == $parameters['documentation']) {
                $parameters['documentation'] = 0;
            }
        }

        return $parameters;
    }

    /**
     * Opportunity to analyze and do whatever to an entity before going through serializer.
     *
     * @param        $entity
     * @param string $action
     *
     * @return mixed
     */
    protected function preSerializeEntity(&$entity, $action = 'view')
    {
        // deconstruct the campaign_settings blob and merge campaign entities in.
        $list             = [];
        $campaignSettings = json_decode($entity->getCampaignSettings(), true);
        $campaignModel    = $this->container->get('mautic.campaign.model.campaign');
        if (!empty($campaignSettings)) {
            foreach ($campaignSettings as $campaignSetting) {
                foreach ($campaignSetting as $setting) {
                    // get campaign name or other fields to merge with this data.
                    $campaign                       = $campaignModel->getEntity($setting['campaignId']);
                    $campaignName                   = $campaign->getName();
                    $campaignDescription            = $campaign->getDescription();
                    $setting['campaignName']        = $campaignName;
                    $setting['campaignDescription'] = $campaignDescription;
                    $list[$setting['campaignId']]   = $setting;
                }
            }
            $entity->setCampaignList($list);
        }
    }

    /**
     * @param $id
     *
     * @return array|bool|\Symfony\Component\HttpFoundation\Response
     */
    public function addCampaignAction($contactSourceId)
    {
        $campaignSettingsModel = $this->container->get('mautic.contactsource.model.campaign_settings');
        $parameters            = $this->request->request->all();

        if (!isset($parameters['campaignId']) || empty($parameters['campaignId'])) {
            return $this->notFound();
        }

        $valid = $this->validateBatchPayload($parameters);
        if ($valid instanceof Response) {
            return $valid;
        }

        $entity = $this->model->getEntity($contactSourceId);

        if (!$this->checkEntityAccess($entity, 'edit')) {
            return $this->accessDenied();
        }

        $campaignModel  = $this->container->get('mautic.campaign.model.campaign');
        $campaignEntity = $campaignModel->getEntity($parameters['campaignId']);
        if (empty($campaignEntity)) {
            return $this->returnError('mautic.contactsource.api.add_campaign.not_found', Codes::HTTP_BAD_REQUEST);
        }

        $campaignSettingsModel->setContactSource($entity);
        $campaignSettings = $campaignSettingsModel->getCampaignSettings();
        $existingCampaign = $campaignSettingsModel->getCampaignSettingsById($parameters['campaignId']);

        $requestCampaign             = new \stdClass();
        $requestCampaign->campaignId = $parameters['campaignId'];
        $requestCampaign->cost       = isset($parameters['cost']) ? number_format(
            (float) $parameters['cost'],
            3,
            '.',
            ''
        ) : 0;
        $requestCampaign->realTime   = isset($parameters['realTime']) && ('false' !== $parameters['realTime']) ? true : false;
        $requestCampaign->scrubRate  = isset($parameters['scrubRate']) ? (int) $parameters['scrubRate'] : 0;
        $requestCampaign->limits     = [];

        if (empty($existingCampaign)) {
            $campaignSettings->campaigns[] = $requestCampaign;
        } else {
            return $this->returnError('mautic.contactsource.api.add_campaign.bad_request', Codes::HTTP_BAD_REQUEST);
        }
        $campaignSettingsJSON = json_encode($campaignSettings);
        $entity->setCampaignSettings($campaignSettingsJSON);

        $this->model->saveEntity($entity);

        $headers = [];
        //return the newly created entities location if applicable

        $route               = (null !== $this->get('router')->getRouteCollection()->get(
                'mautic_api_'.$this->entityNameMulti.'_getone'
            ))
            ? 'mautic_api_'.$this->entityNameMulti.'_getone' : 'mautic_api_get'.$this->entityNameOne;
        $headers['Location'] = $this->generateUrl(
            $route,
            array_merge(['id' => $entity->getId()], $this->routeParams),
            true
        );

        $this->preSerializeEntity($entity, 'edit');

        $view = $this->view([$this->entityNameOne => $entity], Codes::HTTP_OK, $headers);

        $this->setSerializationContext($view);

        return $this->handleView($view);
    }
}
