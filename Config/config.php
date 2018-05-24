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
    'name'        => 'Contact Source',
    'description' => 'Creates API endpoints for receiving contacts from third parties.',
    'version'     => '1.0',
    'author'      => 'Mautic',

    'routes' => [
        'main'   => [
            'mautic_contactsource_index'           => [
                'path'       => '/contactsource/{page}',
                'controller' => 'MauticContactSourceBundle:ContactSource:index',
            ],
            'mautic_contactsource_action'          => [
                'path'       => '/contactsource/{objectAction}/{objectId}',
                'controller' => 'MauticContactSourceBundle:ContactSource:execute',
            ],
            'mautic_contactsource_timeline_action' => [
                'path'         => '/s/contactsource/timeline/{contactSourceId}/{page}',
                'controller'   => 'MauticContactSourceBundle:Timeline:index',
                'requirements' => [
                    'contactSourceId' => '\d+',
                    'objectId'        => '\d+',
                ],
            ],
        ],
        'api'    => [
            'mautic_api_contactsourcestandard'      => [
                'standard_entity' => true,
                'name'            => 'contactsources',
                'path'            => '/sources',
                'controller'      => 'MauticContactSourceBundle:Api\Api',
            ],
            'mautic_api_contactsource_add_campaign' => [
                'path'       => '/sources/{contactSourceId}/campaign/add',
                'controller' => 'MauticContactSourceBundle:Api\Api:addCampaign',
                'method'     => 'PUT',
            ],
            'mautic_api_contactsource_new_campaign' => [
                'path'       => '/campaigns/new_from_clone',
                'controller' => 'MauticContactSourceBundle:Api\Api:newCampaign',
                'method'     => 'GET',
            ],
        ],
        'public' => [
            'mautic_contactsource_contact'       => [
                'path'       => '/source/{sourceId}/{main}/{campaignId}/{object}/{action}',
                'controller' => 'MauticContactSourceBundle:Api\Api:handler',
                'method'     => ['POST', 'PUT'],
                'defaults'   => [
                    'object'     => 'contact',
                    'action'     => 'add',
                    'main'       => 'campaign',
                    'campaignId' => '',
                    'sourceId'   => '',
                ],
                'arguments'  => [
                    'translator',
                ],
            ],
            'mautic_contactsource_documentation' => [
                'path'       => '/source/{sourceId}/{main}/{campaignId}/{object}/{action}',
                'controller' => 'MauticContactSourceBundle:Public:handler',
                'method'     => 'GET',
                'defaults'   => [
                    'object'     => 'contact',
                    'action'     => 'add',
                    'main'       => 'campaign',
                    'campaignId' => '',
                    'sourceId'   => '',
                ],
            ],
        ],
    ],

    'services' => [
        'events' => [
            'mautic.contactsource.subscriber.stat'          => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\EventListener\StatSubscriber',
                'arguments' => [
                    'mautic.contactsource.model.contactsource',
                ],
            ],
            'mautic.contactsource.subscriber.contactsource' => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\EventListener\ContactSourceSubscriber',
                'arguments' => [
                    'router',
                    'mautic.helper.ip_lookup',
                    'mautic.core.model.auditlog',
                    'mautic.page.model.trackable',
                    'mautic.page.helper.token',
                    'mautic.asset.helper.token',
                    'mautic.form.helper.token',
                    'mautic.contactsource.model.contactsource',
                ],
            ],
            'mautic.contactsource.stats.subscriber'         => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\EventListener\StatsSubscriber',
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.contactsource.customcontent.subscriber' => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\EventListener\CustomContentSubscriber',
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
        ],
        'forms'  => [
            'mautic.contactsource.form.type.contactsourceshow_list' => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\Form\Type\ContactSourceShowType',
                'arguments' => 'router',
                'alias'     => 'contactsourceshow_list',
            ],
            'mautic.contactsource.form.type.contactsource_list'     => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\Form\Type\ContactSourceListType',
                'arguments' => 'mautic.contactsource.model.contactsource',
                'alias'     => 'contactsource_list',
            ],
            'mautic.contactsource.form.type.contactsource'          => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\Form\Type\ContactSourceType',
                'alias'     => 'contactsource',
                'arguments' => 'mautic.security',
            ],
            'mautic.contactsource.form.type.chartfilter'            => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\Form\Type\ChartFilterType',
                'arguments' => 'mautic.factory',
                'alias'     => 'sourcechartfilter',
            ],
        ],
        'models' => [
            'mautic.contactsource.model.contactsource'     => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel',
                'arguments' => [
                    'mautic.form.model.form',
                    'mautic.page.model.trackable',
                    'mautic.helper.templating',
                    'event_dispatcher',
                    'mautic.lead.model.lead',
                ],
            ],
            'mautic.contactsource.model.api'               => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\Model\Api',
                'arguments' => [
                    'event_dispatcher',
                    'doctrine.orm.entity_manager',
                    'mautic.helper.ip_lookup',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.contactsource.model.campaign_settings' => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\Model\CampaignSettings',
                'arguments' => [
                    'mautic.contactsource.model.contactsource',
                ],
            ],
            'mautic.contactsource.model.campaign_event'    => [
                'class'     => 'MauticPlugin\MauticContactSourceBundle\Model\CampaignEventModel',
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
            'mautic.contactsource.model.cache'             => [
                'class' => 'MauticPlugin\MauticContactSourceBundle\Model\Cache',
            ],
        ],
        'other'  => [
            'mautic.contactsource.helper.utmsource' => [
                'class' => 'MauticPlugin\MauticContactSourceBundle\Helper\UtmSourceHelper',
            ],
            'mautic.contactsource.helper.json'      => [
                'class' => 'MauticPlugin\MauticContactSourceBundle\Helper\JSONHelper',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.contactsource' => [
                'route'     => 'mautic_contactsource_index',
                'access'    => 'plugin:contactsource:items:view',
                'id'        => 'mautic_contactsource_root',
                'iconClass' => 'fa-cloud-download',
                'priority'  => 65,
                'checks'    => [
                    'integration' => [
                        'Source' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ],
    ],

    'categories' => [
        'plugin:contactsource' => 'mautic.contactsource',
    ],
];
