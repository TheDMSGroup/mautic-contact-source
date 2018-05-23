<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
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
<div class="tab-pane fade in bdr-w-0 page-list" id="budgets-widget" style="overflow:auto; max-height:<?php echo $data['height'] - 39; ?>px;">

</div>
<script>
    mQuery('#budget-widget-overlay').show();
    mQuery.getScript(mauticBaseUrl + 'plugins/MauticContactSourceBundle/Assets/build/budget_widget.js');
</script>