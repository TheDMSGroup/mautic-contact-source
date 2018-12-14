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