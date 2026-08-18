<?php

namespace App\Support\AccountingTax;

use DOMDocument;
use RuntimeException;

class AccountingArtifactValidator
{
    private const MAX_BYTES = 25 * 1024 * 1024;

    public function assertValid(string $format, string $contents): void
    {
        $size = strlen($contents);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Kích thước artifact hóa đơn không hợp lệ.');
        }

        if ($format === 'pdf' && ! str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException('Provider không trả về file PDF hợp lệ.');
        }

        if ($format === 'xml') {
            if (stripos($contents, '<!DOCTYPE') !== false || stripos($contents, '<!ENTITY') !== false) {
                throw new RuntimeException('XML hóa đơn chứa khai báo thực thể không an toàn.');
            }

            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);

            try {
                if (! $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                    throw new RuntimeException('Provider không trả về XML hóa đơn hợp lệ.');
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }
    }
}
