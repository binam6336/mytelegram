<?php

namespace Tests\Webrtc\Webrtc;

use Override;
use Amp\Pipeline\DisposedException;
use Revolt\EventLoop;
use Webrtc\Codecs\EncodedPacket;
use Webrtc\RTP\Enum\MediaKind;
use Webrtc\RTP\MediaStreamTrack\MediaStreamTrack;

use function Amp\delay;

final class PreEncodedAudioStreamTrack extends MediaStreamTrack
{
    private const INTERVAL = 0.960;

    public function __construct(?string $id = null)
    {
        parent::__construct(MediaKind::Audio, $id);
        EventLoop::queue(function () {
            $timestamp = 0;
            while (!$this->isEnded()) {
                $packet = new EncodedPacket("\xf8\xff\xfe", $timestamp, audioLevel: 127);
                try {
                    $this->push($packet);
                } catch (DisposedException) {
                    // Stopped.
                    return;
                }
                $timestamp += 960;
                delay(self::INTERVAL);
            }
        });
    }

    #[Override]
    public function stop(): void
    {
        parent::stop();
    }
}
