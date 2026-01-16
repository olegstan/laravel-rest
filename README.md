# `olegstan/laravel-rest` Package Documentation

## Overview

The `olegstan/laravel-rest` package offers a streamlined approach to building RESTful APIs with Laravel, emphasizing convention over configuration. It automatically sets up API routes and organizes the application structure to enhance the development of REST APIs. This document guides you through the installation, setup, and basic usage of the package.

## Installation

Begin by installing the package through Composer:

```bash
composer require olegstan/laravel-rest
```

This command adds the `olegstan/laravel-rest` package to your project, enabling its features and functionalities.

## Associated Package

For optimal use of `olegstan/laravel-rest`, install the related `laravel-request` package, which complements the main package by handling request validations and more. Find it at:

```
https://github.com/olegstan/laravel-request
```

## Configuration

After installation, publish the package's assets and configurations to your project:

```bash
php artisan vendor:publish --provider="LaravelRest\LaravelRestServiceProvider"
```

This command copies necessary files into your project, setting the stage for the API's structure and behavior.

## Automatic API Routing

With the package published, API routes are automatically configured. The routing convention follows the pattern:

```
/api/v1/call/{controllerName}/{functionName}
```

This pattern facilitates direct access to controller methods via API endpoints. For instance, to access the `postStore` method in `ClientController`, you would use:

- **URL:** `/api/v1/call/client/store`
- **Method:** POST

## Project Structure

The package organizes your Laravel project in a manner conducive to API development, as follows:

```
/App
    /Api
        /Controllers
            /Common - Controllers without authentication requirements
            /AnyRole - Controllers for specific role-based access
        /Helpers - Utility functions and classes
        /Requests - Custom request validation classes
        /Transformers
            /Base - Common data transformers
            /AnyRole - Role-specific data transformers
```

### Controllers and Requests

Example request handling:

```javascript
const form = new FormData();
form.append('data[user_id]', 10);
form.append('data[first_name]', 'Andrey');
form.append('data[last_name]', 'Kirov');

const requestOptions = {
  method: "POST",
  body: form
};

let url = SERVER_API + '/api/v1/call/client/store';

await fetch(url, requestOptions)
  .then(response => response.json())
  .then(json => { /* Handle the JSON response */ })
  .catch(error => { /* Handle errors */ });
```

### Handling Data in Controllers

Data sent to an endpoint, such as in the example above, is easily retrievable in the controller:

```php
class ClientController extends BaseController
{
    public function postStore(Request $request)
    {
        $userId = $request->input('user_id');
        $firstName = $request->input('first_name');
        $lastName = $request->input('last_name');

        // Implement your logic here

        return $this->response()->success();
    }
}
```

## Support

For questions or further assistance with the `olegstan/laravel-rest` package, please reach out to olegstan@inbox.ru.