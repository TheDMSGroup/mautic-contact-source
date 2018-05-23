Mautic.loadCampaignBudgetsWidget = function () {
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: 'POST',
        data: {
            action: 'plugin:mauticContactSource:campaignBudgetsDashboard',
        },
        cache: true,
        dataType: 'html',
        success: function (response) {
            mQuery('#budget-widget-overlay').hide();
            mQuery('#budgets-widget').html(response).addClass('table-done');
        }
    });
};

mQuery(document).ready(function () {
    if (!mQuery('#budgets-widget').hasClass('table-done')) {
        Mautic.loadCampaignBudgetsWidget();
    }
});

