<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'name'        => 'Mautic Contact Server',
    'description' => 'Sets up API endpoints for contact ingestion from providers.',
    'version'     => '0.1',
    'author'      => 'Mautic',

    'routes' => [
        'main' => [
            'mautic_contactserver_index' => [
                'path'       => '/contactserver/{page}',
                'controller' => 'MauticContactServerBundle:ContactServer:index',
            ],
            'mautic_contactserver_action' => [
                'path'       => '/contactserver/{objectAction}/{objectId}',
                'controller' => 'MauticContactServerBundle:ContactServer:execute',
            ],
            'mautic_contactserver_timeline_action' => [
                'path'       => '/contactserver/timeline/{contactServerId}',
                'controller' => 'MauticContactServerBundle:Timeline:index',
                'requirements' => [
                    'contactServerId' => '\d+',
                ],
            ],
        ],
    ],

    'services' => [
        'events' => [
            'mautic.contactserver.subscriber.stat' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\EventListener\StatSubscriber',
                'arguments' => [
                    'mautic.contactserver.model.contactserver',
                ],
            ],
            'mautic.contactserver.subscriber.contactserver' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\EventListener\ContactServerSubscriber',
                'arguments' => [
                    'router',
                    'mautic.helper.ip_lookup',
                    'mautic.core.model.auditlog',
                    'mautic.page.model.trackable',
                    'mautic.page.helper.token',
                    'mautic.asset.helper.token',
                    'mautic.form.helper.token',
                    'mautic.contactserver.model.contactserver',
                ],
            ],
            'mautic.contactserver.stats.subscriber' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\EventListener\StatsSubscriber',
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
        ],
        'forms' => [
            'mautic.contactserver.form.type.contactservershow_list' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Form\Type\ContactServerShowType',
                'arguments' => 'router',
                'alias'     => 'contactservershow_list',
            ],
            'mautic.contactserver.form.type.contactserver_list' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Form\Type\ContactServerListType',
                'arguments' => 'mautic.contactserver.model.contactserver',
                'alias'     => 'contactserver_list',
            ],
            'mautic.contactserver.form.type.contactserver' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Form\Type\ContactServerType',
                'alias'     => 'contactserver',
                'arguments' => 'mautic.security',
            ],
        ],
        'models' => [
            'mautic.contactserver.model.contactserver' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Model\ContactServerModel',
                'arguments' => [
                    'mautic.form.model.form',
                    'mautic.page.model.trackable',
                    'mautic.helper.templating',
                    'event_dispatcher',
                    'mautic.lead.model.lead',
                ],
            ],
            'mautic.contactserver.model.cache' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Model\Cache',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.contactserver' => [
                'route'     => 'mautic_contactserver_index',
                'access'    => 'plugin:contactserver:items:view',
                'id'        => 'mautic_contactserver_root',
                'iconClass' => 'fa-cloud-download',
                'priority'  => 65,
            ],
        ],
    ],

    'categories' => [
        'plugin:contactserver' => 'mautic.contactserver',
    ],
];
