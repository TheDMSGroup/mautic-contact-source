Mautic.loadCampaignBudgetsTable = function () {
    mQuery('#campaign-budgets:not(.table-initialized):first').addClass('table-initialized').each(function() {
        mQuery.getScriptCachedOnce(mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticContactLedgerBundle/Assets/js/datatables.min.js', function () {
            mQuery.getCssOnce(mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticContactLedgerBundle/Assets/css/datatables.min.css', function () {
                mQuery.getCssOnce(mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticContactLedgerBundle/Assets/css/dataTables.fontAwesome.css', function () {
                    // dependent files loaded, now get the data and render
                    mQuery.ajax({
                        url: mauticAjaxUrl,
                        type: 'POST',
                        data: {
                            action: 'plugin:mauticContactSource:campaignBudgets',
                            data: campaignId,
                        },
                        cache: true,
                        dataType: 'json',
                        success: function (response) {
                            mQuery('#campaign-budgets').DataTable({
                                language: {
                                    emptyTable: "No results found for this date range and filters."
                                },
                                data: response.rows,
                                autoFill: true,
                                columns: response.columns,
                                order: [[0, 'asc']],
                                bLengthChange: false,
                                // footerCallback: function (row, data, start, end, display) {
                                //     // Add table footer if it doesnt exist
                                //     var container = mQuery('#campaign-budgets');
                                //     var columns = data[0].length;
                                //     if (mQuery('tr.pageTotal').length == 0) {
                                //         var footer = mQuery('<tfoot></tfoot>');
                                //         var tr = mQuery('<tr class=\'pageTotal\' style=\'font-weight: 600; background: #fafafa;\'></tr>');
                                //         var tr2 = mQuery('<tr class=\'grandTotal\' style=\'font-weight: 600; background: #fafafa;\'></tr>');
                                //         tr.append(mQuery('<td>Page totals</td>'));
                                //         tr2.append(mQuery('<td>Grand totals</td>'));
                                //         for (var i = 0; i < columns; i++) {
                                //             tr.append(mQuery('<td class=\'td-right\'></td>'));
                                //             tr2.append(mQuery('<td class=\'td-right\'></td>'));
                                //         }
                                //         footer.append(tr);
                                //         footer.append(tr2);
                                //         container.append(footer);
                                //         var tableBody = mQuery('#' + container[0].id + ' tbody');
                                //     }
                                //
                                //     if (data && data.length === 0) {
                                //         return;
                                //     }
                                //     try {
                                //         var api = this.api();
                                //
                                //         // Remove the formatting to get integer data for
                                //         // summation
                                //         var intVal = function (i) {
                                //             return typeof i === 'string' ? i.replace(/[\$,]/g, '') * 1 : typeof i === 'number' ? i : 0;
                                //         };
                                //
                                //         var total = mQuery('#' + container[0].id + ' thead th').length;
                                //         var footer1 = mQuery(container).find('tfoot tr:nth-child(1)');
                                //         var footer2 = mQuery(container).find('tfoot tr:nth-child(2)');
                                //         for (var i = 2; i < total; i++) {
                                //             var pageSum = api
                                //                 .column(i + 1, {page: 'current'})
                                //                 .data()
                                //                 .reduce(function (a, b) {
                                //                     return intVal(a) + intVal(b);
                                //                 }, 0);
                                //             var sum = api
                                //                 .column(i + 1)
                                //                 .data()
                                //                 .reduce(function (a, b) {
                                //                     return intVal(a) + intVal(b);
                                //                 }, 0);
                                //             footer1.find('td:nth-child(' + (i) + ')').html(pageSum);
                                //             footer2.find('td:nth-child(' + (i) + ')').html(sum);
                                //         }
                                //     }
                                //     catch (e) {
                                //         console.log(e);
                                //     }
                                // } // FooterCallback
                            }); //.DataTables
                        } //success
                    }); //ajax
                }); //getScriptsCachedOnce - fonteawesome css
            });//getScriptsCachedOnce - datatables css
        });  //getScriptsCachedOnce - datatables js
    });
}; //loadCampaignBudgetsTable


function renderCampaignName (row) {
    return '<a href="./campaigns/view/'+ row[1] +'" class="campaign-name-link" title="'+ row[2] + '">'+ row[2] + '</a>';
}

// getScriptCachedOnce for faster page loads in the backend.
mQuery.getScriptCachedOnce = function (url, callback) {
    if (
        typeof window.getScriptCachedOnce !== 'undefined'
        && window.getScriptCachedOnce.indexOf(url) !== -1
    ) {
        callback();
        return mQuery(this);
    } else {
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
    if (document.createStyleSheet){
        document.createStyleSheet(url);
    }
    else {
        mQuery("head").append(mQuery("<link rel='stylesheet' href='" + url + "' type='text/css' />"));
    }
    callback();
};

mQuery(document).ready(function () {
    Mautic.loadCampaignBudgetsTable();
});
mQuery(document).ajaxComplete(function (event, xhr, settings) {
    Mautic.loadCampaignBudgetsTable();
});

