<?php

namespace JohnTout\LaravelImageSizes;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Ramsey\Uuid\Uuid;

trait HasMedia
{
    public string|null $generatedImageName = null;

    public string|null $uploadError = null;

    public function objectId(): string
    {
        if ($this instanceof Model) {
            return $this->primaryKey;
        }

        return $this->id_field ?? now()->toDateTimeString();
    }

    /**
     * @return string
     */
    public function objectImageField(): string
    {
        return $this->image_field ?? config('image-sizes.image_field');
    }

    /**
     * @return string
     */
    public function objectVideoField(): string
    {
        return $this->video_field ?? config('image-sizes.video_field');
    }

    /**
     * @return string
     */
    public function objectMediaDisk(): string
    {
        return $this->filesystem_disk ?? config('image-sizes.filesystem_disk');
    }

    /**
     * @return string
     */
    public function getObjectId(): string
    {
        return (string) $this->{$this->objectId()};
    }

    /**
     * @return string
     */
    public function getObjectVideo(): string
    {
        return (string) $this->{$this->objectVideoField()};
    }

    /**
     * @return string
     */
    public function getObjectImage(): string
    {
        return (string) $this->{$this->objectImageField()};
    }

    /**
     * @throws Exception
     * @return void
     */
    public function checkAttributesBeforeDbAction(): void
    {
        if (! $this instanceof Model) {
            throw new Exception('Object must extend Laravel\'s Model abstract class to perform db actions!');
        }

        if (! array_key_exists($this->objectImageField(), $this->attributes)) {
            throw new Exception('Object must have '.$this->objectImageField().' attribute!');
        }
    }

    /**
     * @throws Exception
     * @return void
     */
    public function checkLocalDiskExists(): void
    {
        if (! config('filesystems.disks.local')) {
            throw new Exception('Disk "local" do not exist!');
        }
    }

    /**
     * @param UploadedFile $image
     * @return void
     */
    public function generateImageName(UploadedFile $image): void
    {
        $imageName = preg_replace('/\s+/', '', $image->getClientOriginalName());
        $this->generatedImageName = Str::of($imageName)->replace($image->getClientOriginalExtension(), 'webp');
    }

    /**
     * @param UploadedFile $image
     * @param array $sizes
     * @return bool
     * @throws Exception
     */
    public function saveImage(UploadedFile $image, array $sizes = ['*']): bool
    {
        $this->checkAttributesBeforeDbAction();

        $this->deleteImage();

        try {
            $this->generateImageName($image);

            $imageSizes = config('image-sizes.sizes');

            if (! in_array('*', $sizes)) {
                $filtered = collect($imageSizes)
                    ->filter(function ($value, $key) use ($sizes) {
                        return in_array($key, $sizes);
                    });

                $imageSizes = $filtered->all();
            }

            foreach ($imageSizes as $size => $options) {
                $this->optimizeAndUploadImage(
                    image: $image,
                    size: $size,
                    options: $options
                );
            }

            $this->setAttribute($this->objectImageField(), $this->generatedImageName);
            $this->save();

            $uploaded = true;
        } catch (\Throwable $e) {
            $uploaded = false;
            $this->deleteImage();
            $this->uploadError = $e->getMessage();
        }

        return $uploaded;
    }

    /**
     * @throws Exception
     * @return void
     */
    public function deleteImage(): void
    {
        $this->checkAttributesBeforeDbAction();

        $deleted = false;
        $disk = $this->objectMediaDisk();

        if (Storage::disk($disk)->exists($this->getObjectId().'/images')) {
            $deleted = Storage::disk($disk)->deleteDirectory($this->getObjectId().'/images');
        }

        if ($deleted) {
            $this->setAttribute($this->objectImageField(), null);
            $this->save();
        }
    }

    /**
     * @param UploadedFile $image
     * @param string $size
     * @param array $options
     * @return void
     * @throws Exception
     */
    public function optimizeAndUploadImage(UploadedFile $image, string $size, array $options): void
    {
        $this->checkLocalDiskExists();

        if (empty($this->generatedImageName)) {
            $this->generateImageName($image);
        }

        $localUniqueDirectory = Uuid::uuid4().now()->timestamp;
        $localSizeDirectory = $localUniqueDirectory.'/'.$this->getObjectId().'/images/'.$size;

        $image->storeAs($localSizeDirectory, $this->generatedImageName, ['disk' => 'local']);
        $imagePath = Storage::disk('local')->path($localSizeDirectory).'/'.$this->generatedImageName;

        if (isset($options['size'])) {
            Image::read($imagePath)
                ->encode(config('image-sizes.encode'))
                ->resize($options['size']['width'], $options['size']['height'], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->save();
        } else {
            Image::make($imagePath)->encode(config('image-sizes.encode'));
        }

        $image = new UploadedFile(path: $imagePath, originalName: $this->generatedImageName);

        $image->storeAs(
            $this->getObjectId().'/images/'.$size, $this->generatedImageName,
            ['disk' => $this->objectMediaDisk()]
        );

        Storage::disk('local')->deleteDirectory($localUniqueDirectory);
    }

    /**
     * @param $source
     * @param $video
     * @return string|null
     */
    public function encodeVideoSource($source, $video): ?string
    {
        if ($source != 'HTML') {
            $encoded = '{'.$source.'}'.$video.'{/'.$source.'}';
        } else {
            $encoded = $video;
        }

        return $encoded;
    }

    /**
     * @param  string  $video
     * @param  string  $provider
     * @return string|null
     */
    public function stripVideoProviderTags(string $video, string $provider): ?string
    {
        return Str::of($video)
            ->replace('{'.$provider.'}', '')
            ->replace('{/'.$provider.'}', '');
    }

    /**
     * @return string|null
     */
    public function videoProvider(): ?string
    {
        $video = $this->getObjectVideo();

        if (empty($video)) {
            return null;
        }

        $provider = null;

        foreach (config('image-sizes.video_providers') as $videoProvider) {
            if (Str::of($video)->containsAll([
                '{'.$videoProvider.'}',
                '{/'.$videoProvider.'}',
            ])) {
                $provider = $videoProvider;
                break;
            }
        }

        if (is_null($provider)) {
            $provider = 'HTML';
        }

        return $provider;
    }

    /**
     * @return string|null
     */
    public function videoWithoutTags(): ?string
    {
        return $this->stripVideoProviderTags($this->getObjectVideo(), (string) $this->videoProvider());
    }

    /**
     * @return string|null
     */
    public function videoUrl(): ?string
    {
        $videoProvider = $this->videoProvider();

        if ($videoProvider == 'HTML' || empty($videoProvider)) {
            return null;
        }

        $providerUrl = config('image-sizes.video_providers_urls')[$videoProvider];
        $videoId = $this->stripVideoProviderTags($this->getObjectVideo(), (string) $videoProvider);

        return Str::of($providerUrl)->replace('{video}', $videoId);
    }

    /**
     * @param string $size
     * @param null $default
     * @return string|null
     */
    public function imageUrl(string $size = 'originalImage', $default = null): ?string
    {
        $image = $this->getObjectImage();

        if (is_null($default)) {
            $default = config('image-sizes.preview_image_url');
        }

        if (! $image) {
            return $default;
        }

        return Storage::disk($this->objectMediaDisk())
            ->url($this->getObjectId().'/images/'.$size.'/'.$image);
    }

    public function imageExists(string $size = 'originalImage'): bool
    {
        return Storage::disk($this->objectMediaDisk())
            ->exists($this->getObjectId().'/images/'.$size.'/'.$this->getObjectImage());
    }
}
