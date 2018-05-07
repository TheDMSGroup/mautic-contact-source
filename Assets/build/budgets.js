Mautic.loadCampaignBudgetsTable = function (campaignId) {
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: 'POST',
        data: {
            action: 'plugin:mauticContactSource:campaignBudgetsTab',
            data: campaignId,
        },
        cache: true,
        dataType: 'html',
        success: function (response) {
            mQuery('#budgets-container').html(response).addClass('table-done');
        }
    });
};

mQuery(document).ready(function () {
    if (!mQuery('#budgets-container').hasClass('table-done')) {
        Mautic.loadCampaignBudgetsTable(campaignId);
    }
});

