<?php

namespace App\Tests\Service;

use App\Service\ImageOptimizer;
use PHPUnit\Framework\TestCase;

class ImageOptimizerTest extends TestCase
{
    /** @dataProvider imageFormats */
    public function testResizeWithoutExifMetadata(string $format, int $expectedType): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'optimizer-');
        rename($filename, $filename . '.' . $format);
        $filename .= '.' . $format;
        $image = imagecreatetruecolor(2400, 1200);

        try {
            if ($format === 'jpeg') {
                imagejpeg($image, $filename);
            } else {
                imagepng($image, $filename);
            }

            (new ImageOptimizer())->resize($filename);

            $size = getimagesize($filename);
            self::assertSame(1920, $size[0]);
            self::assertSame(960, $size[1]);
            self::assertSame($expectedType, $size[2]);
        } finally {
            unlink($filename);
        }
    }

    public function imageFormats(): array
    {
        return [
            'JPEG without metadata' => ['jpeg', IMAGETYPE_JPEG],
            'PNG without EXIF support' => ['png', IMAGETYPE_PNG],
        ];
    }
}
