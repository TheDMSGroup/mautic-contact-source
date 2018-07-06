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
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use Symfony\Component\HttpFoundation\Request;

class PublicController extends CommonController
{
    use ContactSourceAccessTrait;

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
        $ApiModel = $this->get('mautic.contactsource.model.api');
        $ApiModel
            ->setRequest($request)
            ->setSourceId((int) $sourceId)
            ->setCampaignId((int) $campaignId)
            ->setVerbose(true)
            ->handleInputPublic();

        $result     = $ApiModel->getResult();
        $parameters = [];

        // Get list of campaigns.
        $contactSourceModel         = $this->container->get('mautic.contactsource.model.contactsource');
        $campaigns                  = $contactSourceModel->getCampaignList($ApiModel->getContactSource());
        $parameters['campaignList'] = $campaigns;
        $parameters['campaign']     = null;

        // Get global Source integration settings.
        $parameters['global'] = [];
        /** @var \Mautic\PluginBundle\Helper\IntegrationHelper $helper */
        $helper = $this->get('mautic.helper.integration');
        /** @var \Mautic\PluginBundle\Integration\AbstractIntegration $object */
        $object = $helper->getIntegrationObject('Source');
        if ($object) {
            $objectSettings = $object->getIntegrationSettings();
            if ($objectSettings) {
                $featureSettings      = $objectSettings->getFeatureSettings();
                $parameters['global'] = $featureSettings;
            }
        }
        if (empty($parameters['global']['domain'])) {
            $parameters['global']['domain'] = $this->get('mautic.helper.core_parameters')->getParameter('site_url');
        }
        $parameters['global']['domain'] = rtrim('/', $parameters['global']['domain']);
        $parameters['source']           = isset($result['source']) ? $result['source'] : null;
        $parameters['sourceId']         = $sourceId;
        $parameters['FieldList']        = $ApiModel->getAllowedFields(false);
        $parameters['authenticated']    = $result['authenticated'];
        if (!isset($result['campaign']['name']) && isset($result['source']['name'])) {
            // No valid campaign specified, should show the listing of all campaigns.
            $parameters['title'] = $this->translator->trans(
                'mautic.contactsource.api.docs.source_title',
                [
                    '%source%' => $result['source']['name'],
                ]
            );
        } elseif (isset($result['campaign']['name']) && isset($result['source']['name'])) {
            // Valid campaign is specified, should include hash or direct link to that campaign.
            $parameters['title']          = $this->translator->trans(
                'mautic.contactsource.api.docs.campaign_title',
                [
                    '%source%'   => $result['source']['name'],
                    '%campaign%' => $result['campaign']['name'],
                ]
            );
            $parameters['campaignFields'] = $ApiModel->getCampaignFields(false);
            $parameters['campaign']       = $result['campaign'];
        } else {
            // Completely invalid source.
            $this->notFound('mautic.contactsource.api.docs.not_found');
        }

        // Attempt auth by permissions (assuming logged in user).
        if (!$parameters['authenticated']) {
            $anonymous = $this->get('mautic.security')->isAnonymous();
            if (!$anonymous) {
                $contactSource = $this->checkContactSourceAccess($sourceId, 'view');
                if ($contactSource instanceof ContactSource) {
                    $parameters['authenticated'] = true;
                }
            }
        }

        if (!$parameters['authenticated']) {
            $parameters['title'] = $this->translator->trans('mautic.contactsource.api.docs.auth_title');
            $view                = 'MauticContactSourceBundle:Documentation:auth.html.php';
        } else {
            $view = 'MauticContactSourceBundle:Documentation:details.html.php';
        }

        return $this->render($view, $parameters);
    }
}
