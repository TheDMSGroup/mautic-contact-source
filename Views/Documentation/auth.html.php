<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo isset($title) ? $title : ''; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo $view['assets']->getUrl('media/images/favicon.ico'); ?>"/>
    <link rel="icon" sizes="192x192" href="<?php echo $view['assets']->getUrl('media/images/favicon.ico'); ?>">
    <link rel="apple-touch-icon" href="<?php echo $view['assets']->getUrl('media/images/apple-touch-icon.png'); ?>"/>

    <?php echo $view['assets']->outputSystemStylesheets(); ?>

    <?php echo $view->render('MauticCoreBundle:Default:script.html.php'); ?>
    <?php $view['assets']->outputHeadDeclarations(); ?>
</head>
<body>
<section id="main" role="main">
    <div class="container" style="margin-top:100px;">
        <div class="row">
            <div class="col-lg-4 col-lg-offset-4">
                <div class="panel" name="form-login">
                    <div class="panel-body">
                        <div class="mautic-logo img-circle mb-md text-center">
                            <img src="<?php echo $view['assets']->getUrl(
                                'media/images/mautic_logo_db200.png'
                            ); ?>" class="logo" alt="Logo" style="max-width: 100%; margin-bottom: -3px;"/>
                        </div>
                        <div id="main-panel-flash-msgs">
                            <div id="flashes" class="alert-growl-container">
                            </div>
                        </div>

                        <form class="form-group login-form" name="login" data-toggle="ajax" role="form" method="post">
                            <?php if (empty($sourceId)): ?>
                            <div class="input-group mb-md">

                                <span class="input-group-addon"><i class="fa fa-cloud-download"></i></span>
                                <label for="sourceId" class="sr-only">Source ID:</label>
                                <input type="number" id="sourceId" name="sourceId"
                                       class="form-control input-lg" value="" required autofocus
                                       placeholder="Source ID" />
                            </div>
                            <?php endif; ?>
                            <div class="input-group mb-md">
                                <span class="input-group-addon"><i class="fa fa-key"></i></span>
                                <label for="token" class="sr-only">Token:</label>
                                <input type="hidden" name="documentationAuthAttempt" value="1"/>
                                <input type="token" id="token" name="token"
                                       class="form-control input-lg" required
                                       placeholder="Token"/>
                            </div>
                            <button class="btn btn-lg btn-primary btn-block" type="submit">view documentation</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
</html>
