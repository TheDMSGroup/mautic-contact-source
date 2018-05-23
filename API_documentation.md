# Campaign and Contact Source API Documentation

## Getting Started

words...

### Prerequisites

words ...

### Authorization
Engage  supports Basic Authorization  OAuth 2.


### Endpoints and Base URL
All responses are JSON encoded.

The Base API Endpoint for Production Sandbox is 
```pre.dmsengage.com/api ```


### Error Handling
If an OAuth error is encountered, it’ll be a JSON encoded array similar to:
 ```
 {
      "error": "invalid_grant",
      "error_description": "The access token provided has expired."
    }
 ```
    
If a system error encountered, it’ll be a JSON encoded array similar to:  
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

Query Paramters
```
    search	String or search command to filter entities by.
    start	Starting row for the entities returned. Defaults to 0.
    limit	Limit number of entities to return. Defaults to the system configuration for pagination (30).
    orderBy	Column to sort by. Can use any column listed in the response.
    orderByDir	Sort direction: asc or desc.
    published	Only return currently published entities.
    minimal	Return only array of entities without additional lists in it.
```

Response
```Expected Response Code: 200```
A JSON string of campaigns

### Get a Campaign By ID
HTTP Request
```GET /campaigns/{ID}```

Response
```Expected Response Code: 200```
A JSON string of campaigns

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
```PATCH /campaigns/ID/edit```
To edit a campaign and create a new one if the campaign is not found:
```PUT /campaigns/ID/edit```
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

Response
```Expected Response Code: 200```
A JSON string of sources, including linked campaigns

### Get a Source By ID
HTTP Request
```GET /sources/{ID}```

Response
```Expected Response Code: 200```
A JSON string of the source parameters, including linked campaigns

### Create A Source
HTTP Request
```POST /sources/new```

POST Parameters
```
    name            Source name is the only required field
    description	    A description of the Source.
    isPublished	    A value of 0 or 1
```
Response
```Expected Response Code: 201```

### Edit A Source
HTTP Request
To edit a contactsource and return a 404 if the campaign is not found:
```PATCH /sources/{ID}/edit```
To edit a campaign and create a new one if the campaign is not found:
```PUT /sources/{ID}/edit```
POST Paramters
```
    name	        Source name is the only required field
    description	        A description of the source.
    isPublished	        A value of 0 or 1
```
Response
If ```PUT```, the expected response code is 200 if the source was edited or 201 if created.

If ```PATCH```, the expected response code is 200.

### Add a Source to a Campaign
HTTP Request
```POST /sources/{ID}/campaign/add```

POST Parameters
```
    ID              The Campaign ID.
    cost	    cost per lead. Default is 0.
    realTime	    A value of 0 or 1, default is 0.
    scrubbed        a number from 0 to 100, percentage to scrub. Default is 0.     
```
Response
```Expected Response Code: 201```