# Laravel Image Sizes
A Laravel package to store images in different sizes. There are also options for various video provider urls.

This package was created for a personal project and I publish it in case anyone has the same needs. Cheers :)

## Requirements ##
```
PHP 8+
Laravel 9+
```

## Installation ##

### Step 1: Install johntout/laravel-image-sizes with composer:

```
composer require johntout/laravel-image-sizes
```

The package is using intervention/image package for image manipulation. Before using laravel-image-sizes package, make sure to checkout the [intervention/image docs](https://image.intervention.io/v2/introduction/installation) for laravel integration.

### Step 2: Publish config file

```
php artisan vendor:publish --tag="laravel-image-sizes-config"
```

If you want to use Twitch video provider to embed player to your app, add this to your .env file.

```
APP_DOMAIN=yourdomain.com
```

## Usage ##

Use properties or Laravel Attributes to set in which disk and directory you want to create the images. The
package uses Laravel's filesystem. The different sizes of the images are defined in the config file.

```php
class User extends Model 
{
    use JohnTout\LaravelImageSizes\HasMedia;
    
    public filesystem_disk = 'avatars';
    
    public image_field = 'avatar';
    
    // OR
    
    /**
     * @return Attribute
     */
    public function filesystemDisk(): Attribute
    {
        return Attribute::make(
            get: fn () => 'avatars'
        );
    }

    /**
     * @return Attribute
     */
    public function imageField(): Attribute
    {
        return Attribute::make(
            get: fn () => 'avatar'
        );
    }
}
```

```php
$user = User::query()->find(1);
$user->saveImage($request->file('image'));
```

You can get the image url using this:
```php
$user->imageUrl(size: 'originalImage');
```
## Notes ##

The package is using v2 intervention/image package. For more information about image manipulation options, see [intervention/image docs](https://image.intervention.io/v2).