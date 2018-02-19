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
        'public' => [
            'mautic_contactserver_contact' => [
                'path'       => '/server/{serverId}/{object}/{campaignId}/{action}',
                'controller' => 'MauticContactServerBundle:Api\Api:contact',
                'method'     => ['POST', 'PUT'],
                'defaults'   => [
                    'action' => 'add',
                    'campaignId' => '',
                    'object' => 'campaign',
                ],
                'arguments'  => [
                    'translator'
                ]
            ],
            'mautic_contactserver_documentation' => [
                'path'       => '/server/{serverId}/{object}/{campaignId}/{action}',
                'controller' => 'MauticContactServerBundle:Public:getDocumentation',
                'method'     => 'GET',
                'defaults'   => [
                    'action' => 'add',
                    'campaignId' => '',
                    'object' => 'campaign'
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
            'mautic.contactserver.model.campaign_settings' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Model\CampaignSettings',
                'arguments' => [
                    'mautic.contactserver.model.contactserver',
                ],
            ],
            'mautic.contactserver.model.campaign_event' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Model\CampaignEventModel',
                'arguments' => [
                    'mautic.helper.ip_lookup',
                    'mautic.helper.core_parameters',
                    'mautic.lead.model.lead',
                    'mautic.campaign.model.campaign',
                    'mautic.user.model.user',
                    'mautic.core.model.notification',
                    'mautic.factory',
                ],
            ],
            'mautic.contactserver.model.campaign' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Model\CampaignModel',
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.list',
                    'mautic.form.model.form',
                ],
            ],
            'mautic.contactserver.model.cache' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Model\Cache',
            ],
            'mautic.contactserver.model.contact' => [
                'class'     => 'MauticPlugin\MauticContactServerBundle\Model\ContactModel',
                'arguments' => [
                    'request_stack',
                    'mautic.helper.cookie',
                    'mautic.helper.ip_lookup',
                    'mautic.helper.paths',
                    'mautic.helper.integration',
                    'mautic.lead.model.field',
                    'mautic.lead.model.list',
                    'form.factory',
                    'mautic.lead.model.company',
                    'mautic.category.model.category',
                    'mautic.channel.helper.channel_list',
                    '%mautic.track_contact_by_ip%',
                    'mautic.helper.core_parameters',
                    'mautic.validator.email',
                    'mautic.user.provider',
                ],
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
                'checks'    => [
                    'integration' => [
                        'Server' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ],
    ],

    'categories' => [
        'plugin:contactserver' => 'mautic.contactserver',
    ],
];
