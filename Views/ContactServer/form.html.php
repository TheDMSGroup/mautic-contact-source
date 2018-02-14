<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'contactserver');

$header = ($entity->getId())
    ?
    $view['translator']->trans(
        'mautic.contactserver.edit',
        ['%name%' => $view['translator']->trans($entity->getName())]
    )
    :
    $view['translator']->trans('mautic.contactserver.new');
$view['slots']->set('headerTitle', $header);

echo $view['assets']->includeScript('plugins/MauticContactServerBundle/Assets/build/contactserver.min.js');
echo $view['assets']->includeStylesheet('plugins/MauticContactServerBundle/Assets/build/contactserver.min.css');

echo $view['form']->start($form);
?>

<!-- start: box layout -->
<div class="box-layout">

    <!-- tab container -->
    <div class="col-md-9 bg-white height-auto bdr-l contactserver-left">
        <div class="">
            <ul class="nav nav-tabs pr-md pl-md mt-10">
                <li class="active">
                    <a href="#details" role="tab" data-toggle="tab" class="contactserver-tab">
                        <?php echo $view['translator']->trans('mautic.contactserver.form.group.details'); ?>
                    </a>
                </li>
                <li>
                    <a href="#campaigns" role="tab" data-toggle="tab" class="contactserver-tab">
                        <?php echo $view['translator']->trans('mautic.contactserver.form.group.campaigns'); ?>
                    </a>
                </li>
            </ul>
        </div>
        <div class="tab-content">
            <!-- pane -->
            <div class="tab-pane fade in active bdr-rds-0 bdr-w-0" id="details">
                <div class="pa-md">
                    <div class="form-group mb-0">
                        <div class="row">
                            <div class="col-md-5">
                                <?php echo $view['form']->row($form['name']); ?>
                            </div>
                            <div class="col-md-2">
                                <?php echo $view['form']->row($form['documentation']); ?>
                            </div>
                            <div class="col-md-5">
                                <?php echo $view['form']->row($form['token']); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['description']); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['description_public']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade bdr-rds-0 bdr-w-0" id="campaigns">
                <div class="pa-md">
                    <div class="form-group mb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['campaign_settings']); ?>
                            </div>
                        </div>
                        <hr class="mnr-md mnl-md">
                    </div>
                </div>
            </div>
            <!--/ #pane -->
        </div>
    </div>
    <!--/ tab container -->

    <!-- container -->
    <div class="col-md-3 bg-white height-auto contactserver-right">
        <div class="pr-lg pl-lg pt-md pb-md">
            <?php
            echo $view['form']->row($form['category']);
            echo $view['form']->row($form['isPublished']);
            echo $view['form']->row($form['publishUp']);
            echo $view['form']->row($form['publishDown']);
            ?>
        </div>
    </div>
    <!--/ container -->
</div>
<!--/ box layout -->

<?php echo $view['form']->end($form); ?>