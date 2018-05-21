Mautic.loadCampaignBudgetsWidget = function (widgetHeight) {
    var $sourcetarget = mQuery('#budgets-widget');
    if ($sourcetarget.length) {
        mQuery('#budgets-widget:first:not(.table-initialized)').addClass('table-initialized').each(function () {
            mQuery.getScriptCachedOnce(mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticContactLedgerBundle/Assets/js/datatables.min.js', function () {
                mQuery.getCssOnce(mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticContactLedgerBundle/Assets/css/datatables.min.css', function () {
                    mQuery.getCssOnce(mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticContactLedgerBundle/Assets/css/dataTables.fontAwesome.css', function () {
                        // dependent files loaded, now get the data and render
                        mQuery.ajax({
                            url: mauticAjaxUrl,
                            type: 'POST',
                            data: {
                                action: 'plugin:mauticContactSource:campaignBudgetsDashboard',
                            },
                            cache: true,
                            dataType: 'json',
                            success: function (response) {
                                console.log(response);
                                var rowCount = Math.floor((widgetHeight - 140) / 40);
                                mQuery('#budgets-widget').DataTable({
                                    language: {
                                        emptyTable: 'No results found for this date range and filters.'
                                    },
                                    data: response.rows,
                                    autoFill: true,
                                    columns: response.columns,
                                    order: [[2, 'asc'], [4, 'asc']],
                                    bLengthChange: false,
                                    lengthMenu: [[rowCount]],
                                    columnDefs: [
                                        {
                                            render: function (data, type, row) {
                                                return renderStatusToggle(row[0]);
                                            },
                                            targets: 0
                                        },
                                        {
                                            // campaign name and link
                                            render: function (data, type, row) {
                                                return renderLink(row[1], row[2]);
                                            },
                                            targets: 1
                                        },
                                        {
                                            // source name and link
                                            render: function (data, type, row) {
                                                return renderLink(row[3], row[4]);
                                            },
                                            targets: 3
                                        },
                                        {
                                            // forecast
                                            render: function (data, type, row) {
                                                return renderForecast(row[8], row[9]);
                                            },
                                            targets: 8
                                        },
                                        {visible: false, targets: [2, 4, 9]},
                                        {width: '5%', targets: [0]},
                                        {width: '45%', targets: [5]},
                                    ]
                                });
                                mQuery('#budget-widget-overlay').hide();
                            }
                        });
                    }); //getScriptsCachedOnce - fonteawesome css
                });//getScriptsCachedOnce - datatables css
            });  //getScriptsCachedOnce - datatables js
        });
    }
};

function renderStatusToggle (icon) {
    var color = '#cb4641';
    if (icon == 'fa-heartbeat') {
        color = '#2a84c5';
    }
    return '<i style="color:' + color + ';" class="fa ' + icon + '"></i>';
};

function renderLink (label, link) {
    return '<a href="' + link + '">' + label + '</a>';
};

function renderForecast(value, forecast) {
    // danger or success
    var forecastClass = 'success';
    var forecastInt = parseInt(forecast);
    console.log(forecastInt);
    if(forecastInt >=90){
        forecastClass = 'danger';
    }
    return '<span class="label label-' + forecastClass + '">' + value + '</span>&nbsp;<span class="label label-' + forecastClass + '">' + forecast + '</span>';
}

mQuery(document).ready(function () {
    if (!mQuery('#budgets-widget').hasClass('table-done')) {
        Mautic.loadCampaignBudgetsWidget(widgetHeight);
    }
});

// getScriptCachedOnce for faster page loads in the backend.
mQuery.getScriptCachedOnce = function (url, callback) {
    if (
        typeof window.getScriptCachedOnce !== 'undefined'
        && window.getScriptCachedOnce.indexOf(url) !== -1
    ) {
        callback();
        return mQuery(this);
    }
    else {
        return mQuery.ajax({
            url: url,
            dataType: 'script',
            cache: true
        }).done(function () {
            if (typeof window.getScriptCachedOnce === 'undefined') {
                window.getScriptCachedOnce = [];
            }
            window.getScriptCachedOnce.push('url');
            callback();
        });
    }
};

// getScriptCachedOnce for faster page loads in the backend.
mQuery.getCssOnce = function (url, callback) {
    if (document.createStyleSheet) {
        document.createStyleSheet(url);
    }
    else {
        mQuery('head').append(mQuery('<link rel=\'stylesheet\' href=\'' + url + '\' type=\'text/css\' />'));
    }
    callback();
};

