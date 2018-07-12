<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<div id="budget-widget-overlay">
    <div style="position: relative; top: <?php echo $data['height'] / 3; ?>px; left: 45%; index: 1024;display:inline-block; opacity: .5;">
        <i class="fa fa-spinner fa-spin fa-4x"></i>
    </div>
</div>
<div class="tab-pane fade in bdr-w-0 page-list" id="budgets-widget-wrqpper" data-height="<?php echo $data['height']; ?>">
    <table class="table table-striped table-bordered" width="100%" id="budgets-widget">
    </table>
</div>
<script>
    mQuery('#budget-widget-overlay').show();
</script>
<?php
    echo $view['assets']->includeScript('plugins/MauticContactSourceBundle/Assets/build/contactsource.min.js', 'contactsourceLoadCampaignBudgetsWidget', 'contactsourceLoadCampaignBudgetsWidget');
    echo $view['assets']->includeStylesheet('plugins/MauticContactSourceBundle/Assets/build/contactsource.min.css');
?>