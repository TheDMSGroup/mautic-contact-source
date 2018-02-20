![](./Assets/img/source.png)
# Mautic Contact Source

Creates API endpoints for receiving contacts from external sources.

Designed for use by performance marketers who enhance/exchange contacts in mass quantities.
Can optionally be used in tandem with it's sibling [Mautic Contact Client](https://github.com/TheDMSGroup/mautic-contact-client)
to discern real-time acceptance criteria with external clients.

## Installation & Usage

Currently being tested with Mautic `2.12.x`.
If you have success/issues with other versions please report.

1. Install by running `composer require thedmsgroup/mautic-contact-source-bundle`
2. Go to `/s/plugins/reload`
3. After a refresh you will find "Sources" in the main menu, you can dive in and create your first one.

## Endpoints

By default your third parties can POST contacts to urls matching this pattern:

`/source/{sourceId}/campaign/{campaignId}/contact`

## Uses these fine libraries:

* [Bootstrap Slider](https://github.com/seiyria/bootstrap-slider)
* [JSON Editor](https://github.com/json-editor/json-editor)

## Todo
- [ ] Campaigns: A whitelist of campaigns can be selected for the third party to post into.
- [ ] Campaign Limits: Limit the number of contacts accepted to a campaign within defined time frames.
- [ ] Campaign Finance: Track the cost/revenue of contacts upon ingestion per campaign.
- [ ] Campaign Scrub: Support an optional scrub-rate per campaign which affects the cost/revenue.
- [ ] Campaign Required Fields: The fields being used within a campaign should percolate upward to the Source, updating required fields.
- [ ] Self-Documentation: Each source (API) created should auto-generate a public documentation page for a third party. 
- [ ] Notifications: Third parties should be notified when their API changes (such as an added campaign or required field change).
- [ ] Batch Support: Multiple contacts being imported at once for performance.