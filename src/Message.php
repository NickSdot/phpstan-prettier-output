<?php

declare(strict_types=1);

namespace NickSdot\PrettierStanOutput;

use PHPStan\Analyser\Error;

use function array_shift;
use function count;
use function mb_substr_count;
use function preg_match;
use function preg_quote;
use function str_replace;
use function vsprintf;
use function explode;
use function mb_strlen;
use function str_repeat;
use function array_splice;
use function htmlspecialchars;

final readonly class Message
{
    private const array PATTERN = [
        'Method %s::%s() should return %%s but returns %%s.' => 'Method %s::%s() return type mismatch' . PHP_EOL . '⁉️ %s' . PHP_EOL . '⁉️ %s' . PHP_EOL,
        'PHPDoc tag @return with type %s is incompatible with native type %s.' => 'PHPDoc tag @return is incompatible' . PHP_EOL . '⁉️ %s' . PHP_EOL . '⁉️ native type %s' . PHP_EOL,
        'Parameter %%s %s of method %s expects %s, %s given.' => 'Parameter %s %s of method %s does not match' . PHP_EOL . '✅️ %s (expected)' . PHP_EOL . '❌️ %s (current)' . PHP_EOL,
    ];

    public static function applyCustomFormatting(string $message): string
    {
        foreach (self::PATTERN as $oldPattern => $newPattern) {

            $regex = '/' . str_replace([ '%%s', '%s' ], "([^']+?)", preg_quote($oldPattern, '/')) . '/';

            if (false !== preg_match($regex, $message, $matches)) {

                array_shift($matches); // Remove the full match from the matches array

                if (mb_substr_count($newPattern, '%s') === count($matches)) {
                    $message = vsprintf($newPattern, $matches);
                    break;
                }
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
