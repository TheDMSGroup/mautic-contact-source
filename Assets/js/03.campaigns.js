// Campaigns field.
Mautic.contactsourceCampaigns = function () {
    var $campaigns = mQuery('#contactsource_campaign_settings');
    if (typeof window.contactsourceCampaignsLoaded === 'undefined' && $campaigns.length) {

        window.contactsourceCampaignsLoaded = true;

        // Retrieve the list of available campaigns via Ajax
        var campaigns = {};
        mQuery.ajax({
            url: mauticAjaxUrl,
            type: 'POST',
            data: {
                action: 'plugin:mauticContactSource:getCampaignList'
            },
            dataType: 'json',
            success: function (response) {
                console.log(response);
                if (typeof response.array !== 'undefined') {
                    campaigns = response.array;
                }
            },
            error: function (request, textStatus, errorThrown) {
                Mautic.processAjaxError(request, textStatus, errorThrown);
            },
            complete: function () {
                var campaignsJSONEditor;

                // Grab the JSON Schema to begin rendering the form with JSONEditor.
                mQuery.ajax({
                    dataType: 'json',
                    cache: true,
                    url: mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticContactSourceBundle/Assets/json/campaigns.json',
                    success: function (data) {
                        var schema = data;

                        if (campaigns.length) {
                            schema.definitions.campaign.properties.campaignId.enumSource[0].source = campaigns;
                        }

                        // Create our widget container for the JSON Editor.
                        var $campaignsJSONEditor = mQuery('<div>', {
                            class: 'contactsource_jsoneditor'
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
                        // mQuery('label[for=contactsource_campaign_settings]').addClass('hide');
                    }
                });

            }
        });
    }
};