Mautic.loadCampaignBudgetsTable = function (campaignId) {
    console.log('loading tab content');
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
            console.log(response);
            mQuery('#budgets-container').html(response).addClass('table-done');
        } //success
    }); //ajax
}; //loadCampaignBudgetsTable

mQuery(document).ready(function () {
    if(!mQuery('#budgets-container').hasClass('table-done)')) {Mautic.loadCampaignBudgetsTable(campaignId)};
});

