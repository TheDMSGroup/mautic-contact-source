// General helpers for the Contact Client editor form.
Mautic.contactserverOnLoad = function () {
    mQuery(document).ready(function () {
        Mautic.contactserverDocumentation();
        Mautic.contactserverCampaigns();
    });
};