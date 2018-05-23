#Campaign and Contact Source API Documentation

## Getting Started

words...

### Prerequisites

words ...

### Authorization
Engage (Mautic) supports Basic Authorization and OAuth 1a and 2.
Read more about authorization on Mautic's developer docs [here](https://developer.mautic.org/?php#authorization).

### Libraries for Your Application

Mautic provides a PHP library that can be installed in your applicatioon to make 
communicating with Engage (Mautic) consistent and easy.

[Read More](https://developer.mautic.org/?php#libraries)


### Endpoints
All responses are JSON encoded.

The Base API Endpoint is 
```pre.dmsengage.com/api ```
 or 
```dev.dmsengage.com/api```

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
    name	         Campaign name is the only required field
    alias	 string
    description	 A description of the campaign.
    isPublished	 A value of 0 or 1
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
    name	Campaign name is the only required field
    alias	Name alias generated automatically if not set
    description	A description of the campaign.
    isPublished	A value of 0 or 1
```
Response
If ```PUT```, the expected response code is 200 if the campaign was edited or 201 if created.

If ```PATCH```, the expected response code is 200.

### List ContactSources 
HTTP Request
```GET /contactsources```

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
A JSON string of contactsources, including linked campaigns

### Get a ContactSource By ID
HTTP Request
```GET /contactsources/{ID}```

Response
```Expected Response Code: 200```
A JSON string of the contact source parameters, including linked campaigns

### Create A ContactSource
HTTP Request
```POST /contactsources/new```

POST Parameters
```
    name            Campaign name is the only required field
    description	    A description of the campaign.
    isPublished	    A value of 0 or 1
```
Response
```Expected Response Code: 201```

### Edit ContactSource
HTTP Request
To edit a contactsource and return a 404 if the campaign is not found:
```PATCH /contactsources/{ID}/edit```
To edit a campaign and create a new one if the campaign is not found:
```PUT /contactsources/{ID}/edit```
POST Paramters
```
    name	        ContactSource name is the only required field
    description	        A description of the campaign.
    isPublished	        A value of 0 or 1
```
Response
If ```PUT```, the expected response code is 200 if the contactsource was edited or 201 if created.

If ```PATCH```, the expected response code is 200.

### Add a Source to a Campaign / Campaign to a Source
HTTP Request
```POST /contactsources/{ID}/campaign/add```

POST Parameters
```
    ID              The Campaign ID.
    cost	    cost per lead. Default is 0.
    realTime	    A value of 0 or 1, default is 0.
    scrubbed        a number from 0 to 100, percentage to scrub. Default is 0.     
```
Response
```Expected Response Code: 201```