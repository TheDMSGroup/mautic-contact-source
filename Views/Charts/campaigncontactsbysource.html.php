<div class="pa-md">
    <div class="row">
        <div class="col-sm-12">
            <div class="panel">
                <div class="panel-body box-layout">
                    <div class="col-md-12 va-m">
                        <h5 class="text-white dark-md fw-sb mb-xs">
                            <span class="fa fa-line-chart"></span>
                            <?php echo $view['translator']->trans('mautic.contactsource.campaigncontactsbysource'); ?>
                        </h5>
                    </div>
                </div>
                <div class="pt-0 pl-15 pb-10 pr-15">
                    <?php echo $view->render(
                        'MauticCoreBundle:Helper:chart.html.php',
                        ['chartData' => $campaignSourceData, 'chartType' => 'line', 'chartHeight' => 300]
                    ); ?>
                </div>
            </div>
        </div>
    </div>
</div>