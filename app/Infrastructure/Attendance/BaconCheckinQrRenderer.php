<?php

namespace App\Infrastructure\Attendance;

use App\Application\Attendance\Contracts\CheckinQrRendererInterface;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class BaconCheckinQrRenderer implements CheckinQrRendererInterface
{
    public function svgForCode(string $code): string
    {
        $url = route('attendance.staff.index', ['code' => $code, 'via' => 'qr']);
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($url);

        // Drop the XML prolog so the markup can be embedded inline.
        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}
