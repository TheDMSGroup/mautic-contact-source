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
<script>
    var campaignId = {campaignId: <?php echo $campaign->getId(); ?>};
    mQuery.getScript(mauticBaseUrl + 'plugins/MauticContactSourceBundle/Assets/build/budgets.js');
</script>
<div class="tab-pane fade in bdr-w-0 page-list" id="budgets-container">
</div>