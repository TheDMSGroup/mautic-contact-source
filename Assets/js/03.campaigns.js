// Campaigns field.
Mautic.contactserverCampaigns = function () {
    var $campaigns = mQuery('#contactserver_campaign_settings');
    if (typeof window.contactserverCampaignsLoaded === 'undefined' && $campaigns.length) {

        window.contactserverCampaignsLoaded = true;

        var campaignsJSONEditor;

        // Grab the JSON Schema to begin rendering the form with JSONEditor.
        mQuery.ajax({
            dataType: 'json',
            cache: true,
            url: mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticContactServerBundle/Assets/json/campaigns.json',
            success: function (data) {
                var schema = data;

                // Create our widget container for the JSON Editor.
                var $campaignsJSONEditor = mQuery('<div>', {
                    class: 'contactserver_jsoneditor'
                }).insertBefore($campaigns);

                // Instantiate the JSON Editor based on our schema.
                campaignsJSONEditor = new JSONEditor($campaignsJSONEditor[0], {
                    schema: schema,
                    disable_collapse: true
                });

                $campaigns.change(function () {
                    // Load the initial value if applicable.
                    var raw = mQuery(this).val(),
                        obj;
                    if (raw.length) {
                        try {
                            obj = mQuery.parseJSON(raw);
                            if (typeof obj === 'object') {
                                campaignsJSONEditor.setValue(obj);
                            }
                        }
                        catch (e) {
                            console.warn(e);
                        }
                    }
                }).trigger('change');

                // Persist the value to the JSON Editor.
                campaignsJSONEditor.on('change', function () {
                    var obj = campaignsJSONEditor.getValue();
                    if (typeof obj === 'object') {
                        var raw = JSON.stringify(obj, null, '  ');
                        if (raw.length) {
                            // Set the textarea.
                            $campaigns.val(raw);
                        }
                    }
                });

                $campaigns.addClass('hide');
                $campaignsJSONEditor.show();
                // mQuery('label[for=contactserver_campaign_settings]').addClass('hide');
            }
        });

    }
};