# Campaign and Source API Documentation

## Getting Started

words...

### Prerequisites

words ...

### Authorization
Engage supports Basic Authorization or OAuth 2 (recommended).


### Endpoints and Base URL
The Base API Endpoint for the Engage Production Sandbox is
 
 ```pre.dmsengage.com/api ```

All responses are JSON encoded.

### Error Handling
If an OAuth error is encountered, it’ll be a JSON encoded array similar to:
 ```
 {
      "error": "invalid_grant",
      "error_description": "The access token provided has expired."
    }
 ```
    
If a system error is encountered, it’ll be a JSON encoded array similar to:  
```
{
    "error": {
        "message": "You do not have access to the requested area/action.",
        "code": 403
    }
}
```

## API Endpoints

### List Campaigns 
HTTP Request
```GET /campaigns```

Query Parameters
```
    search	String or search command to filter entities by.
    start	Starting row for the entities returned. Defaults to 0.
    limit	Limit number of entities to return. Defaults to the system configuration for pagination (30).
    orderBy	Column to sort by. Can use any column listed in the response.
    orderByDir	Sort direction: asc or desc.
    published	Only return currently published entities.
    minimal	Return only array of entities without additional lists in it.
```

Example Request
```
GET /api/campaigns?limit=1&amp;minimal=true HTTP/1.1
Host: pre.dmsengage.com
Authorization: Basic QVBJX1RFU1Q6bWF1dGlj==
```

Response
```Expected Response Code: 200```
A JSON string of campaigns

```{
       "total": 28,
       "campaigns": {
           "1": {
               "isPublished": true,
               "dateAdded": "2017-12-18T22:38:48+00:00",
               "dateModified": "2018-05-07T19:35:46+00:00",
               "createdBy": 3,
               "createdByUser": "Matt Goodman",
               "modifiedBy": 6,
               "modifiedByUser": "Jonathan Katz",
               "id": 1,
               "name": "Simply Jobs",
               "category": {
                   "createdByUser": "Matt Goodman",
                   "modifiedByUser": null,
                   "id": 2,
                   "title": "jobs",
                   "alias": "jobs",
                   "description": "Jobs sector",
                   "color": "05f571",
                   "bundle": "campaign"
               },
               "description": "DMS's owned and operated jobs portal (http:\/\/simplyjobs.com)"
           }
       }
   }
```


### Get a Campaign By ID
HTTP Request
```GET /campaigns/{ID}```

Response
```Expected Response Code: 200```
A JSON string of campaigns (see example above)

### Create A Campaign
HTTP Request
```POST /campaigns/new```

POST Parameters
```
    name            Campaign name is the only required field
    alias           string
    description	    A description of the campaign.
    isPublished	    A value of 0 or 1
```
Response
```Expected Response Code: 201```

### Edit Campaign
HTTP Request
To edit a campaign and return a 404 if the campaign is not found:
```PATCH /campaigns/{ID}/edit```
To edit a campaign and create a new one if the campaign is not found:
```PUT /campaigns/{ID}/edit```
POST Paramters
```
    name            Campaign name is the only required field
    alias           Name alias generated automatically if not set
    description     A description of the campaign.
    isPublished     A value of 0 or 1
```
Response
If ```PUT```, the expected response code is 200 if the campaign was edited or 201 if created.

If ```PATCH```, the expected response code is 200.

### List Sources 
HTTP Request
```GET /sources```

Query Paramters
```
    search      String or search command to filter entities by.
    start       Starting row for the entities returned. Defaults to 0.
    limit       Limit number of entities to return. Defaults to the system configuration for pagination (30).
    orderBy     Column to sort by. Can use any column listed in the response.
    orderByDir  Sort direction: asc or desc.
    published   Only return currently published entities.
    minimal     Return only array of entities without additional lists in it.
```

Example Request
```
    GET /api/sources?limit=1 HTTP/1.1
    Host: pre.dmsengage.com
    Authorization: Basic QVBJX1RFU1Q6bWF1dGlj==
```

Response
```Expected Response Code: 200```
A JSON string of sources, including linked campaigns

```{
       "total": 136,
       "contactsources": {
           "134": {
               "isPublished": true,
               "dateAdded": "2018-05-02T14:40:04+00:00",
               "dateModified": "2018-05-02T14:42:04+00:00",
               "createdBy": 3,
               "createdByUser": "Matt Goodman",
               "modifiedBy": 3,
               "modifiedByUser": "Matt Goodman",
               "id": 134,
               "name": "3 Day Job SMS",
               "category": null,
               "description": "500839",
               "description_public": "500839",
               "utmSource": "500839",
               "token": "f035e57f57ae8c4f92cbdb5ac1d270a381c97120",
               "documentation": true,
               "publishUp": null,
               "publishDown": null,
               "campaignList": {
                   "1": {
                       "campaignId": "1",
                       "cost": 0,
                       "realTime": false,
                       "scrubRate": 0,
                       "limits": [],
                       "campaignName": "Simply Jobs",
                       "campaignDescription": "DMS's owned and operated jobs portal (http:\/\/simplyjobs.com)"
                   }
               }
           }
       }
   }
```

### Get a Source By ID
HTTP Request
```GET /sources/{ID}```

Response
```Expected Response Code: 200```
A JSON string of the source parameters, including linked campaigns
(see example response above)

### Create A Source
HTTP Request
```POST /sources/new```

POST Parameters
```
    name            Source name is the only required field
    description	    A description of the Source.
    isPublished	    A value of 0 or 1
```

Example Request
```POST /api/sources/new HTTP/1.1
   Host: mautic.loc
   Authorization: Basic QVBJX1RFU1Q6bWF1dGlj==
   Content-Type: application/x-www-form-urlencoded
   Cache-Control: no-cache
   Postman-Token: 170937f4-343a-403d-aebc-bb928a2c496c
   
   name=some+value
```

Response
```Expected Response Code: 201```

```{
       "contactsource": {
           "isPublished": true,
           "dateAdded": "2018-05-23T20:47:27+00:00",
           "dateModified": null,
           "createdBy": 38,
           "createdByUser": "API TEST",
           "modifiedBy": null,
           "modifiedByUser": null,
           "id": 137,
           "name": "some value",
           "category": null,
           "description": null,
           "description_public": null,
           "utmSource": null,
           "token": "858eb6f20fc866b8e577c70b48dbd5bdb95edd76",
           "documentation": false,
           "publishUp": null,
           "publishDown": null,
           "campaignList": null
       }
   }
```

### Edit A Source
HTTP Request
To edit a source and return a 404 if the source is not found:
```PATCH /sources/{ID}/edit```
To edit a source and create a new one if the source is not found:
```PUT /sources/{ID}/edit```
POST Parameters
```
    name	        Source name 
    description	        A description of the source.
    isPublished	        A value of 0 or 1
```
Example Request
```
    PUT /api/contactsources/10/edit HTTP/1.1
    Host: pre.dmsengage.com
    Authorization: Basic QVBJX1RFU1Q6bWF1dGlj==
    Content-Type: application/x-www-form-urlencoded
   
    name=New+Source+From+API
```


Response
If ```PUT```, the expected response code is 200 if the source was edited or 201 if created.

If ```PATCH```, the expected response code is 200.

```{
       "contactsource": {
           "isPublished": false,
           "dateAdded": "2018-04-04T21:27:02+00:00",
           "dateModified": "2018-05-22T15:38:42+00:00",
           "createdBy": 3,
           "createdByUser": "Matt Goodman",
           "modifiedBy": 38,
           "modifiedByUser": "API TEST",
           "id": 10,
           "name": "Benefit Logix (edit)",
           "category": null,
           "description": "501093X",
           "description_public": "1",
           "utmSource": null,
           "token": "46ea7f4c80eb17f2aba445020018215877e05f21",
           "documentation": false,
           "publishUp": null,
           "publishDown": null,
           "campaignList": null
       }
   }
```

### Add a Source to a Campaign
HTTP Request
```POST /sources/{ID}/campaign/add``` where ID is the Source ID.

POST Parameters
```
    campaignId      The Campaign ID.
    cost	    cost per lead. Default is 0.
    realTime	    A value of 0 or 1, default is 0.
    scrubRate       a number from 0 to 100, percentage to scrub. Default is 0.     
```

Example Request
```
    PUT /api/contactsources/46/campaign/add HTTP/1.1
    Host: pre.dmsengage.com
    Authorization: Basic QVBJX1RFU1Q6bWF1dGlj==
    Content-Type: application/x-www-form-urlencoded
   
    campaignId=25&cost=0.13&realTime=false&scrubRate=15
```
Response
```Expected Response Code: 201```

```{
       "contactsource": {
           "isPublished": true,
           "dateAdded": "2018-04-04T23:38:46+00:00",
           "dateModified": "2018-05-23T20:36:20+00:00",
           "createdBy": 3,
           "createdByUser": "Matt Goodman",
           "modifiedBy": 38,
           "modifiedByUser": "API TEST",
           "id": 46,
           "name": "Fluent",
           "category": null,
           "description": "500395",
           "description_public": null,
           "utmSource": "500395",
           "token": "3e5f14695a7af8d1cdcb26358b54301871aa0751",
           "documentation": true,
           "publishUp": null,
           "publishDown": null,
           "campaignList": {
               "2": {
                   "campaignId": "2",
                   "cost": 0,
                   "realTime": false,
                   "scrubRate": 0,
                   "limits": [
                       {
                           "quantity": 50000,
                           "scope": "5",
                           "value": "",
                           "duration": "P1D"
                       },
                       {
                           "quantity": 1000000,
                           "scope": "5",
                           "value": "",
                           "duration": "1M"
                       }
                   ],
                   "campaignName": "Survey Voices SMS",
                   "campaignDescription": "Survey Voices SMS Monetization"
               },
               "25": {
                   "campaignId": "25",
                   "cost": 0.13,
                   "realTime": false,
                   "scrubRate": 15,
                   "limits": [],
                   "campaignName": "Cricket Media",
                   "campaignDescription": null
               },
           }
       }
   }
```