Gravity Forms REST API v2
=========================

## Upgrading to Version 2

The API is intended to feel as familiar as possible to developers who have worked with the WordPres REST API.
The endpoints are largely the same as version 1, however, the responses are slightly different and authentication 
is no longer handled by Gravity Forms.

The following breaking changes are required by clients to consume version 2:

### Authentication

If you're using cookie authentication, WordPress supports cookie authentication out of the box so you'll just need
to change the way the nonce is created and sent. Create the nonce using wp_create_nonce( 'wp_rest' ) and send it
in the \_wpnonce data parameter (either POST data or in the query for GET requests), or via the X-WP-Nonce header.

If you're using signature authentication then you'll need to implement either Basic or OAuth authentication. Further details here:

* [WordPress REST API authentication documentation](http://v2.wp-api.org/guide/authentication/)
* [WP REST API: Setting Up and Using Basic Authentication](https://code.tutsplus.com/tutorials/wp-rest-api-setting-up-and-using-basic-authentication--cms-24762)
* [WP REST API: Setting Up and Using OAuth 1.0a Authentication](https://code.tutsplus.com/tutorials/wp-rest-api-setting-up-and-using-oauth-10a-authentication--cms-24797)


### Specify the Content Type when appropriate

The content-type application/json must be specified when sending JSON.

#### Example

```bash
curl --data [EXAMPLE_DATA] --header "Content-Type: application/json" https://localhost/wp-json/gf/v2
```

### No Response Envelope

The response will not be enveloped by default. This means that the response will not be a JSON string containing the
"status" and "response" - the body will contain the response and the HTTP code will contain the status.

The WP-API will envelope the response if the \_envelope param is included in the request.

#### Example

**Standard response:**

```json
{
    "3":                "Drop Down First Choice",
    "created_by":       "1",
    "currency":         "USD",
    "date_created":     "2016-10-10 18:06:12",
    "form_id":          "1",
    "id":               "1",
    "ip":               "127.0.0.1",
    "is_fulfilled":     null,
    "is_read":          0,
    "is_starred":       0,
    "payment_amount":   null,
    "payment_date":     null,
    "payment_method":   null,
    "payment_status":   null,
    "post_id":          null,
    "source_url":       "http://localhost?gf_page=preview&id=1",
    "status":           "active",
    "transaction_id":   null,
    "transaction_type": null,
    "user_agent":       "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36"
}
```
**Response with _envelope parameter:**

```json
{
    "body": {
        "3":                "Drop Down First Choice",
        "created_by":       "1",
        "currency":         "USD",
        "date_created":     "2016-10-10 18:06:12",
        "form_id":          "1",
        "id":               "1",
        "ip":               "127.0.0.1",
        "is_fulfilled":     null,
        "is_read":          0,
        "is_starred":       0,
        "payment_amount":   null,
        "payment_date":     null,
        "payment_method":   null,
        "payment_status":   null,
        "post_id":          null,
        "source_url":       "http://localhost?gf_page=preview&id=1",
        "status":           "active",
        "transaction_id":   null,
        "transaction_type": null,
        "user_agent":       "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36"
    },
    "headers": {
        "Allow": "GET, POST, PUT, PATCH, DELETE"
    },
    "status": 200
}
```

### Form Submissions

The Form Submissions endpoint now accepts application/json, application/x-www-form-urlencoded and multipart/form-data
content types. With the introduction of support for multipart/form-data now files can be sent to single file upload fields.

Request values should be sent all together instead of in separate elements for input_values, field_values, target_page
and source_page.

#### Example

**Example body of a JSON request:**

```json
{
    "input_1":      "test",
    "field_values": "",
    "source_page":  1,
    "target_page":  0
}
```

### POST Single Resources

In order to maintain consistency with the WP API, the POST /entries and POST /forms endpoints no longer accept
collections. This means that it's no longer possible to create multiple entries or forms in a single request.

### DELETE now trashes
Sending DELETE requests will send the resource to the trash instead of deleting it permanently.
Repeating the DELETE request will not delete the resource permanently but it will generate a 401 (Gone) response code.
Use the 'force' parameter to delete the entry or form permanently.

### DELETE, POST and PUT responses
Successful DELETE, POST and PUT requests now return the deleted, updated or created entry or form instead of a confirmation message.

## Unit Tests

The unit tests can be installed from the terminal using:

```bash
bash tests/bin/install.sh [DB_NAME] [DB_USER] [DB_PASSWORD] [DB_HOST]
```

If you're using [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV) you can use this command:

```bash
bash tests/bin/install.sh wordpress_unit_tests root root localhost
```

# API Documentation

## Authentication

Authentication can be performed using the same methods as the WordPress REST API. For further information on
WordPress' authentication, the following resources are available:

* [WordPress REST API authentication documentation](http://v2.wp-api.org/guide/authentication/)
* [WP REST API: Setting Up and Using Basic Authentication](https://code.tutsplus.com/tutorials/wp-rest-api-setting-up-and-using-basic-authentication--cms-24762)
* [WP REST API: Setting Up and Using OAuth 1.0a Authentication](https://code.tutsplus.com/tutorials/wp-rest-api-setting-up-and-using-oauth-10a-authentication--cms-24797)


## API Path

The API can be accessed as route from the WordPress REST API. This should look something like this:

    https://localhost/wp-json/gf/v2/

For example, to obtain the Gravity Forms entry with ID 5, your request would be made to the following:

    https://localhost/wp-json/gf/v2/entries/5

## Sending Requests

### PHP

```php
// Define the URL that will be accessed.
$url = rest_url( 'gf/v2/entries' );

// Example using Basic Authentication
$args = array(
    'Authorization' => 'Basic ' . base64_encode( 'admin' . ':' . '12345' ),
	'headers'       => array( 'Content-type' => 'application/json' ),
);

// Make the request to the API.
$response = wp_remote_get( $url, $args );

// Check the response code.
if ( wp_remote_retrieve_response_code( $response ) != 200 || ( empty( wp_remote_retrieve_body( $response ) ) ) ){
    // If not a 200, HTTP request failed.
    die( 'There was an error attempting to access the API.' );
}

// Result is in the response body and is json encoded.
$body = json_decode( wp_remote_retrieve_body( $response ), true );

// Check the response body.
if( $body['status'] > 202 ){
    die( "Could not retrieve forms." );
}

// Entries retrieved successfully.
$entries = $body['response'];
```

In this example, the *$entries* variable contains the response from the API request.

## Endpoints

### GET /entries

Gets all entries.

#### Path

    https://localhost/wp-json/gf/v2/entries

#### Response *[json]*

The response will contain a JSON object which contains the entry details. An example can be found below:

**Example Response**

```json
{
  "id":           "71",
  "form_id":      "1",
  "date_created": "2016-11-28 18:12:17",
  "is_starred":   0,
  "is_read":      0,
  "ip":           "127.0.0.1",
  "source_url":   "http:\/\/localhost\/pagename",
  "post_id":      null,
  "created_by":   "2",
  "user_agent":   "Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/54.0.2840.87 Safari\/537.36",
  "status":       "active",
  "1":            "",
  "2":            "",
  "3":            "",
  "4":            "",
  "5":            "",
  "6.1":          "",
  "6.2":          "",
  "6.3":          ""
}
```

#### Optional Arguments

* **_labels** *[int]*

    Enabled the inclusion of field labels in the results.  

    * **Usage**

            https://localhost/wp-json/gf/v2/entries?_labels=1

    * **Example Response**

        ```json
        [{
          "id":           "71",
          "form_id":      "1",
          "date_created": "2016-11-28 18:12:17",
          "is_starred":   0,
          "is_read":      0,
          "ip":           "127.0.0.1",
          "source_url":   "http:\/\/localhost\/pagename",
          "post_id":      null,
          "created_by":   "2",
          "user_agent":   "Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/54.0.2840.87 Safari\/537.36",
          "status":       "active",
          "1":            "",
          "2":            "",
          "3":            "",
          "4":            "",
          "5":            "",
          "6.1":          "",
          "6.2":          "",
          "6.3":          "",
          "_labels": {
            "1":  "Single Line Text",
            "2":  "Paragraph Text",
            "13": "File",
            "3":  "Drop Down",
            "4":  "Multi Select",
            "5":  "Number",
            "6": {
              "6.1": "Checkboxes First Choice",
              "6.2": "Checkboxes Second Choice",
              "6.3": "Checkboxes Third Choice"
            }
          }
        }]
        ```
* **include** *[int]*

    An array of entries to include in the response.

    * **Usage**

            https://localhost/wp-json/gf/v2/entries?include[0]=1&include[1]=3

    * **Example Response**

        ```json
        [{
          "date_created": "2016-11-28 18:12:17",
          "1":            "Text",
          "6.1":          "first",
          "6.2":          "second",
          "6.3":          "third"
        }]
        ```
        
* **_field_ids** *[int]*

    A comma separated list of fields to include in the response.

    * **Usage**

            https://localhost/wp-json/gf/v2/entries/5?_field_ids=1,6.1,6.2,6.3,date_created

    * **Example Response**

        ```json
        [{
          "date_created": "2016-11-28 18:12:17",
          "1":            "Text",
          "6.1":          "first",
          "6.2":          "second",
          "6.3":          "third"
        }]
        ```
        
* **search** *[json]*

    The search criteria.  

    * **Properties**

        * **field_filters** *[array]*  

            An array of filters to search by.

        * **key** *[int|float]*

            The field ID.

        * **value**  *[string]*

            The value to search for.

        * **operator** *[string]*  

            The comparison operator to use.

    * **Usage**

        ```json
        {
          "field_filters": [{
            "key":      1,
            "value":    "Field Value",
            "operator": "contains"
          }]
        }
        ```

* **paging** *[array]*

    The paging criteria.

    * **Properties**

        * **page_size** *[int]*

            The number of results per page.

        * **current_page** *[int]*

            The current page to pull details from.

        * **offset** *[int]*

            The offset to begin with.

    * **Usage**

            https://localhost/wp-json/gf/v2/entries?paging[page_size]=20&paging[current_page]=2&paging[offset]=30

* **sorting** *[array]*

    The sorting criteria.

    * **Properties**

        * **key** *[string|int]*

            The key to sort by.

        * **direction** *[string]*

            The direction. Either *ASC* or *DESC*.

        * **is_numeric** *[bool]*

            If the key is numeric.

    * **Usage**

            https://localhost/wp-json/gf/v2/entries?sorting[key]=id&sorting[direction]=ASC&sorting[is_numeric]=true

* **form_ids** *[array]*

    The form IDs to be included in the search.

    * **Usage**

            https://localhost/wp-json/gf/v2/entries?form_ids[0]=1&form_ids[1]=2


------------------------------------------------------------------------------------------------------------------------

### POST /entries

Creates an entry.

#### Path

    https://localhost/wp-json/gf/v2/entries

#### Response *[json]*

When creating an entry, the response body will contain the complete new entry.

#### Optional Arguments

* **created_by** *[string]*  

    The user ID of the entry submitter.

    * **Example**

        Sets the entry submitter as the user with user ID *1*.  

            created_by=1

* **date_created** *[string]*

    The date the entry was created, in UTC.

    * **Example**

        Sets the date created as *2016-11-28 18:12:17*.

            date_created=2016-11-28+18%3A12%3A17

* **ip** *[string]*

    The IP address of the entry creator.

    * **Example**

        Sets the entry IP as *127.0.0.1*.

            ip=127.0.0.1

* **is_fulfilled** *[bool]*

    Whether the transaction has been fulfilled, if applicable.

    * **Example**

        Sets the entry as fulfilled.

            is_fulfilled=1

* **is_read** *[bool]*

    Whether the entry has been read.

    * **Example**

        Marks the entry as read.

            is_read=1

* **is_starred** *[bool]*

    Whether the entry is starred.

    * **Example**  

        Stars the entry.

            is_starred=1

* **source_url** *[string]*

    The URL where the form was embedded.

    * **Examples**

        Set the source URL as *http://localhost/pagename*:

            source_url=http%3A%2F%2Flocalhost%2Fpagename

* **status** *[string]*

    The status of the entry.

    * **Examples**

        Sets the status to *active*:

             status=active

* **user_agent** *[string]*

    The user agent string for the browser used to submit the entry.

    * **Examples**

        Sets the user agent as *Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.87 Safari/537.36*

            user_agent=Mozilla%2F5.0+%28Macintosh%3B+Intel+Mac+OS+X+10_12_2%29+AppleWebKit%2F537.36+%28KHTML%2C+like+Gecko%29+Chrome%2F54.0.2840.87+Safari%2F537.36``

#### Payment Arguments

* **payment_amount** *[int]*

    The amount of the payment, if applicable.

    * **Limitations**

        Only applies when payment fields are present.

    * **Examples**

        Sets the payment amount of *$2500*.

            payment_amount=2500

* **payment_date** *[string]*

    The date of the payment, if applicable.

    * **Limitations**  

          Only applies when payment fields are present.

    * **Example**

        Sets the payment date as *2016-11-28 18:12:17*.  

            payment_date=2016-11-28+18%3A12%3A17

* **payment_method** *[string]*  

    The payment method for the payment, if applicable.

    * **Limitations**  

        Only applies when payment fields are present.

    * **Example**

        Sets the payment method as *Stripe*.

            payment_method=Stripe

* **payment_status** *[string]*  

    The status of the payment, if applicable.

    * **Limitations**  

        Only applies when payment fields are present.

    * **Example**

        Sets the payment status as *Paid*.

            payment_status=Paid

* **transaction_id** *[string]*

    The transaction ID for the payment, if applicable.

    * **Limitations**

        Only applies when payment fields are present.

    * **Example**  

        Sets the transaction ID as *1234*.  

            transaction_id=1234

* **transaction_type** *[string]*

    The type of the transaction, if applicable.

    * **Limitations**  

        Only applies when payment fields are present.

    * **Example**

        Sets the *Subscription* transaction type.  

            transaction_type=Subscription

------------------------------------------------------------------------------------------------------------------------

### GET /entries/[ENTRY_ID]

Gets an entry based on the entry ID.

#### Path

        https://localhost/wp-json/gf/v2/entries/1

#### Response *[json]*

The response will contain a JSON object which contains the entry details. An example can be found below:

* **Example**

    ```json
    {
      "id":           "71",
      "form_id":      "1",
      "date_created": "2016-11-28 18:12:17",
      "is_starred":   0,
      "is_read":      0,
      "ip":           "127.0.0.1",
      "source_url":   "http:\/\/localhost\/pagename",
      "post_id":      null,
      "created_by":   "2",
      "user_agent":   "Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/54.0.2840.87 Safari\/537.36",
      "status":       "active",
      "1":            "",
      "2":            "",
      "3":            "",
      "4":            "",
      "5":            "",
      "6.1":          "",
      "6.2":          "",
      "6.3":          ""
    }
    ```

#### Optional Arguments

* **_labels** *[int]*

    Whether to include the labels.

    * **Usage**

            https://localhost/wp-json/gf/v2/entries/5?_labels=1

    * **Example Response**

        ```json
        {
          "id":           "71",
          "form_id":      "1",
          "date_created": "2016-11-28 18:12:17",
          "is_starred":   0,
          "is_read":      0,
          "ip":           "127.0.0.1",
          "source_url":   "http:\/\/localhost\/pagename",
          "post_id":      null,
          "created_by":   "2",
          "user_agent":   "Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/54.0.2840.87 Safari\/537.36",
          "status":       "active",
          "1":            "",
          "2":            "",
          "3":            "",
          "4":            "",
          "5":            "",
          "6.1":          "",
          "6.2":          "",
          "6.3":          "",
          "labels": {
            "1":  "Single Line Text",
            "2":  "Paragraph Text",
            "13": "File",
            "3":  "Drop Down",
            "4":  "Multi Select",
            "5":  "Number",
            "6": {
              "6.1": "Checkboxes First Choice",
              "6.2": "Checkboxes Second Choice",
              "6.3": "Checkboxes Third Choice"
            }
          }
        }
        ```
* **_field_ids** *[int]*

    A comma separated list of fields to include in the response.

    * **Usage**

            https://localhost/wp-json/gf/v2/entries/5?_field_ids=1,6.1,6.2,6.3,date_created

    * **Example Response**

        ```json
        {
          "date_created": "2016-11-28 18:12:17",
          "1":            "Text",
          "6.1":          "first",
          "6.2":          "second",
          "6.3":          "third"
        }
        ```


------------------------------------------------------------------------------------------------------------------------

### PUT /entries/[ENTRY_ID]

Updates an entry based on the specified entry ID.

#### Path

    https://localhost/wp-json/gf/v2/entries/1

#### Response *[json]*
When updating an entry, the response body will contain the complete updated entry.


#### Optional Arguments

* **created_by** *[string]*

    The user ID of the entry submitter.

    * **Example**  

        Sets the entry submitter as the user with user ID *1*.  

            created_by=1

* **date_created** *[string]*  

    The date the entry was created, in UTC.

    * **Example**  

        Sets the date created as *2016-11-28 18:12:17*.  

            date_created=2016-11-28+18%3A12%3A17

* **ip** *[string]*  

    The IP address of the entry creator.  

    * **Example**  

        Sets the entry IP as *127.0.0.1*.  

            ip=127.0.0.1

* **is_fulfilled** *[bool]*  

    Whether the transaction has been fulfilled, if applicable.  

    * **Example**  

        Sets the entry as fulfilled.  

			is_fulfilled=1

* **is_read** *[bool]*  

	Whether the entry has been read.  

	* **Example**

		Marks the entry as read.

	        is_read=1

* **is_starred** *[bool]*

    Whether the entry is starred.  

    * **Example**  

        Stars the entry.  

            is_starred=1

* **source_url** *[string]*  

    The URL where the form was embedded.  

    * **Example**  

        Sets the source URL as *http://localhost/pagename*.  

            source_url=http%3A%2F%2Flocalhost%2Fpagename

* **status** *[string]*  

    The status of the entry.

    * **Example**

        Sets the status to *active*.  

            status=active

* **user_agent** *[string]*

    The user agent string for the browser used to submit the entry.  

    * **Example**

        Sets the user agent as *Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.87 Safari/537.36*

            user_agent=Mozilla%2F5.0+%28Macintosh%3B+Intel+Mac+OS+X+10_12_2%29+AppleWebKit%2F537.36+%28KHTML%2C+like+Gecko%29+Chrome%2F54.0.2840.87+Safari%2F537.36``

#### Payment Arguments

* **payment_amount** *[int]*  

    The amount of the payment, if applicable.  

    * **Example**  

        Sets the payment amount of *$2500*.  

            payment_amount=2500

* **payment_date** *[string]*

    The date of the payment, if applicable.  

    * **Example**  

        Sets the payment date as *2016-11-28 18:12:17*.  

            payment_date=2016-11-28+18%3A12%3A17

* **payment_method** *[string]*  

    The payment method for the payment, if applicable.  

    * **Example**

        Sets the payment method as *Stripe*.  

            payment_method=Stripe

* **payment_status** *[string]*

    The status of the payment, if applicable.

    * **Example**  

        Sets the payment status as *Paid*.  

            payment_status=Paid

* **transaction_id** *[string]*

    The transaction ID for the payment, if applicable.  

    * **Example**  

        Sets the transaction ID as *1234*.

            transaction_id=1234

* **transaction_type** *[string]*  

    The type of the transaction, if applicable.  

    * **Example**  

        Sets the *Subscription* transaction type.

            transaction_type=Subscription


------------------------------------------------------------------------------------------------------------------------

### POST /entries/[ENTRY_ID]/notifications

Sends the notifications for the given entry.

#### Path

    https://localhost/wp-json/gf/v2/entries/1/notifications

#### Response *[json]*

* **Success** *[json]*  

  An array of notification IDs passed to WordPres for sending.

#### Optional Arguments

* **_notifications** *[array]*

    Limit the notifications to specific IDs.

    * **Example**  

        Sets the entry submitter as the user with user ID *1*.  

            https://localhost/wp-json/gf/v2/entries/1/notifications?_notifications=574ff8257d864,596e543d90b46

* **_event** *[string]*  

    The event to trigger. Default: form_submission.

    * **Example**  

        Sets the date created as *2016-11-28 18:12:17*.  

            https://localhost/wp-json/gf/v2/entries/1/notifications?_event=form_submission



------------------------------------------------------------------------------------------------------------------------

### DELETE /entries/[ENTRY_ID]

Sends the specified entry to the trash. If the entry is already in the trash then repeating this request will not delete
the entry permanently but the response code will be 410 (Gone). Use the 'force' parameter to delete the entry permanently.

#### Path

    https://localhost/wp-json/gf/v2/entries/1
    https://localhost/wp-json/gf/v2/entries/1?force=1

#### Response *[json]*

* **Success** *[json]*  

  The trashed or deleted entry.

* **Failure** *[json]*  

  ```json
  {
    "code":    "gf_cannot_delete",
    "message": "Invalid entry id: 71",
    "data":    {
      "status": 500
    }
  }
  ```

------------------------------------------------------------------------------------------------------------------------

### GET /forms

Gets the details of all forms.

#### Path

    https://localhost/wp-json/gf/v2/forms

#### Response *[json]*

```json
{
  "4": {
    "id":      "4",
    "title":   "Multi-Page Form",
    "entries": "2"
  },
  "1": {
    "id":      "1",
    "title":   "Test Form",
    "entries": "60"
  },
  "5": {
    "id":      "5",
    "title":   "Test Form 2",
    "entries": "2"
  },
  "6": {
    "id":      "6",
    "title":   "Test Form 3",
    "entries": "2"
  }
}
```

#### Optional Arguments

* **include** *[array]*

    Limit the forms to specific IDs.

    * **Example**  

        Returns the forms with IDs *1* and *2*.  

            https://localhost/wp-json/gf/v2/forms?include[0]=1&include[1]=2


------------------------------------------------------------------------------------------------------------------------

### POST /forms

Creates a form.

#### Path

    https://localhost/wp-json/gf/v2/forms

#### Response

* **Success** *[json]*

    The newly created form.


* **Failure** *[json]*

  ```json
  {
    "code":    "missing_form_json",
    "message": "The Form object must be sent as a JSON string in the request body with the content-type header set to application\/json.",
    "data": {
    "status": 400
    }
  }
  ```

#### Required Arguments

* **title** *[string]*  

    The form title.

    * **Example**  

        Sets the form title as *Form Title*  

        ```json
        {
          "title": "Form Title"
        }
        ```

------------------------------------------------------------------------------------------------------------------------

### PUT /forms

Updates a form.

#### Path

    https://localhost/wp-json/gf/v2/forms

#### Response

* **Success** *[json]*

    The updated form.

* **Failure** *[json]*

  ```json
  {
    "code":    "missing_form_json",
    "message": "The Form object must be sent as a JSON string in the request body with the content-type header set to application\/json.",
    "data": {
    "status": 400
    }
  }
  ```

#### Required Arguments

* **title** *[string]*  

    The form title.

    * **Example**  

        Sets the form title as *Form Title*  

        ```json
        {
          "title": "Form Title"
        }
        ```
------------------------------------------------------------------------------------------------------------------------

### DELETE /forms

Sends the specified form to the trash. If the form is already in the trash then repeating this request will not delete
the form permanently but the response code will be 410 (Gone). Use the 'force' parameter to delete the entry permanently.

#### Path

    https://localhost/wp-json/gf/v2/forms

#### Response

* **Success** *[json]*

    The deleted form.

* **Failure** *[json]*

  ```json
  {
    "code":    "gf_form_invalid_id",
    "message": "Invalid form id.",
    "data": {
    "status": 404
    }
  }
  ```

#### Required Arguments

* **title** *[string]*  

    The form title.

    * **Example**  

        Sets the form title as *Form Title*  

        ```json
        {
          "title": "Form Title"
        }
        ```
------------------------------------------------------------------------------------------------------------------------

### GET /forms/[FORM_ID]

Gets the details of a form based on the specified form ID.

#### Path

    https://localhost/wp-json/gf/v2/forms/1

#### Response

```json
{
  "title":                "Test Form",
  "description":          "",
  "labelPlacement":       "top_label",
  "descriptionPlacement": "below",
  "button": {
    "type":     "text",
    "text":     "Submit",
    "imageUrl": ""
  },
  "fields": [
    {
      "type":                 "text",
      "id":                   1,
      "label":                "Single Line Text",
      "adminLabel":           "",
      "isRequired":           false,
      "size":                 "medium",
      "errorMessage":         "",
      "inputs":               null,
      "formId":               1,
      "description":          "",
      "allowsPrepopulate":    false,
      "inputMask":            false,
      "inputMaskValue":       "",
      "inputType":            "",
      "labelPlacement":       "",
      "descriptionPlacement": "",
      "subLabelPlacement":    "",
      "placeholder":          "",
      "cssClass":             "",
      "inputName":            "",
      "noDuplicates":         false,
      "defaultValue":         "",
      "choices":              "",
      "conditionalLogic":     "",
      "failed_validation":    "",
      "productField":         "",
      "enablePasswordInput":  "",
      "maxLength":            "",
      "pageNumber":           1,
      "displayOnly":          "",
      "multipleFiles":        false,
      "maxFiles":             "",
      "calculationFormula":   "",
      "calculationRounding":  "",
      "enableCalculation":    "",
      "disableQuantity":      false,
      "displayAllCategories": false,
      "useRichTextEditor":    false,
      "visibility":           "visible"
    },
    {
      "type":         "radio",
      "id":           7,
      "label":        "Radio Buttons",
      "adminLabel":   "",
      "isRequired":   false,
      "size":         "medium",
      "errorMessage": "",
      "inputs":       null,
      "choices": [
        {
          "text":       "Radio Buttons First Choice",
          "value":      "Radio Buttons First Choice",
          "isSelected": false,
          "price":      ""
        },
        {
          "text":       "Radio Buttons Second Choice",
          "value":      "Radio Buttons Second Choice",
          "isSelected": false,
          "price":      ""
        },
        {
          "text":       "Radio Buttons Third Choice",
          "value":      "Radio Buttons Third Choice",
          "isSelected": false,
          "price":      ""
        }
      ],
      "formId":               1,
      "description":          "",
      "allowsPrepopulate":    false,
      "inputMask":            false,
      "inputMaskValue":       "",
      "inputType":            "",
      "labelPlacement":       "",
      "descriptionPlacement": "",
      "subLabelPlacement":    "",
      "placeholder":          "",
      "cssClass":             "",
      "inputName":            "",
      "noDuplicates":         false,
      "defaultValue":         "",
      "conditionalLogic":     "",
      "failed_validation":    "",
      "productField":         "",
      "enableOtherChoice":    "",
      "enablePrice":          "",
      "pageNumber":           1,
      "displayOnly":          "",
      "multipleFiles":        false,
      "maxFiles":             "",
      "calculationFormula":   "",
      "calculationRounding":  "",
      "enableCalculation":    "",
      "disableQuantity":      false,
      "displayAllCategories": false,
      "useRichTextEditor":    false,
      "visibility":           "visible"
    },
    {
      "type":         "product",
      "id":           22,
      "label":        "Product Name",
      "adminLabel":   "",
      "isRequired":   false,
      "size":         "medium",
      "errorMessage": "",
      "inputs": [
        {
          "id":    "22.1",
          "label": "Name",
          "name":  ""
        },
        {
          "id":    "22.2",
          "label": "Price",
          "name":  ""
        },
        {
          "id":    "22.3",
          "label": "Quantity",
          "name":  ""
        }
      ],
        "inputType":            "singleproduct",
        "enablePrice":          null,
        "formId":               1,
        "description":          "",
        "allowsPrepopulate":    false,
        "inputMask":            false,
        "inputMaskValue":       "",
        "labelPlacement":       "",
        "descriptionPlacement": "",
        "subLabelPlacement":    "",
        "placeholder":          "",
        "cssClass":             "",
        "inputName":            "",
        "visibility":           "visible",
        "noDuplicates":         false,
        "defaultValue":         "",
        "choices":              "",
        "conditionalLogic":     "",
        "failed_validation":    "",
        "productField":         "",
        "basePrice":            "$200.00",
        "disableQuantity":      false,
        "pageNumber":           1,
        "displayOnly":          "",
        "multipleFiles":        false,
        "maxFiles":             "",
        "calculationFormula":   "",
        "calculationRounding":  "",
        "enableCalculation":    "",
        "displayAllCategories": false,
        "useRichTextEditor":    false
    }
  ],
  "version": "2.1.1.11",
  "id": 1,
  "useCurrentUserAsAuthor":     true,
  "postContentTemplateEnabled": false,
  "postTitleTemplateEnabled":   false,
  "postTitleTemplate":          "",
  "postContentTemplate":        "",
  "lastPageButton":             null,
  "pagination":                 null,
  "firstPageCssClass":          null,
  "postAuthor":                 "1",
  "postCategory":               "1",
  "postFormat":                 "0",
  "postStatus":                 "draft",
  "subLabelPlacement":          "below",
  "cssClass":                   "",
  "enableHoneypot":             false,
  "enableAnimation":            false,
  "save": {
    "enabled": true,
    "button": {
      "type": "link",
      "text": "Save and Continue Later"
    }
  },
  "limitEntries":           false,
  "limitEntriesCount":      "",
  "limitEntriesPeriod":     "",
  "limitEntriesMessage":    "",
  "scheduleForm":           false,
  "scheduleStart":          "",
  "scheduleStartHour":      "",
  "scheduleStartMinute":    "",
  "scheduleStartAmpm":      "",
  "scheduleEnd":            "",
  "scheduleEndHour":        "",
  "scheduleEndMinute":      "",
  "scheduleEndAmpm":        "",
  "schedulePendingMessage": "",
  "scheduleMessage":        "",
  "requireLogin":           false,
  "requireLoginMessage":    "",
  "notifications": {
    "57f6965a0b2e0": {
      "id":      "57f6965a0b2e0",
      "to":      "{admin_email}",
      "name":    "Admin Notification",
      "event":   "form_submission",
      "toType":  "email",
      "subject": "New submission from {form_title}",
      "message": "{all_fields}"
    }
  },
  "confirmations": {
    "57f6965a0bcd0": {
      "id":                "57f6965a0bcd0",
      "name":              "Default Confirmation",
      "isDefault":         true,
      "type":              "page",
      "message":           "",
      "url":               "",
      "pageId":            2,
      "queryString":       "",
      "disableAutoformat": false,
      "conditionalLogic":  [],
      "gppcmtEnable":      true
    }
  },
  "is_active":    "1",
  "date_created": "2016-10-06 18:22:18",
  "is_trash":     "0"
}
```

------------------------------------------------------------------------------------------------------------------------

### GET /forms/[FORM_ID]/entries

Gets entries associated with a specific form.

#### Path

    https://localhost/wp-json/gf/v2/forms/1/entries

#### Response *[json]*

The response will contain a JSON object which contains the entry details. An example can be found below:

**Example Response**

```json
{
  "id":           "71",
  "form_id":      "1",
  "date_created": "2016-11-28 18:12:17",
  "is_starred":   0,
  "is_read":      0,
  "ip":           "127.0.0.1",
  "source_url":   "http:\/\/localhost\/pagename",
  "post_id":      null,
  "created_by":   "2",
  "user_agent":   "Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/54.0.2840.87 Safari\/537.36",
  "status":       "active",
  "1":            "",
  "2":            "",
  "3":            "",
  "4":            "",
  "5":            "",
  "6.1":          "",
  "6.2":          "",
  "6.3":          ""
}
```

#### Optional Arguments

* **_labels** *[int]*  

    Whether to include the labels.

    * **Usage**  

            https://localhost/wp-json/gf/v2/forms/1/entries?_labels=1

    * **Example Response**  

        ```json
        {
          "id":           "71",
          "form_id":      "1",
          "date_created": "2016-11-28 18:12:17",
          "is_starred":   0,
          "is_read":      0,
          "ip":           "127.0.0.1",
          "source_url":   "http:\/\/localhost\/pagename",
          "post_id":      null,
          "created_by":   "2",
          "user_agent":   "Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/54.0.2840.87 Safari\/537.36",
          "status":       "active",
          "1":            "",
          "2":            "",
          "3":            "",
          "4":            "",
          "5":            "",
          "6.1":          "",
          "6.2":          "",
          "6.3":          "",
          "labels": {
            "1":  "Single Line Text",
            "2":  "Paragraph Text",
            "13": "File",
            "3":  "Drop Down",
            "4":  "Multi Select",
            "5":  "Number",
            "6": {
              "6.1": "Checkboxes First Choice",
              "6.2": "Checkboxes Second Choice",
              "6.3": "Checkboxes Third Choice"
            }
          }
        }
        ```
* **_field_ids** *[int]*

    A comma separated list of fields to include in the response.

    * **Usage**

            https://localhost/wp-json/gf/v2/entries/5?_field_ids=1,6.1,6.2,6.3,date_created

    * **Example Response**

        ```json
        {
          "date_created": "2016-11-28 18:12:17",
          "1":            "Text",
          "6.1":          "first",
          "6.2":          "second",
          "6.3":          "third"
        }
        ```

* **search** *[json]*  

    The search criteria.

    * **Usage**

        * **field_filters** *array*  

            An array of filters to search by.

        * **key** *int|float*

            The field ID.

        * **value**  *string*

            The value to search for.

        * **operator** *string*

            The comparison operator to use.

        ```json
        {
          "field_filters": [{
          "key":      1,
          "value":    "Field Value",
          "operator": "contains"
          }]
        }
        ```

* **paging** *[array]*  

    The paging criteria.

    * **Parameters**

        * **page_size** *[int]*  

            The number of results per page.

        * **current_page** *[int]*  

            The current page to pull details from.

        * **offset** *[int]*  

            The offset to begin with.

    * **Usage**  

            https://localhost/wp-json/gf/v2/forms/1/entries?paging[page_size]=20&paging[current_page]=2&paging[offset]=30

* **sorting** *[array]*

    The sorting criteria.

    * **Parameters**

        * **key** *[string|int]*  

            The key to sort by.

        * **direction** *[string]*  

            The direction. Either *ASC* or *DESC*.

        * **is_numeric** *[bool]*  

            If the key is numeric.

    * **Usage**  

            https://localhost/wp-json/gf/v2/forms/1/entries?sorting[key]=id&sorting[direction]=ASC&sorting[is_numeric]=true

------------------------------------------------------------------------------------------------------------------------

### POST /forms/[FORM_ID]/entries

Creates an entry based on the specified form ID.

#### Path

    https://localhost/wp-json/gf/v2/forms/1/entries

#### Response *[json]*

When creating an entry, the response body will contain the new entry.

#### Optional Arguments

* **created_by** *[string]*

    The user ID of the entry submitter.

    * **Example**  

        Sets the entry submitter as the user with user ID *1*.  

            created_by=1

* **date_created** *[string]*  

    The date the entry was created, in UTC.  

    * **Example**  

        Sets the date created as *2016-11-28 18:12:17*.  

            date_created=2016-11-28+18%3A12%3A17

* **ip** *[string]*  

    The IP address of the entry creator.  

    * **Example**

        Sets the entry IP as *127.0.0.1*.  

            ip=127.0.0.1

* **is_fulfilled** *[bool]*  

    Whether the transaction has been fulfilled, if applicable.  

    * **Example**  

        Sets the entry as fulfilled.  

            is_fulfilled=1

* **is_read** *[bool]*  

    Whether the entry has been read.  

    * **Example**  

        Marks the entry as read.  

            is_read=1

* **is_starred** *[bool]*  

    Whether the entry is starred.  

    * **Example**  

        Stars the entry.  

            is_starred=1

* **source_url** *[string]*  

    The URL where the form was embedded.  

    * **Example**  

        Sets the source URL as *http://localhost/pagename*.  

            source_url=http%3A%2F%2Flocalhost%2Fpagename

* **status** *[string]*  

    The status of the entry.  

    * **Example**

        Sets the status to *active*.  

            status=active

* **user_agent** *[string]*  

    The user agent string for the browser used to submit the entry.  

    * **Example**  

        Sets the user agent as:  

        *Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.87 Safari/537.36*

            user_agent=Mozilla%2F5.0+%28Macintosh%3B+Intel+Mac+OS+X+10_12_2%29+AppleWebKit%2F537.36+%28KHTML%2C+like+Gecko%29+Chrome%2F54.0.2840.87+Safari%2F537.36``

#### Payment Arguments

* **payment_amount** *[int]*  

    The amount of the payment, if applicable.  

    * **Example**  

        Sets the payment amount of *$2500*.  

            payment_amount=2500

* **payment_date** *[string]*  

    The date of the payment, if applicable.

    * **Example**  

        Sets the payment date as *2016-11-28 18:12:17*.  

            payment_date=2016-11-28+18%3A12%3A17

* **payment_method** *[string]*  

    The payment method for the payment, if applicable.  

    * **Example**  

        Sets the payment method as *Stripe*.  

            payment_method=Stripe

* **payment_status** *[string]*  

    The status of the payment, if applicable.  

    * **Example**  

        Sets the payment status as *Paid*.  

            payment_status=Paid

* **transaction_id** *[string]*  

    The transaction ID for the payment, if applicable.  

    * **Example**  

        Sets the transaction ID as *1234*.  

            transaction_id=1234

* **transaction_type** *[string]*  

    The type of the transaction, if applicable.  

    * **Example**  

        Sets the *Subscription* transaction type.  

            transaction_type=Subscription

------------------------------------------------------------------------------------------------------------------------

### GET /forms/[FORM_ID]/results

Gets form details, including entry details.

#### Path

    https://localhost/wp-json/gf/v2/forms/1/results

#### Response

```json
{
  "entry_count": "60",
  "field_data": {
    "1":  29,
    "2":  7,
    "13": 12,
    "3": {
      "Drop Down First Choice":  52,
      "Drop Down Second Choice": 0,
      "Drop Down Third Choice":  0
    },
    "4": {
      "Multi Select First Choice":  2,
      "Multi Select Second Choice": 0,
      "Multi Select Third Choice":  0
    },
    "5": 2,
    "6": {
      "Checkboxes First Choice":  2,
      "Checkboxes Second Choice": 0,
      "Checkboxes Third Choice":  0
    },
    "7": {
      "Radio Buttons First Choice":  0,
      "Radio Buttons Second Choice": 0,
      "Radio Buttons Third Choice":  0
    },
    "8":  0,
    "9":  0,
    "10": 0,
    "14": 6,
    "15": 3,
    "16": 0,
    "17": 2,
    "18": 0,
    "19": 0,
    "20": 0,
    "21": 0,
    "22": 8,
    "23": 1,
    "24": 0
  },
  "status":    "complete",
  "timestamp": 1480536695,
  "_labels": {
    "1":  "Single Line Text",
    "2":  "Paragraph Text",
    "13": "File",
    "3": {
      "label": "Drop Down",
      "choices": {
        "Drop Down First Choice":  "Drop Down First Choice",
        "Drop Down Second Choice": "Drop Down Second Choice",
        "Drop Down Third Choice":  "Drop Down Third Choice"
      }
    },
    "4": {
      "label": "Multi Select",
      "choices": {
        "Multi Select First Choice":  "Multi Select First Choice",
        "Multi Select Second Choice": "Multi Select Second Choice",
        "Multi Select Third Choice":  "Multi Select Third Choice"
      }
    },
    "5": "Number",
    "6": {
      "label": "Checkboxes",
      "choices": {
        "Checkboxes First Choice":  "Checkboxes First Choice",
        "Checkboxes Second Choice": "Checkboxes Second Choice",
        "Checkboxes Third Choice":  "Checkboxes Third Choice"
      }
    },
    "7": {
      "label": "Radio Buttons",
      "choices": {
        "Radio Buttons First Choice":  "Radio Buttons First Choice",
        "Radio Buttons Second Choice": "Radio Buttons Second Choice",
        "Radio Buttons Third Choice":  "Radio Buttons Third Choice"
      }
    },
    "8":  "Hidden Field",
    "9":  "HTML Block",
    "10": "Section Break",
    "14": "List",
    "15": "Date",
    "16": "Phone",
    "17": "Post Body",
    "18": "Name",
    "19": "Name",
    "20": "Name",
    "21": "Email",
    "22": "Product Name",
    "23": "Total",
    "24": "Coupon"
  }
}
```

#### Optional Arguments

* **search** *[json]*

    The search criteria.

    * **Parameters**

      * **field_filters** *[array]*  
        An array of filters to search by.
      * **key** *[int|float]*  
        The field ID.
      * **value**  *[string]*  
        The value to search for.
      * **operator** *[string]*  
        The comparison operator to use.

    * **Usage**

        ```json
        {
          "field_filters": [{
            "key":      1,
            "value":    "Field Value",
            "operator": "contains"
          }]
        }
        ```


------------------------------------------------------------------------------------------------------------------------

### POST /forms/[FORM_ID]/submissions

Submits the specified form ID with the specified values.

#### Path

    https://localhost/wp-json/gf/v2/forms/1/submissions

#### Response

#### Required Arguments

* **input_[FIELD_ID]** *[string]*  

    The input values. Replace field ID with the input that you want to submit data for.

#### Returns

```json
{
  "is_valid":             true,
  "page_number":          0,
  "source_page_number":   1,
  "confirmation_message": "<div id='gform_confirmation_wrapper_6' class='gform_confirmation_wrapper '><div id='gform_confirmation_message_6' class='gform_confirmation_message_6 gform_confirmation_message'>Thanks for contacting us! We will get in touch with you shortly.<\/div><\/div>"
}
```

#### Optional Arguments

* **field_values** *[string]*  

    The field values.

* **source_page** *[string]*  

    The source page number.

* **target_page** *[string]*  

    The target page number.

------------------------------------------------------------------------------------------------------------------------

### GET /forms/[FORM_ID]/feeds

Returns the feeds for the specified form ID.

#### Path

    https://localhost/wp-json/gf/v2/forms/1/feeds

#### Response

An array of feeds.

#### Optional URL Parameters

* **include** *[array]*  

    An array of feed IDs to include in the response. e.g. include[0]=1&include[1]=2

* **addon** *[string]*  

    The slug of a feed add-on.


------------------------------------------------------------------------------------------------------------------------

### POST /forms/[FORM_ID]/feeds

Adds a feed for the specified form ID.

#### Path

    https://localhost/wp-json/gf/v2/forms/36/feeds

#### Response

The newly created feed.

#### Arguments

* **meta** *[object]*  

    The feed meta.

* **addon_slug** *[object]*  

    The add-on slug for the feed.

#### Optional URL Parameter

* **include** *[array]*  

    An array of feed IDs to include in the response. e.g. include[0]=1&include[1]=2


**Example Payload**

```json
{
  "addon_slug": "gravityformstestaddon",
  "meta": {
    "textField": "My Value"
  }
}
```

**Example Response**

```json
{
  "addon_slug": "gravityformstestaddon",
  "meta": {
    "textField": "My Value"
  },
  "form_id": 36,
  "id": 31
}
```

------------------------------------------------------------------------------------------------------------------------

### GET /feeds

Returns all the feeds optionally filtered by ID and/or add-on slug.

#### Path

    https://localhost/wp-json/gf/v2/feeds

#### Response

An array of feeds.

#### Optional URL Parameters

* **include** *[array]*  

    An array of feed IDs to include in the response. e.g. include[0]=1&include[1]=2

* **addon** *[string]*  

    The slug of a feed add-on.


**Example Response**

```json
[
  {
    "id": "31",
    "form_id": "36",
    "addon_slug": "gravityformstestaddon",
    "meta": {
      "textField": "My Value"
    }
  }
]
```

------------------------------------------------------------------------------------------------------------------------

### POST /feeds

Adds a feed.

#### Path

    https://localhost/wp-json/gf/v2/feeds

#### Response

The newly created feed.

#### Arguments

* **meta** *[object]*  

    The feed meta.

* **addon_slug** *[object]*  

    The add-on slug for the feed.

**Example Payload**

```json
{
  "addon_slug": "gravityformstestaddon",
  "meta": {
    "textField": "My Value"
  },
  "form_id": 36
}
```

**Example Response**

```json
{
    "id": "31",
    "form_id": "36",
    "addon_slug": "gravityformstestaddon",
    "meta": {
      "textField": "My Value"
    }
}
```

------------------------------------------------------------------------------------------------------------------------

### PUT /feeds/[FEED ID]

Updates a feed.

#### Path

    https://localhost/wp-json/gf/v2/feeds/34

#### Response

The updated feed.

#### Arguments

* **meta** *[object]*  

    The feed meta.

* **addon_slug** *[string]*  

    The add-on slug for the feed.

* **form_id** *[integer]*  

    The form ID for the feed.

**Example Payload**

```json
{
  "addon_slug": "gravityformstestaddon",
  "meta": {
    "feedName": "My Value2"
  },
  "form_id": 36
}
```

**Example Response**

```json
{
  "id": "34",
  "form_id": "36",
  "addon_slug": "gravityformstestaddon",
  "meta": {
    "feedName": "My Value2"
  }
}
```

------------------------------------------------------------------------------------------------------------------------

### DELETE /feeds/[FEED ID]

Deleted a feed.

#### Path

    https://localhost/wp-json/gf/v2/feeds/34

#### Response

The result and, if successful, the deleted feed.

**Example Response**

```json
{
  "deleted": true,
  "previous": {
    "id": "34",
    "form_id": "36",
    "addon_slug": "gravityformstestaddon",
    "meta": {
      "feedName": "My Value2"
    }
  }
}
```