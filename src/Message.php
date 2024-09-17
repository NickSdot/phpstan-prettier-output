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
use function str_repeat;
use function array_splice;
use function htmlspecialchars;
use function max;
use function str_ends_with;

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

        return $message;
    }

    public static function applyCustomFormattingForIdeOutput(Error $error): string
    {

        $message = $error->getMessage();
        $identifier = $error->getIdentifier();
        $tip = $error->getTip();

        $formattedMessage = '';

        $identifier = null !== $identifier
            ? " ({$identifier})"
            : '';

        $lines = explode("\n", self::applyCustomFormatting($message));

        array_splice($lines, 1, 0, [ '', '' ]);

        if (null !== $tip) {
            $lines[] = ''; // Separator line
            $lines[] = $tip;
            $lines[] = ''; // Separator line
        }

        foreach ($lines as $key => $line) {

            $lineLength = mb_strlen($line);

            if (0 === $key) {
                $lineLength += 9; // PhpStorm adds "phpstan: ", so we need to account for that.

                if (0 < $count = mb_strlen($identifier)) {
                    $lineLength += $count; // We added the identifier, so we need to account for that.
                    $line .= $identifier;
                }
            }

            // todo: 115 chars is the length of the tooltip without scrollbars.
            //       If lines are longer, scrollbars appear. We need to make
            //       sure that we check for the longest line in `$lines` and
            //       then replace the static 115 with `$longest`.

            if ($lineLength < 115) {
                $line .= str_repeat('·', 115 - $lineLength);
            }

            $formattedMessage .= $line . ' '; // We need to have a single space after the added periods to line-break.
        }

        return htmlspecialchars($formattedMessage, \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
    }
}
