<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<?php if (!empty($showMore)): ?>
    <a href="<?php echo $view['router']->generate('mautic_contactsource_index', ['search' => $searchString]); ?>"
       data-toggle="ajax">
        <span><?php echo $view['translator']->trans('mautic.core.search.more', ['%count%' => $remaining]); ?></span>
    </a>
<?php else: ?>
    <a href="<?php echo $view['router']->generate(
        'mautic_contactsource_action',
        ['objectAction' => 'view', 'objectId' => $source->getId()]
    ); ?>" data-toggle="ajax">
        <span><?php echo $source->getName(); ?></span>
        <?php
        ?>
        <span class="label label-default pull-right" data-toggle="tooltip" data-placement="left"
              title="ID: <?php echo $source->getId(); ?>" ; ?>ID: <?php echo $source->getId(); ?></span>
    </a>
    <div class="clearfix"></div>
<?php endif; ?>