<p align="center">
<img src="/docs/logo.png" alt="Redactable Models for Laravel" width="600">
</p>

<p align="center">
<a href="https://packagist.org/packages/ashallendesign/redactable-models"><img src="https://img.shields.io/packagist/v/ashallendesign/redactable-models.svg?style=flat-square" alt="Latest Version on Packagist"></a>
<a href="https://packagist.org/packages/ashallendesign/redactable-models"><img src="https://img.shields.io/packagist/dt/ashallendesign/redactable-models.svg?style=flat-square" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/ashallendesign/redactable-models"><img src="https://img.shields.io/packagist/php-v/ashallendesign/redactable-models?style=flat-square" alt="PHP from Packagist"></a>
<a href="https://github.com/ash-jc-allen/redactable-models/blob/master/LICENSE.md"><img src="https://img.shields.io/github/license/ash-jc-allen/redactable-models?style=flat-square" alt="GitHub license"></a>
</p>

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
    - [Requirements](#requirements)
    - [Install the Package](#install-the-package)
- [Usage](#usage)
    - [Defining Redactable Models](#defining-redactable-models)
    - [The `model:redact` Command](#the-modelredact-command)
    - [Redaction Strategies](#redaction-strategies)
        - [`ReplaceContents`](#replacecontents)
        - [`HashContents`](#hashcontents)
        - [`MaskContents`](#maskcontents)
        - [Custom Redaction Strategies](#custom-redaction-strategies)
    - [Manually Redacting Models](#manually-redacting-models)
    - [Events](#events)
      - [`ModelRedacted`](#modelredacted)
- [Testing](#testing)
- [Security](#security)
- [Credits](#credits)
- [Changelog](#changelog)
- [License](#license)
    
## Overview

> [!IMPORTANT]  
> This package is still in development. There may be some bugs and API changes before the first stable release.

A Laravel package that you can use to redact, obfuscate, or mask fields from your models in a consistent and easy way.

When building web applications, you'll often need to keep hold of old data for auditing or reporting purposes. But for data privacy and security reasons, you may want to redact the sensitive information from the data that you store. This way, you can keep the rows in the database, but without the sensitive information.

This package allows you to define which models and fields should be redacted, and how they should be redacted.

## Installation

### Requirements

The package has been developed and tested to work with the following minimum requirements:

- PHP 8.2
- Laravel 10

### Install the Package

You can install the package via Composer:

```bash
composer require ashallendesign/redactable-models
```

## Usage

### Defining Redactable Models

In order to make a model redactable, you need to add the `AshAllenDesign\RedactableModels\Interfaces\Redactable` interface to the model. This will enforce two new methods (`redactable` and `redactionStrategy`) that you need to implement.

Your model may look something like so:

```php
use AshAllenDesign\RedactableModels\Interfaces\Redactable;
use Illuminate\Contracts\Database\Eloquent\Builder

class User extends Model implements Redactable
{
    // ...
    
    public function redactable(): Builder
    {
        // ...
    }

    public function redactionStrategy(): RedactionStrategy
    {
        // ...
    }
}
```

The `redactable` method allows you to return an instance of `Illuminate\Contracts\Database\Eloquent\Builder` which defines the models that are redactable.

The `redactionStrategy` method allows you to return an instance of `AshAllenDesign\RedactableModels\Interfaces\RedactionStrategy` which defines how the fields should be redacted. We'll cover the available strategies further down.

As an example, if we wanted to redact the `email` and `name` fields from all `App\Models\User` models older than 30 days, we could do the following:

```php
use AshAllenDesign\RedactableModels\Support\Strategies\ReplaceContents;
use AshAllenDesign\RedactableModels\Interfaces\Redactable;
use Illuminate\Contracts\Database\Eloquent\Builder

class User extends Model implements Redactable
{
    // ...
    
    public function redactable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(30));
    }

    public function redactionStrategy(): RedactionStrategy
    {
        return app(ReplaceContents::class)->replaceWith([
            'name' => 'REDACTED',
            'email' => 'redacted@redacted.com',
        ]);
    }
}
```

### The `model:redact` Command

In order to automatically redact the fields on the models, you can use the package's `model:redact` command like so:

```text
php artisan model:redact
```

This will find all the models within your app's `app/Models` directory that implement the `AshAllenDesign\RedactableModels\Interfaces\Redactable` interface and redact the fields based on the defined redaction strategy and query.

You may want to set this to run on a schedule (such as on a daily basis) in your Laravel app's [scheduler](https://laravel.com/docs/11.x/scheduling).

### Redaction Strategies

The package ships with several strategies that you can use redacting fields:

#### `ReplaceContents`

The `ReplaceContents` strategy allows you to replace the contents of the fields with a specified value.

For example, if we wanted to replace the `name` and `email` fields, we could do the following:

```php
use AshAllenDesign\RedactableModels\Support\Strategies\ReplaceContents;
use AshAllenDesign\RedactableModels\Interfaces\Redactable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements Redactable
{
    // ...

    public function redactionStrategy(): RedactionStrategy
    {
        return app(ReplaceContents::class)->replaceWith([
            'name' => 'REDACTED',
            'email' => 'redacted@redacted.com',
        ]);
    }
}
```

Running this against a model would replace the `name` field with `REDACTED` and the `email` field with `redacted@redacted.com`.

The `ReplaceContents` strategy also allows you to use a closure to define the replacement value. This can be useful if you want a bit more control over the redaction process. The closure should accept the model as an argument and have a `void` return type.

For example, say you want to replace the `name` field with `name_` followed by their ID. You could do the following:

```php
use AshAllenDesign\RedactableModels\Support\Strategies\ReplaceContents;
use AshAllenDesign\RedactableModels\Interfaces\Redactable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements Redactable
{
    // ...

    public function redactionStrategy(): RedactionStrategy
    {
        return app(ReplaceContents::class)->replaceWith(function (User $user): void {
            $user->name = 'name_'.$user->id;
        });
    }
}
```

Imagine we have a user with ID `123` and a `name` of `John Doe`. Running the above code would replace the `name` field with `name_123`.

#### `HashContents`

The `HashContents` strategy allows you to MD5 hash the contents of the field.

This can be useful when you still want to be able to compare the redacted fields, but don't want to expose the original data.

For example, imagine you have an `invitations` table that contains an `email` field. You may want to find out how many unique email addresses have been invited, but you don't want to expose the email addresses themselves. You could do the following:

```php
use AshAllenDesign\RedactableModels\Support\Strategies\HashContents;
use AshAllenDesign\RedactableModels\Interfaces\Redactable;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model implements Redactable
{
    // ...

    public function redactionStrategy(): RedactionStrategy
    {
        return app(HashContents::class))->fields([
            'email',
        ]);
    }
}
```

#### `MaskContents`

The `MaskContents` strategy allows you to mask the contents of the field with a specified character.

You can define the character to use for the mask, and how many characters from the start and end of the field to leave unmasked like so:

```php
use AshAllenDesign\RedactableModels\Support\Strategies\MaskContents;
use AshAllenDesign\RedactableModels\Interfaces\Redactable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements Redactable
{
    // ...

    public function redactionStrategy(): RedactionStrategy
    {
        return app(MaskContents::class)
            ->mask(field: 'name', character: '*', index: 0, length: 4)
            ->mask(field: 'email', character: '-', index: 2, length: 3);
    }
}
```

In the above example, the `name` field would be masked with `*` and the first 4 characters would be left unmasked. The `email` field would be masked with `-` and the first 2 and last 3 characters would be left unmasked.

This means if a user's name was "Ash Allen" and their email was "ash@example.com", after redaction their name would be "****Allen" and their email would be "as---xample.com".

#### Custom Redaction Strategies

Although the package ships with several redaction strategies out of the box, you can create your own custom redaction strategies.

You just need to create a class that implements the `AshAllenDesign\RedactableModels\Interfaces\RedactionStrategy` interface. This method enforces an `apply` method which accepts the model and defines the redaction logic.

An example of a custom redaction strategy might look like so:

```php
use AshAllenDesign\RedactableModels\Interfaces\Redactable;
use AshAllenDesign\RedactableModels\Interfaces\RedactionStrategy;
use Illuminate\Database\Eloquent\Model;

class CustomStrategy implements RedactionStrategy
{
    public function apply(Redactable&Model $model): void
    {
        // Redaction logic goes here
    }
}
```

### Manually Redacting Models

There may be times when you want to manually redact a model rather than using the `model:redact` command. To do this you can use the `redactFields` method that is available via the `AshAllenDesign\RedactableModels\Traits\HasRedactableFields` trait.

You can apply the trait to your model like so:

```php
use AshAllenDesign\RedactableModels\Interfaces\Redactable;
use AshAllenDesign\RedactableModels\Traits\HasRedactableFields;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements Redactable
{
    use HasRedactableFields;
    
    // ...
}
```

You can now use the `redactFields` method to redact the fields on the model like so:

```php
$user = User::find(1);

$user->redactFields();
```

By default, this will redact the fields using the strategy defined in the model's `redactionStrategy` method.

You can override this by passing a custom redaction strategy to the `redactFields` method like so:

```php
use App\Models\User;
use AshAllenDesign\RedactableModels\Support\Strategies\ReplaceContents;

$user = User::find(1);

$user->redactFields(
    strategy: app(ReplaceContents::class)->replaceWith(['name' => 'REDACTED'])
);
```

### Events

#### `ModelRedacted`

When a model is redacted, an `AshAllenDesign\RedactableModels\Events\ModelRedacted` event is fired that can be listened on.

## Testing

To run the package's unit tests, run the following command:

``` bash
vendor/bin/phpunit
```

## Security

If you find any security related issues, please contact me directly at [mail@ashallendesign.co.uk](mailto:mail@ashallendesign.co.uk) to report it.

## Contribution

If you wish to make any changes or improvements to the package, feel free to make a pull request.

Note: A contribution guide will be added soon.

## Credits

- [Ash Allen](https://ashallendesign.co.uk)
- [Jess Allen](https://jesspickup.co.uk) (Logo)
- [All Contributors](https://github.com/ash-jc-allen/redactable-models/graphs/contributors)

## Changelog

Check the [CHANGELOG](CHANGELOG.md) to get more information about the latest changes.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support Me

If you've found this package useful, please consider buying a copy of [Battle Ready Laravel](https://battle-ready-laravel.com) to support me and my work.

Every sale makes a huge difference to me and allows me to spend more time working on open-source projects and tutorials.

To say a huge thanks, you can use the code **BATTLE20** to get a 20% discount on the book.

[👉 Get Your Copy!](https://battle-ready-laravel.com)

[![Battle Ready Laravel](https://ashallendesign.co.uk/images/custom/sponsors/battle-ready-laravel-horizontal-banner.png)](https://battle-ready-laravel.com)
