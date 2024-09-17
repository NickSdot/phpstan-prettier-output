<?php

declare(strict_types=1);

namespace NickSdot\PrettierStanOutput;

use PHPStan\Analyser\Error;

use function count;
use function preg_match;
use function preg_quote;
use function str_replace;
use function vsprintf;
use function explode;
use function mb_strlen;
use function array_splice;
use function htmlspecialchars;
use function max;
use function str_ends_with;
use function array_unshift;
use function ltrim;
use function mb_str_pad;
use function trim;

use const ENT_COMPAT;
use const ENT_XML1;

final readonly class Message
{
    private const array PATTERN = [

        // simple
        'return %s but returns %s.' => 'return 1 but returns 2.' . PHP_EOL . PHP_EOL . '   1) %s' . PHP_EOL . '   2) %s' . PHP_EOL,
        'type %s is incompatible with native type %s.' => 'type 1 is incompatible with native type 2.' . PHP_EOL . PHP_EOL . '   1) %s' . PHP_EOL . '   2) %s' . PHP_EOL,
        'expects %s, %s given.' => 'expects 1, 2 given.' . PHP_EOL . PHP_EOL . '   1) %s' . PHP_EOL . '   2) %s' . PHP_EOL,
        'type %s, %s given.' => 'type 1, 2 given.' . PHP_EOL . PHP_EOL . '   1) %s' . PHP_EOL . '   2) %s' . PHP_EOL,

        // comparison
        'between %s and trait %s will' => 'between 1 and trait 2 will %3$s' . PHP_EOL . PHP_EOL . '   1) %1$s' . PHP_EOL . '   2) %2$s' . PHP_EOL,
        'between %s and %s will' => 'between 1 and 2 will %3$s' . PHP_EOL . PHP_EOL . '   1) %1$s' . PHP_EOL . '   2) %2$s' . PHP_EOL,
        'between %s and %s is' => 'between 1 and 2 is %3$s' . PHP_EOL . PHP_EOL . '   1) %1$s' . PHP_EOL . '   2) %2$s' . PHP_EOL,
        'between %s and %s results' => 'between 1 and 2 results  %3$s' . PHP_EOL . PHP_EOL . '   1) %1$s' . PHP_EOL . '   2) %2$s' . PHP_EOL,

        // docs
        'PHPDoc type %s of %%s PHPDoc type %s of %%s.' => 'PHPDoc type 1 of %2$s PHPDoc type 2 of %4$s' . PHP_EOL . PHP_EOL . '   1) %1$s' . PHP_EOL . '   2) %3$s' . PHP_EOL,

        // variants
        '%%s (%s) %%s should be covariant %%s (%s)%%%s' => '%1$s (1) %3$s should be covariant %4$s (2) %6$s' . PHP_EOL . PHP_EOL . '   1) %2$s' . PHP_EOL . '   2) %5$s' . PHP_EOL,
        '%%s (%s) %%s should be contravariant %%s (%s)%%%s' => '%1$s (1) %3$s should be contravariant %4$s (2) %6$s' . PHP_EOL . PHP_EOL . '   1) %2$s' . PHP_EOL . '   2) %5$s' . PHP_EOL,
    ];

    public static function applyCustomFormatting(string $message): string
    {
        foreach (self::PATTERN as $oldPattern => $newPattern) {

            $matchPlaceholders = str_replace(
                [ '%%%s', '%%s', '%s' ],
                [ "\s*(.+?)$", "(.+?)", "([^']+?)" ],
                preg_quote($oldPattern, '/')
            );

            // open end strings without specified end will
            // match the rest until the end of the string.

            $regex = true === str_ends_with($oldPattern, '.') || true === str_ends_with($oldPattern, '%%%s')
                ? '/' . $matchPlaceholders . '/'
                : '/' . $matchPlaceholders . '\s*(.+)/';

            if (false !== preg_match($regex, $message, $matches)) {

                if (0 === count($matches)) {
                    continue;
                }

                $message = str_replace($matches[0], vsprintf($newPattern, array_splice($matches, 1)), $message);
            }
        }

        return trim($message);
    }

    public static function applyCustomFormattingForIdeOutput(Error $error): string
    {
        $lines = explode("\n", self::applyCustomFormatting($error->getMessage()));

        // PhpStorm adds the 'phpstan' string to the first line.
        // this leads to formatting issues with all following
        // lines. For that reason we manually add one line.

        array_unshift($lines, '');

        // We add the identifier to the very
        // first line if we have one.

        if (null !== $identifier = $error->getIdentifier()) {
            $lines[0] .= $identifier;
        }

        // For the same reason as the unshift, we add
        // one extra line which acts as a 'clear'.

        array_splice($lines, 1, 0, [ '' ]);

        // If we have a tip, we add it at the
        // very end of the lines array.

        if (null !== $tip = $error->getTip()) {
            array_splice($lines, count($lines), 0, [ '', $tip ]);
        }

        // Because there can be multiple errors in the
        // same time, we add two separator lines to
        // the very end.

        array_splice($lines, count($lines), 0, [ '', '' ]);

        $lineLength = [];
        $formattedMessage = '';

        foreach ($lines as $key => $line) {

            $line = htmlspecialchars($line, ENT_XML1 | ENT_COMPAT, 'UTF-8');

            $lineLength[$key] = mb_strlen($line);

            if (0 === $key) {
                $lineLength[$key] += 9; // PhpStorm adds "phpstan: " which we need to account for.
            }
        }

        $largestLength = max(max($lineLength), 103);

        foreach ($lines as $key => $line) {

            $finalLength = 0 === $key
                ? $largestLength - 9 // PhpStorm adds "phpstan: " which we need to account for.
                : $largestLength;

            $line = mb_str_pad(
                ltrim(htmlspecialchars($line, ENT_XML1 | ENT_COMPAT, 'UTF-8'), ' '),
                $finalLength,
                '·'
            );

            $formattedMessage .= $line . ' ';
        }

        return $formattedMessage;
    }
}
