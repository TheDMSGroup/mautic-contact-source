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
use Symfony\Component\HttpFoundation\Request;

class PublicController extends CommonController
{
    /**
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
        /** @var \MauticPlugin\MauticContactSourceBundle\Model\Api $ApiModel */
        $ApiModel = $this->get('mautic.contactsource.model.api')
            ->setRequest($request)
            ->setContainer($this->container)
            ->setSourceId((int) $sourceId)
            ->setCampaignId((int) $campaignId)
            ->setVerbose(true)
            ->handleInputPublic();

        $result     = $ApiModel->getResult();
        $parameters = [];

        // get list of campaigns
        $contactSourceModel         = $this->container->get('mautic.contactsource.model.contactsource');
        $campaigns                  = $contactSourceModel->getCampaignList($ApiModel->getContactSource());
        $parameters['campaignList'] = $campaigns;
        $parameters['campaign']     = null;

        // get global Source integration settings
        $global = [];

        $parameters['global'] = $global;
        $parameters['source'] = $result['source'];

        if (!isset($result['campaign']['name'])) {
            // No valid campaign specified, should show the listing of all campaigns.
            $view                = 'MauticContactSourceBundle:Documentation:details.html.php';
            $parameters['title'] = $this->translator->trans(
                'mautic.contactsource.api.docs.source_title',
                [
                    '%source%' => $result['source']['name'],
                ]
            );
        } elseif (isset($result['campaign']['name'])) {
            // Valid campaign is specified, should include hash or direct link to that campaign.
            $view                 = 'MauticContactSourceBundle:Documentation:details.html.php';
            $parameters['title']  = $this->translator->trans(
                'mautic.contactsource.api.docs.campaign_title',
                [
                    '%source%'   => $result['source']['name'],
                    '%campaign%' => $result['campaign']['name'],
                ]
            );
            $parameters['campaignFields'] = $ApiModel->getAllowedFields(false);
            $parameters['campaign']       = $result['campaign'];
        } else {
            // Completely invalid source.
            // @todo - We should respond with a 404 most likely.
            $this->notFound('mautic.contactsource.api.docs.not_found');
        }

        return $this->render($view, $parameters);
    }
}
