// Logic for the documentation switch.
Mautic.contactsourceDocumentation = function () {

    var $documentation = mQuery('input[name="contactsource[documentation]"]');
    if ($documentation.length) {

        // Trigger payload tab visibility based on contactSource documentation.
        $documentation.change(function () {
            var val = mQuery('input[name="contactsource[documentation]"]:checked').val(),
                $target = mQuery('.description-public');
            if (val === '1') {
                $target.removeClass('hide');
            }
            else {
                $target.addClass('hide');
            }
        }).first().parent().parent().find('label.active input:first').trigger('change');

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
    }
};