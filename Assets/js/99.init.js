// General helpers for the Contact Client editor form.
Mautic.contactsourceOnLoad = function () {
    mQuery(document).ready(function () {
        Mautic.contactsourceDocumentation();
        Mautic.contactsourceCampaigns();
    });
};