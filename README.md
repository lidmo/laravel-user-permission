# Associate users with permissions

## What It Does
This package allows you to manage user permissions and users in a database.

Once installed you can do stuff like this:

```php
$user->givePermissionTo('edit articles');
```

Because all permissions will be registered on [Laravel's gate](https://laravel.com/docs/authorization), you can check if a user has a permission with Laravel's default `can` function:

```php
$user->can('edit articles');
```

### Testing

``` bash
composer test
```
