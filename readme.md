clapp/imagerepository [![Build Status](https://travis-ci.org/clappcom/imagerepository.svg?branch=master)](https://travis-ci.org/clappcom/imagerepository) [![Coverage Status](https://coveralls.io/repos/github/clappcom/imagerepository/badge.svg?branch=master)](https://coveralls.io/github/clappcom/imagerepository?branch=master)

Usage example
---

```

use Clapp\ImageRepository\ImageRepository;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Intervention\Image\Exception\NotReadableException;

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
        }catch(FileNotFoundException $e){
            return $this->profilePictureRepository->get(resource_path('assets/images/placeholder.png'), $width, $height);
        }catch(NotReadableException $e){
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
