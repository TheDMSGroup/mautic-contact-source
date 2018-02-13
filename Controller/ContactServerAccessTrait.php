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

use MauticPlugin\MauticContactServerBundle\Entity\ContactServer;

/**
 * Class ContactServerAccessTrait.
 */
trait ContactServerAccessTrait
{
    /**
     * Determines if the user has access to the contactServer the note is for.
     *
     * @param $contactServerId
     * @param $action
     * @param bool $isPlugin
     * @param string $integration
     * @return ContactServer
     */
    protected function checkContactServerAccess($contactServerId, $action, $isPlugin = false, $integration = '')
    {
        if (!$contactServerId instanceof ContactServer) {
            //make sure the user has view access to this contactServer
            $contactServerModel = $this->getModel('contactServer');
            $contactServer = $contactServerModel->getEntity((int)$contactServerId);
        } else {
            $contactServer = $contactServerId;
            $contactServerId = $contactServer->getId();
        }

        if ($contactServer === null || !$contactServer->getId()) {
            if (method_exists($this, 'postActionRedirect')) {
                //set the return URL
                $page = $this->get('session')->get(
                    $isPlugin ? 'mautic.'.$integration.'.page' : 'mautic.contactServer.page',
                    1
                );
                $returnUrl = $this->generateUrl(
                    $isPlugin ? 'mautic_plugin_timeline_index' : 'mautic_contact_index',
                    ['page' => $page]
                );

                return $this->postActionRedirect(
                    [
                        'returnUrl' => $returnUrl,
                        'viewParameters' => ['page' => $page],
                        'contentTemplate' => $isPlugin ? 'MauticContactServerBundle:ContactServer:pluginIndex' : 'MauticContactServerBundle:ContactServer:index',
                        'passthroughVars' => [
                            'activeLink' => $isPlugin ? '#mautic_plugin_timeline_index' : '#mautic_contact_index',
                            'mauticContent' => 'contactServerTimeline',
                        ],
                        'flashes' => [
                            [
                                'type' => 'error',
                                'msg' => 'mautic.contactServer.contactServer.error.notfound',
                                'msgVars' => ['%id%' => $contactServerId],
                            ],
                        ],
                    ]
                );
            } else {
                return $this->notFound('mautic.contact.error.notfound');
            }
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'contactServer:contactServers:'.$action.'own',
            'contactServer:contactServers:'.$action.'other',
            $contactServer->getPermissionUser()
        )
        ) {
            return $this->accessDenied();
        } else {
            return $contactServer;
        }
    }

    /**
     * Returns contactServers the user has access to.
     *
     * @param $action
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function checkAllAccess($action, $limit)
    {
        /** @var ContactServerModel $model */
        $model = $this->getModel('contactServer');

        //make sure the user has view access to contactServers
        $repo = $model->getRepository();

        // order by lastactive, filter
        $contactServers = $repo->getEntities(
            [
                'filter' => [
                    'force' => [
                        [
                            'column' => 'l.date_identified',
                            'expr' => 'isNotNull',
                        ],
                    ],
                ],
                'oderBy' => 'r.last_active',
                'orderByDir' => 'DESC',
                'limit' => $limit,
                'hydration_mode' => 'HYDRATE_ARRAY',
            ]
        );

        if ($contactServers === null) {
            return $this->accessDenied();
        }

        foreach ($contactServers as $contactServer) {
            if (!$this->get('mautic.security')->hasEntityAccess(
                'contactServer:contactServers:'.$action.'own',
                'contactServer:contactServers:'.$action.'other',
                $contactServer->getOwner()
            )
            ) {
                unset($contactServer);
            }
        }

        return $contactServers;
    }
}
