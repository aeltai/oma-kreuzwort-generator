<?php

namespace App\Services;

use Normalizer;

class WordNormalizer
{
    /**
     * Language-aware word normaliser. Ported from the original Node `normalise`.
     */
    public static function normalise(string $word, string $lang = 'de'): string
    {
        if ($lang === 'es' || $lang === 'it') {
            $up = mb_strtoupper($word, 'UTF-8');
            $decomposed = Normalizer::normalize($up, Normalizer::FORM_D) ?: $up;
            // strip combining marks
            $decomposed = preg_replace('/\p{Mn}+/u', '', $decomposed);
            return preg_replace('/[^A-Z]/', '', $decomposed);
        }

        if ($lang === 'tr') {
            $up = mb_strtoupper($word, 'UTF-8');
            // dotless ı → I, dotted İ → I
            $up = str_replace(["\u{0131}", "\u{0130}"], 'I', $up);
            // keep A-Z plus Turkish uppercase specials
            return preg_replace('/[^A-ZÇĞÖŞÜ]/u', '', $up);
        }

        if ($lang === 'ru') {
            $up = mb_strtoupper($word, 'UTF-8');
            return preg_replace('/[^А-ЯЁ]/u', '', $up);
        }

        if ($lang === 'el') {
            $up = mb_strtoupper($word, 'UTF-8');
            $decomposed = Normalizer::normalize($up, Normalizer::FORM_D) ?: $up;
            // strip combining marks + tonos/dialytika
            $decomposed = preg_replace('/[\x{0300}-\x{036f}\x{0384}\x{0385}]/u', '', $decomposed);
            return preg_replace('/[^ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩ]/u', '', $decomposed);
        }

        if ($lang === 'ar') {
            $s = $word;
            // strip harakat / wasla
            $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0671}]/u', '', $s);
            // normalize alef variants
            $s = preg_replace('/[آأإٱ]/u', 'ا', $s);
            // ta marbuta → ta
            $s = str_replace('ة', 'ت', $s);
            // alef maqsura → ya
            $s = str_replace('ى', 'ي', $s);
            // keep basic Arabic letters
            return preg_replace('/[^\x{0621}-\x{063A}\x{0641}-\x{064A}]/u', '', $s);
        }

        // de (default)
        $up = mb_strtoupper($word, 'UTF-8');
        $up = str_replace(['Ä', 'Ö', 'Ü', 'ß'], ['AE', 'OE', 'UE', 'SS'], $up);
        return preg_replace('/[^A-Z]/', '', $up);
    }

    /** Length in (multibyte) characters. */
    public static function len(string $s): int
    {
        return mb_strlen($s, 'UTF-8');
    }

    /** Split a normalised string into an array of characters. */
    public static function chars(string $s): array
    {
        return $s === '' ? [] : mb_str_split($s, 1, 'UTF-8');
    }
}
