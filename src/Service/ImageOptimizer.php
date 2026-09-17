<?php

namespace App\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;

class ImageOptimizer
{
    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1080;

    private $imagine;

    public function __construct()
    {
        $this->imagine = new Imagine();
    }

    public function resize(string $filename): void
    {
        list($iwidth, $iheight, $imageType) = getimagesize($filename);
        $ratio = $iwidth / $iheight;
        $width = self::MAX_WIDTH;
        $height = self::MAX_HEIGHT;
        if ($width / $height > $ratio) {
            $width = $height * $ratio;
        } else {
            $height = $width / $ratio;
        }

        $exif = [];
        if (\function_exists('exif_read_data') && $imageType === IMAGETYPE_JPEG) {
            // EXIF is optional: missing or unreadable metadata must not prevent uploads.
            $exif = @\exif_read_data($filename) ?: [];
        }

        $photo = $this->imagine->open($filename);
        $photo->resize(new Box($width, $height))->save($filename);

        if(!empty($exif['Orientation']) && $exif['Orientation'] == 6){
            $source = imagecreatefromjpeg($filename);
            $rotate = imagerotate($source, -90, 0);
            imagejpeg($rotate, $filename);
        } elseif (!empty($exif['Orientation']) && $exif['Orientation'] == 8){
            $source = imagecreatefromjpeg($filename);
            $rotate = imagerotate($source, 90, 0);
            imagejpeg($rotate, $filename);
        } elseif (!empty($exif['Orientation']) && $exif['Orientation'] == 3){
            $source = imagecreatefromjpeg($filename);
            $rotate = imagerotate($source, 180, 0);
            imagejpeg($rotate, $filename);
        }
    }
}
