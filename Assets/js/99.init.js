// General helpers for the Contact Client editor form.
Mautic.contactsourceOnLoad = function () {
    Mautic.contactsourceDocumentation();
    Mautic.contactsourceCampaigns();

    // Hide the right column when Campaigns tab is open to give more room
    // for table entry.
    var activeTab = '#details';
    mQuery('.contactsource-tab').click(function () {
        var thisTab = mQuery(this).attr('href');
        if (thisTab !== activeTab) {
            activeTab = thisTab;
            if (activeTab === '#campaigns') {
                // Expanded view.
                mQuery('.contactsource-left').addClass('col-md-12').removeClass('col-md-9');
                mQuery('.contactsource-right').addClass('hide');
            }
            else {
                // Standard view.
                mQuery('.contactsource-left').removeClass('col-md-12').addClass('col-md-9');
                mQuery('.contactsource-right').removeClass('hide');
            }
        }
    });

    if (mQuery('#contactsource-timeline').length) {
        Mautic.contactsourceTimelineOnLoad();
    }

    if (mQuery('#budgets-widget').length) {
        Mautic.contactsourceLoadCampaignBudgetsWidget();
    }
};

// Confirm utm_source value when manually entered, if value already exists
Mautic.contactSourceUtmSourceConfirm = function() {
    var utmSource = mQuery('#contactsource_utmSource').val();
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: 'POST',
        data: {
            action: 'plugin:mauticContactSource:confirmUtmSource',
            utmSource: utmSource,
        },
        cache: true,
        dataType: 'json',
        success: function (response) {
            if (response == true) {
                mQuery('#contactsource_utmSource').parent().addClass('has-warning').append('<div id="utm-warning" class="help-block">This UTM Source is used by other Contact Sources.</div>');
            } else {
                mQuery('#contactsource_utmSource').parent().removeClass('has-warning');
                mQuery('#utm-warning').remove();

            }
        }
    });
};