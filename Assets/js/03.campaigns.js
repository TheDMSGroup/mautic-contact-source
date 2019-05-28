// Campaigns field.
Mautic.contactsourceCampaigns = function () {
    var $campaigns = mQuery('#contactsource_campaign_settings:first:not(.campaigns-checked)');
    if ($campaigns.length) {
        // Retrieve the list of available campaigns via Ajax
        var campaigns = {},
            campaignsJSONEditor,
            $campaignsJSONEditor;
        mQuery.ajax({
            url: mauticAjaxUrl,
            type: 'POST',
            data: {
                action: 'plugin:mauticContactSource:getCampaignList'
            },
            dataType: 'json',
            success: function (response) {
                if (typeof response.array !== 'undefined') {
                    campaigns = response.array;
                }
            },
            error: function (request, textStatus, errorThrown) {
                Mautic.processAjaxError(request, textStatus, errorThrown);
            },
            complete: function () {

                // Grab the JSON Schema to begin rendering the form with
                // JSONEditor.
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
                        campaignsJSONEditor.on('change', function (event) {
                            var obj = campaignsJSONEditor.getValue();
                            if (typeof obj === 'object') { 
                                var campaignIds = [];
                                for(var i = 0; i < obj.campaigns.length; i++) { 
                                    var camp = obj.campaigns[i];
                                    if(campaignIds.indexOf(camp.campaignId) >= 0) {
                                        obj.campaigns[i].campaignId = "0"; 
                                        mQuery('[name="root[campaigns][' + i + '][campaignId]"]').val(0).change();
                                        campaignsJSONEditor.setValue(obj);
                                        alert("You shouldn't add the same campaign to a Source more than once, create a different campaign instead.");
                                    }
                                    campaignIds.push(camp.campaignId); 
                                }

                                var raw = JSON.stringify(obj, null, '  ');
                                if (raw.length) {
                                    // Set the textarea.
                                    $campaigns.val(raw);

                                    // Hide the Value when the scope is global.
                                    mQuery('select[name$="[scope]"]:not(.scope-checked)').off('change').on('change', function () {
                                        var $value = mQuery(this).parent().parent().parent().find('input[name$="[value]"]');
                                        if (parseInt(mQuery(this).val()) === 1) {
                                            $value.addClass('hide');
                                        } else {
                                            $value.removeClass('hide');
                                        }
                                    }).addClass('scope-checked').trigger('change');
                                }
                            }
                            // Clickable Campaign headers.
                            $campaignsJSONEditor.find('div[data-schematype="string"][data-schemapath*=".campaignId"] .control-label').each(function () {
                                var campaignForLabel = mQuery(this).parent().find('select:first').val();
                                var label = 'Campaign';

                                if (null !== campaignForLabel && 0 < campaignForLabel) {
                                    label += ' ' + campaignForLabel;

                                    mQuery(this).html('<a href="' + mauticBasePath + '/s/campaigns/edit/' + campaignForLabel + '" target="_blank">' + label + '</a>');
                                }

                            });
                        });

                        $campaigns.addClass('campaigns-checked');
                        $campaignsJSONEditor.show();
                    }
                });

            }
        });
    }
};
