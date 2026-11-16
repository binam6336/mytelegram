<?php

namespace Tests\Webrtc\AVCodec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\AVCodec;

#[CoversClass(AVCodec::class)]
final class ErrnoTest extends TestCase
{
    public function testEagainIsTheErrnoOfThePlatform(): void
    {
        $this->assertTrue(AVCodec::isAvailable());
        // FFmpeg asks for more input with AVERROR(EAGAIN), -errno: taken for an error, decoding fails where EAGAIN isn't 11.
        $expected = \defined('SOCKET_EAGAIN') ? SOCKET_EAGAIN : (\in_array(PHP_OS_FAMILY, ['Darwin', 'BSD'], true) ? 35 : 11);
        $this->assertSame($expected, EAGAIN);
    }
}
