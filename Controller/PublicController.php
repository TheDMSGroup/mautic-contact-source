<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
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

    const COMMON_FIELDS = [
        'firstname' => 'Greg',
        'lastname'  => 'Scott',
        'email'     => 'gregscott@email.com',
    ];

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
        $parameters    = [];
        $campaigns     = [];

        $parameters['commonFields'] = self::COMMON_FIELDS;

        if (!$this->get('mautic.security')->isAnonymous()) {
            $contactSource = $this->checkContactSourceAccess($sourceId, 'view');
            if ($contactSource instanceof ContactSource) {
                $token               = $contactSource->getToken();
                $parameters['token'] = $token;
                $request->request->set('token', $token);
            }
        }

        /** @var \MauticPlugin\MauticContactSourceBundle\Model\Api $ApiModel */
        $ApiModel = $this->get('mautic.contactsource.model.api');
        $ApiModel
            ->setRequest($request)
            ->setSourceId((int) $sourceId)
            ->setCampaignId((int) $campaignId)
            ->setVerbose(true)
            ->handleInputPublic();

        $result        = $ApiModel->getResult();

        $contactSource = $ApiModel->getContactSource();
        if ($contactSource) {
            $contactSourceModel = $this->container->get('mautic.contactsource.model.contactsource');
            $campaigns          = $contactSourceModel->getCampaignList($ApiModel->getContactSource());
        }
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
        $parameters['FieldList']        = $ApiModel->getAllowedFields(true);
        $parameters['authenticated']    = $result['authenticated'];

        // Remove fields in Excluded Groups (set in integration settings)
        if (!empty($featureSettings['field_group_exclusions'])) {
            $excludedGroups = explode(',', $featureSettings['field_group_exclusions']);

            foreach ($parameters['FieldList'] as $fieldIndex => $fieldValues) {
                if (in_array($fieldValues['group'], $excludedGroups)) {
                    unset($parameters['FieldList'][$fieldIndex]);
                }
            }
        }

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

        if (!$parameters['authenticated']) {
            $parameters['title'] = $this->translator->trans('mautic.contactsource.api.docs.auth_title');
            $view                = 'MauticContactSourceBundle:Documentation:auth.html.php';
        } else {
            $view = 'MauticContactSourceBundle:Documentation:details.html.php';
        }

        return $this->render($view, $parameters);
    }
}
