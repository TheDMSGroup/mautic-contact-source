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
?>
<html>
<head>
    <title><?php echo $name ?? null; ?></title>

    <?php echo $view['analytics']->getCode(); ?>

    <?php if (isset($stylesheets) && is_array($stylesheets)) : ?>
        <?php foreach ($stylesheets as $css): ?>
            <link rel="stylesheet" type="text/css" href="<?php echo $css; ?>" />
        <?php endforeach; ?>
    <?php endif; ?>

</head>
<body>
<?php echo $content ?? null; ?>
<?php // @todo - Documentation screen to go here. ?>
</body>
</html>