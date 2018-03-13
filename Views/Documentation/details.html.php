<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// $view->extend('MauticCoreBundle:Default:content.html.php');
//

// $view['assets']->addScript('app/bundles/InstallBundle/Assets/install/install.js');
?>
<!DOCTYPE html>
<html>
<?php echo $view->render('MauticCoreBundle:Default:head.html.php'); ?>


<!--<!DOCTYPE html>-->
<!--<html lang="en">-->
<!--<head>-->
<!--    <meta charset="utf-8">-->
<!--    <meta http-equiv="X-UA-Compatible" content="IE=edge">-->
<!--    <meta name="description" content="">-->
<!--    <meta name="author" content="">-->
<!---->
<!--    <title>--><?php //echo isset($name) ? $name : '';?><!--</title>-->
<!---->
<!--    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">-->
<!--    <link rel="icon" type="image/x-icon" href="--><?php //echo $assetBase.'/images/favicon.ico';?><!--"/>-->
<!--    <link rel="stylesheet" href="--><?php //echo $assetBase.'/css/libraries.css';?><!--"/>-->
<!--    <link rel="stylesheet" href="--><?php //echo $assetBase.'/css/app.css';?><!--"/>-->
<!---->
<!--    --><?php //echo $view['analytics']->getCode();?>
<!---->
<!--    --><?php //if (isset($stylesheets) && is_array($stylesheets)) :?>
<!--        --><?php //foreach ($stylesheets as $css):?>
<!--            <link rel="stylesheet" type="text/css" href="--><?php //echo $css;?><!--"/>-->
<!--        --><?php //endforeach;?>
<!--    --><?php //endif;?>
<!--</head>-->

<body>
<div class="container">

    <div class="row">
        <div class="col-sm-offset-3 col-sm-6">
            <div class="bg-white pa-lg text-center" style="margin-top:100px;">
                <i class="fa fa-warning fa-5x"></i>
                <h2><?php echo $message; ?></h2>
                <?php if (!empty($submessage)): ?>
                    <h4 class="mt-15"><?php echo $submessage; ?></h4>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>

