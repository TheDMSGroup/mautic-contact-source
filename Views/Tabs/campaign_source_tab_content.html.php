<?php
/**
 * Created by PhpStorm.
 * User: scottshipman
 * Date: 4/4/18
 * Time: 3:07 PM.
 */
?>
<script>
    var campaignId = {campaignId: <?php echo $campaign->getId(); ?>,};
</script>
<div class="tab-pane fade in bdr-w-0 page-list" id="budgets-container">

    <script src="/plugins/MauticContactSourceBundle/Assets/js/budgets.js"></script>

    <div class="chart-wrapper">
        <div class="pt-sd pr-md pb-md pl-md">
            <div id="campaign-budgets-table">
                <!-- Budgets for Campaign -->
                <div class="responsive-table">
                    <table id="campaign-budgets" class="table table-striped table-bordered" width="100%">
                    </table>
                </div>
                <!--/ Budgets for Campaign -->
            </div>
        </div>
    </div>



    <div class="clearfix"></div>
</div>