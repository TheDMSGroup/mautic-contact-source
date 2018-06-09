# Mautic Contact Source [![Latest Stable Version](https://poser.pugx.org/thedmsgroup/mautic-contact-source-bundle/v/stable)](https://packagist.org/packages/thedmsgroup/mautic-contact-source-bundle) [![License](https://poser.pugx.org/thedmsgroup/mautic-contact-source-bundle/license)](https://packagist.org/packages/thedmsgroup/mautic-contact-source-bundle) [![Build Status](https://travis-ci.org/TheDMSGroup/mautic-contact-source.svg?branch=master)](https://travis-ci.org/TheDMSGroup/mautic-contact-source)

![](./Assets/img/source.png)

Creates API endpoints for receiving contacts from external sources.

Designed for use by performance marketers who enhance/exchange contacts in mass quantities.
Can optionally be used in tandem with it's sibling [Mautic Contact Client](https://github.com/TheDMSGroup/mautic-contact-client)
to discern real-time acceptance criteria with external clients.

## Installation & Usage

Choose a release that matches your version of Mautic.

| Mautic version | Installation                                                        |
| -------------- | ------------------------------------------------------------------- |
| 2.12.x         | `composer require thedmsgroup/mautic-contact-source-bundle "^2.12"` |
| 2.14.x         | `composer require thedmsgroup/mautic-contact-source-bundle "^2.14"` |

1. Install by running the command above or by downloading the appropriate version and unpacking the contents into a folder named `/plugins/MauticContactSourceBundle`
2. Go to `/s/plugins/reload`
3. After a refresh you will find "Sources" in the main menu, you can dive in and create your first one.

## Endpoints

By default your third parties can POST contacts to urls matching this pattern:

`/source/{sourceId}/campaign/{campaignId}/contact`

## Uses these fine libraries:

* [Bootstrap Slider](https://github.com/seiyria/bootstrap-slider)
* [JSON Editor](https://github.com/json-editor/json-editor)

## Features
- [x] Campaigns: A whitelist of campaigns can be selected for the third party to post into.
- [x] Campaign Caps: Limit the number of contacts accepted to a campaign within defined time frames.
- [x] Campaign Finance: Track the cost/revenue of contacts upon ingestion per campaign.
- [x] Campaign Scrub: Support an optional scrub-rate per campaign which affects the cost/revenue.
- [x] Caps: Rules to limit the quantity of successful contacts can be received.
- [x] Logging: Log statistics on contact ingestion, provide charts when viewing a source in the UI.

## Todo
- [ ] Campaign Required Fields: The fields being used within a campaign should percolate upward to the Source, updating required fields.
- [ ] Self-Documentation: Each source (API) created should auto-generate a public documentation page for a third party. 
- [ ] Notifications: Third parties should be notified when their API changes (such as an added campaign or required field change).
- [ ] Batch Support: Import multiple contacts at once for improved performance.

# Review and refactor for 2.14.x

Overrides to refactor:
- processRealTime - Refactor to use executioner instead of modified event model.
-- Also use event dispatching from client plugin instead of session storage.