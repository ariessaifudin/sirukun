<?php
namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;

final class LetterPdf
{
    public static function create(array $snapshot): string
    {
        $matrix = Encoder::encode($snapshot['verifikasi'], ErrorCorrectionLevel::M())->getMatrix();
        $pdf = Pdf::loadView('surat-pdf', ['s'=>$snapshot])->setPaper('a4')
            ->setOptions(['isRemoteEnabled'=>false, 'defaultFont'=>'DejaVu Sans']);
        $pdf->render();
        // Vector rectangles bypass Dompdf's plaintext temporary SVG/image cache.
        $pdf->getDomPDF()->getCanvas()->page_script(function($number, $count, $canvas, $fonts) use ($matrix) {
            if ($number !== $count) return;
            $size = $matrix->getWidth(); $cell = 90 / ($size + 8);
            $left = 52; $top = $canvas->get_height() - 123;
            $canvas->filled_rectangle($left, $top, 90, 90, [1,1,1]);
            for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) {
                if ($matrix->get($x, $y) === 1) $canvas->filled_rectangle($left+($x+4)*$cell, $top+($y+4)*$cell, $cell, $cell, [0,0,0]);
            }
            $font = $fonts->getFont('DejaVu Sans');
            $canvas->text(155, $top+24, 'Pindai QR untuk memeriksa nomor dan status surat.', $font, 8);
            $canvas->text(155, $top+39, 'Pengesahan adalah rekaman persetujuan sistem;', $font, 8);
            $canvas->text(155, $top+52, 'bukan tanda tangan elektronik tersertifikasi.', $font, 8);
        });
        return $pdf->output();
    }
}
