clapp/imagerepository [![Build Status](https://travis-ci.org/clappcom/imagerepository.svg?branch=master)](https://travis-ci.org/clappcom/imagerepository) [![Coverage Status](https://coveralls.io/repos/github/clappcom/imagerepository/badge.svg?branch=master)](https://coveralls.io/github/clappcom/imagerepository?branch=master)
===

Usage example (in Laravel)
---

```php

use Clapp\ImageRepository\ImageRepository;
use Clapp\ImageRepository\ImageMissingOrInvalidException;

class User {

    protected $profilePictureRepository = null;

    public function __construct(/* ... */){

        $this->profilePictureRepository = new ImageRepository('profile-pictures/');

        /* ... */
    }

    public function getProfilePicture($width=500, $height=500)
    {
        try {
            return $this->profilePictureRepository->get(array_get($this->attributes, 'profile_picture'), $width, $height);
        }catch(ImageMissingOrInvalidException $e){
            return $this->profilePictureRepository->get(resource_path('assets/images/placeholder.png'), $width, $height);
        }
    }

    public function setProfilePictureAttribute($pictureContents){
        $value = $pictureContents;
        if (!empty($value)){
            $value = $this->profilePictureRepository->put($value);
        }
        $this->attributes['profile_picture'] = $value;
    }
}

```

API reference
---

- [`ImageRepository::__construct($storagePrefix = "", $storageDisk = null, $cacheDisk = null, ImageManager $imageManager = null)`](#imagerepository__constructstorageprefix---storagedisk--null-cachedisk--null-imagemanager-imagemanager--null)
- [`ImageRepository::put($imageContents)`](#imagerepositoryputimagecontents)
- [`ImageRepository::get($key, $width = 500, $height = 500)`](#imagerepositorygetkey-width--500-height--500)
- `ImageRepository::remove($key)`
- `ImageRepository::flush()`

### `ImageRepository::__construct($storagePrefix = "", $storageDisk = null, $cacheDisk = null, ImageManager $imageManager = null)`

Create a new ImageRepository instance.

Params:

- `$storagePrefix`: `string` a prefix to allow multiple collections on the same $storageDisk and $cacheDisk - e.g. `"user-profile-pictures"`
- `$storageDisk`: `Illuminate\Contracts\Filesystem\Filesystem` a disk to store the original images
- `$cacheDisk`: `Illuminate\Contracts\Filesystem\Filesystem` a disk to store the generated image thumbnails
- `$imageManager`: `Intervention\Image\ImageManager` ImageManager to use for image manipulation

### `ImageRepository::put($imageContents)`

Store an image into the ImageRepository instance.

Params:

- `$imageContents`: `mixed` any image format that Intervention\Image\ImageManager::make() can parse

Returns:

- `string` $key that can be used to retrieve the image from `get()`

### `ImageRepository::get($key, $width = 500, $height = 500)`

Params:

- `$key`: `string` key from `put()` OR an absolute path to an image file on your local disk (for placeholders)
- `$width`: `int` fit the image into this width (default: 500)
- `$height`: `int` fit the image into this height (default: 500)

Returns:

- `string` path to the generated image from the base of the $cacheDisk - can be put immediately into laravel's `asset()` function
