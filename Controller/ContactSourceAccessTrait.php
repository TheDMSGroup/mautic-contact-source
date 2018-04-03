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

use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;

/**
 * Class ContactSourceAccessTrait.
 */
trait ContactSourceAccessTrait
{
    /**
     * Determines if the user has access to the contactSource the note is for.
     *
     * @param        $contactSourceId
     * @param        $action
     * @param bool   $isPlugin
     * @param string $integration
     *
     * @return ContactSource
     */
    protected function checkContactSourceAccess($contactSourceId, $action, $isPlugin = false, $integration = '')
    {
        if (!$contactSourceId instanceof ContactSource) {
            //make sure the user has view access to this contactSource
            $contactSourceModel = $this->getModel('contactSource');
            $contactSource      = $contactSourceModel->getEntity((int) $contactSourceId);
        } else {
            $contactSource   = $contactSourceId;
            $contactSourceId = $contactSource->getId();
        }

        if (null === $contactSource || !$contactSource->getId()) {
            if (method_exists($this, 'postActionRedirect')) {
                //set the return URL
                $page      = $this->get('session')->get(
                    $isPlugin ? 'mautic.'.$integration.'.page' : 'mautic.contactSource.page',
                    1
                );
                $returnUrl = $this->generateUrl(
                    $isPlugin ? 'mautic_plugin_timeline_index' : 'mautic_contact_index',
                    ['page' => $page]
                );

                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => ['page' => $page],
                        'contentTemplate' => $isPlugin ? 'MauticContactSourceBundle:ContactSource:pluginIndex' : 'MauticContactSourceBundle:ContactSource:index',
                        'passthroughVars' => [
                            'activeLink'    => $isPlugin ? '#mautic_plugin_timeline_index' : '#mautic_contact_index',
                            'mauticContent' => 'contactSourceTimeline',
                        ],
                        'flashes'         => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.contactSource.contactSource.error.notfound',
                                'msgVars' => ['%id%' => $contactSourceId],
                            ],
                        ],
                    ]
                );
            } else {
                return $this->notFound('mautic.contact.error.notfound');
            }
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'contactSource:contactSources:'.$action.'own',
            'contactSource:contactSources:'.$action.'other',
            $contactSource->getPermissionUser()
        )
        ) {
            return $this->accessDenied();
        } else {
            return $contactSource;
        }
    }

    /**
     * Returns contactSources the user has access to.
     *
     * @param $action
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function checkAllAccess($action, $limit)
    {
        /** @var ContactSourceModel $model */
        $model = $this->getModel('contactSource');

        //make sure the user has view access to contactSources
        $repo = $model->getRepository();

        // order by lastactive, filter
        $contactSources = $repo->getEntities(
            [
                'filter'         => [],
                'oderBy'         => 'r.last_active',
                'orderByDir'     => 'DESC',
                'limit'          => $limit,
                'hydration_mode' => 'HYDRATE_ARRAY',
            ]
        );

        if (null === $contactSources) {
            return $this->accessDenied();
        }

        foreach ($contactSources as $contactSource) {
            if (!$this->get('mautic.security')->hasEntityAccess(
                'contactSource:contactSources:'.$action.'own',
                'contactSource:contactSources:'.$action.'other',
                $contactSource['createdBy']
            )
            ) {
                unset($contactSource);
            }
        }

        return $contactSources;
    }
}
