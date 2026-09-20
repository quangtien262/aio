<?php

namespace App\Support;

class LegacyTextEncoding
{
    public function repair(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        // Split around valid Unicode that cannot originate from Windows-1252.
        $result = $segment = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $character) {
            $byte = mb_convert_encoding($character, 'Windows-1252', 'UTF-8');
            if (preg_match('/[ \t\r\n<>\x22\x27{}\[\]]/u', $character)
                || mb_convert_encoding($byte, 'UTF-8', 'Windows-1252') !== $character) {
                $result .= $this->segment($segment).$character;
                $segment = '';
            } else {
                $segment .= $character;
            }
        }

        return $result.$this->segment($segment);
    }

    private function segment(string $text): string
    {
        for ($i = 0; $i < 4; $i++) {
            $candidate = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
            if (! mb_check_encoding($candidate, 'UTF-8')
                || mb_convert_encoding($candidate, 'UTF-8', 'Windows-1252') !== $text
                || $this->score($candidate) >= $this->score($text)) {
                break;
            }
            $text = $candidate;
        }

        return $text;
    }

    private function score(string $text): int
    {
        return preg_match_all('/Ã|Â|â|Ä|Å|Æ|áº|á»/u', $text);
    }

    public function value(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->value($item), $value);
        }

        return is_string($value) ? $this->repair($value) : $value;
    }
}
