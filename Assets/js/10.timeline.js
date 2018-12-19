// load the ContactSource timeline table on initial page load
Mautic.contactsourceTimelineTable = function () {
    var $tableTarget = mQuery('#timeline-table');
    if ($tableTarget.length && !$tableTarget.hasClass('table-initialized')) {
        $tableTarget.addClass('table-initialized');
        // Make ajax call
        mQuery.ajax({
            url: mauticAjaxUrl,
            type: 'POST',
            data: {
                action: 'plugin:mauticContactSource:ajaxTimeline',
                objectId: Mautic.getEntityId()
            },
            cache: true,
            dataType: 'json',
            success: function (response) {
                if(response.success>0){
                    mQuery('#sourceTransactions-builder-overlay').hide();
                    $tableTarget.append(response.html);
                    Mautic.contactsourceTimelineOnLoad();
                }
            } // end ajax success
        }); // end ajax call
    } // end if tableTarget exists
};

Mautic.contactsourceTimelineOnLoad = function (container, response) {
    // Function for activating Codemirror in transactions
    var codeMirror = function ($el) {
        if (!$el.hasClass('codemirror-active')) {
            var $textarea = $el.find('textarea.codeMirror-json');
            if ($textarea.length) {
                CodeMirror.fromTextArea($textarea[0], {
                    mode: {
                        name: 'javascript',
                        json: true
                    },
                    theme: 'cc',
                    gutters: [],
                    lineNumbers: false,
                    lineWrapping: true,
                    readOnly: true
                });
            }
            $el.addClass('codemirror-active');
        }
    };
    mQuery('#contactsource-timeline a[data-activate-details=\'all\']').on('click', function () {
        if (mQuery(this).find('span').first().hasClass('fa-level-down')) {
            mQuery('#contactsource-timeline a[data-activate-details!=\'all\']').each(function () {
                var detailsId = mQuery(this).data('activate-details'),
                    $details = mQuery('#timeline-details-' + detailsId);
                if (detailsId && $details.length) {
                    $details.removeClass('hide');
                    codeMirror($details);
                    mQuery(this).addClass('active');
                }
            });
            mQuery(this).find('span').first().removeClass('fa-level-down').addClass('fa-level-up');
        }
        else {
            mQuery('#contactsource-timeline a[data-activate-details!=\'all\']').each(function () {
                var detailsId = mQuery(this).data('activate-details'),
                    $details = mQuery('#timeline-details-' + detailsId);
                if (detailsId && $details.length) {
                    $details.addClass('hide');
                    mQuery(this).removeClass('active');
                }
            });
            mQuery(this).find('span').first().removeClass('fa-level-up').addClass('fa-level-down');
        }
    });
    mQuery('#contactsource-timeline .timeline-icon a[data-activate-details!=\'all\']').on('click', function () {
        var detailsId = mQuery(this).data('activate-details');
        if (detailsId && mQuery('#timeline-details-' + detailsId).length) {
            var activateDetailsState = mQuery(this).hasClass('active'),
                $details = mQuery('#timeline-details-' + detailsId);

            if (activateDetailsState) {
                $details.addClass('hide');
                mQuery(this).removeClass('active');
            }
            else {
                $details.removeClass('hide');
                codeMirror($details);
                mQuery(this).addClass('active');
            }
        }
    });


    // add Transaction Totals to the tab
    mQuery('span#TimelineCount').html(contactSource.transactionsTotal);
    mQuery('#transactions-filter-btn').unbind('click').click(function () {
        mQuery('.transaction-filter').toggle();
    });

    // Register Form Submission control events
    mQuery('#timeline-table .pagination-wrapper .pagination a').not('.disabled a').click(function (event) {
        event.preventDefault();
        var arg = this.href.split('?')[0];
        var page = arg.substr(arg.lastIndexOf("page/")+5);
        var filterForm = mQuery('#transactions-filters');
        mQuery('#transactions_page').val(page);
        Mautic.startPageLoadingBar();
        filterForm.submit();
    });

    mQuery('.timeline-header-sort').click(function (event) {
        var filterForm = mQuery('#transactions-filters');
        mQuery('#orderby').val(mQuery(this).data('sort'));
        mQuery('#orderbydir').val(mQuery(this).data('sort_dir'));
        Mautic.startPageLoadingBar();
        filterForm.submit();
    });

    mQuery('.transaction-filter').change(function (event) {
        var filterForm = mQuery('#transactions-filters');
        mQuery('#transactions_page').val(1); // reset page to 1 when filtering
        Mautic.startPageLoadingBar();
        filterForm.submit();
    });

    mQuery('#transactions-filters').submit(function (event) {
        event.preventDefault(); // Prevent the form from submitting via the browser
        Mautic.contactSourceTransactionFormSubmit(this);

    });
};

Mautic.contactSourceTransactionFormSubmit = function(form){
    //merge the sourcechartfilter form to the transaction filter before re-submiting it
    mQuery('#transactions_dateFrom').val(mQuery('#sourcechartfilter_date_from').val());
    mQuery('#transactions_dateTo').val(mQuery('#sourcechartfilter_date_to').val());
    mQuery('#transactions_campaignId').val(mQuery('#sourcechartfilter_campaign').val());
    //merge the filter fields to the transaction filter before re-submiting it
    mQuery('#transaction_message').val(mQuery('#filter-message').val());
    mQuery('#transaction_contact_id').val(mQuery('#filter-contact_id').val());
    mQuery('#transaction_type').val(mQuery('#filter-type').val());
    var form = mQuery(form);
    mQuery.ajax({
        type: form.attr('method'),
        url: mauticAjaxUrl,
        data: {
            action: 'plugin:mauticContactSource:ajaxTimeline',
            filters: form.serializeArray(),
            objectId: contactSource.id
        }
    }).done(function (data) {
        mQuery('div#timeline-table').html(data.html);
        mQuery('span#TimelineCount').html(data.total);
        Mautic.contactsourceTimelineOnLoad();
        Mautic.stopPageLoadingBar();
    }).fail(function (data) {
        // Optionally alert the user of an error here...
        alert('Ooops! Something went wrong');
    });
}

// Export
Mautic.contactSourceTimelineExport = function () {
    // grab timeline filter values to send for export params
    var messageVar = mQuery('#filter-message').val();
    var typeVar = mQuery('#filter-type').val();
    var contact_idVar = mQuery('#filter-contact_id').val();
    var params = jQuery.param({
        message: messageVar,
        type: typeVar,
        contact_id: contact_idVar
    });
    var frame = document.createElement('iframe');
    var src = mauticBaseUrl + 's/contactsource/transactions/export/' + Mautic.getEntityId() + '?' + params;
    frame.setAttribute('src', src);
    frame.setAttribute('style', 'display: none');
    document.body.appendChild(frame);
};
