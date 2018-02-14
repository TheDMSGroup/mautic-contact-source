// Logic for the documentation switch.
Mautic.contactserverDocumentation = function () {

    if (typeof window.contactserverTypeLoaded === 'undefined') {
        window.contactserverTypeLoaded = true;
        // Trigger payload tab visibility based on contactServer documentation.
        mQuery('input[name="contactserver[documentation]"]').change(function () {
            var val = mQuery('input[name="contactserver[documentation]"]:checked').val(),
                $target = mQuery('.description-public');
            if (val === "1") {
                $target.removeClass('hide');
            }
            else {
                $target.addClass('hide');
            }
        }).first().parent().parent().find('label.active input:first').trigger('change');

        // Hide the right column when Campaigns tab is open to give more room for
        // table entry.
        var activeTab = '#details';
        mQuery('.contactserver-tab').click(function () {
            var thisTab = mQuery(this).attr('href');
            if (thisTab !== activeTab) {
                activeTab = thisTab;
                if (activeTab === '#campaigns') {
                    // Expanded view.
                    mQuery('.contactserver-left').addClass('col-md-12').removeClass('col-md-9');
                    mQuery('.contactserver-right').addClass('hide');
                }
                else {
                    // Standard view.
                    mQuery('.contactserver-left').removeClass('col-md-12').addClass('col-md-9');
                    mQuery('.contactserver-right').removeClass('hide');
                }
            }
        });
    }
};