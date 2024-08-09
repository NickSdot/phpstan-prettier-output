<?php

declare(strict_types=1);

namespace NickSdot\PrettierStanOutput;

use PHPStan\Analyser\Error;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\CiDetectedErrorFormatter;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use PHPStan\File\RelativePathHelper;
use Symfony\Component\Console\Formatter\OutputFormatter;

use function array_key_exists;
use function array_map;
use function count;
use function explode;
use function getcwd;
use function in_array;
use function ltrim;
use function mb_strlen;
use function mb_substr;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

// Based on the PHPStan built-in `TableErrorFormatter`; version 1.11.9 from August 2024

/** @api */
final readonly class CustomTableFormatter implements ErrorFormatter
{
    public function __construct(
        private RelativePathHelper $relativePathHelper,
        private CiDetectedErrorFormatter $ciDetectedErrorFormatter,
    ) {}

    /** @api */
    public function formatErrors(
        AnalysisResult $analysisResult,
        Output $output,
    ): int {
        $this->ciDetectedErrorFormatter->formatErrors($analysisResult, $output);
        $projectConfigFile = 'phpstan.neon';
        if (null !== $analysisResult->getProjectConfigFile()) {
            $projectConfigFile = $this->relativePathHelper->getRelativePath($analysisResult->getProjectConfigFile());
        }
        $style = $output->getStyle();
        if (!$analysisResult->hasErrors() && !$analysisResult->hasWarnings()) {
            $style->success('No errors');
            return 0;
        }

        /** @var array<string, Error[]> $fileErrors */
        $fileErrors = [];
        $outputIdentifiers = $output->isVerbose();
        $outputIdentifiersInFile = [];

        foreach ($analysisResult->getFileSpecificErrors() as $fileSpecificError) {
            if (!isset($fileErrors[$fileSpecificError->getFile()])) {
                $fileErrors[$fileSpecificError->getFile()] = [];
            }
            $fileErrors[$fileSpecificError->getFile()][] = $fileSpecificError;
            if ($outputIdentifiers) {
                continue;
            }
            $filePath = $fileSpecificError->getTraitFilePath() ?? $fileSpecificError->getFilePath();
            if (array_key_exists($filePath, $outputIdentifiersInFile)) {
                continue;
            }
            if (null === $fileSpecificError->getIdentifier()) {
                continue;
            }
            if (!in_array($fileSpecificError->getIdentifier(), [
                'ignore.unmatchedIdentifier',
                'ignore.parseError',
                'ignore.unmatched',
            ], true)) {
                continue;
            }
            $outputIdentifiersInFile[$filePath] = true;
        }

        foreach ($fileErrors as $file => $errors) {
            $rows = [];

            foreach ($errors as $error) {

                $message = Message::applyCustomFormatting(
                    $error->getMessage()
                );

                $filePath = $error->getTraitFilePath() ?? $error->getFilePath();
                if (array_key_exists($filePath, $outputIdentifiersInFile) && null !== $error->getIdentifier(
                ) && $error->canBeIgnored()) {
                    $message = "({$error->getIdentifier()}) {$message}";
                }
                if (null !== $error->getTip()) {
                    $tip = $error->getTip();
                    $tip = str_replace('%configurationFile%', $projectConfigFile, $tip);
                    $message .= "\n";
                    if (str_contains($tip, "\n")) {
                        $lines = explode("\n", $tip);
                        foreach ($lines as $line) {
                            $message .= '💡 ' . ltrim($line, ' •') . "\n";
                        }
                    } else {
                        $message .= '💡 ' . $tip;
                    }
                }

                if (true === $output->isVerbose()) {
                    $message = "{$filePath}:{$error->getLine()}\n{$message}";
                }

                $rows[] = [
                    $this->formatLineNumber($error->getLine()),
                    $message,
                ];
            }
            $style->table([ 'Line', $this->relativePathHelper->getRelativePath($file) ], $rows);
        }
        if (count($analysisResult->getNotFileSpecificErrors()) > 0) {
            $style->table(
                [ '', 'Error' ],
                array_map(static fn(string $error): array => [ '', OutputFormatter::escape($error) ], $analysisResult->getNotFileSpecificErrors())
            );
        }
        $warningsCount = count($analysisResult->getWarnings());
        if ($warningsCount > 0) {
            $style->table(
                [ '', 'Warning' ],
                array_map(static fn(string $warning): array => [ '', OutputFormatter::escape($warning) ], $analysisResult->getWarnings())
            );
        }
        $finalMessage = sprintf(
            1 === $analysisResult->getTotalErrorsCount() ? 'Found %d error' : 'Found %d errors',
            $analysisResult->getTotalErrorsCount()
        );
        if ($warningsCount > 0) {
            $finalMessage .= sprintf(1 === $warningsCount ? ' and %d warning' : ' and %d warnings', $warningsCount);
        }
        if ($analysisResult->getTotalErrorsCount() > 0) {
            $style->error($finalMessage);
        } else {
            $style->warning($finalMessage);
        }
        return $analysisResult->getTotalErrorsCount() > 0 ? 1 : 0;
    }

    private function formatLineNumber(?int $lineNumber): string
    {
        if (null === $lineNumber) {
            return '';
        }

        return (string) $lineNumber;
    }

    public function getRelativePath(string $filename): string
    {
        $currentWorkingDirectory = getcwd();

        if (true === str_starts_with($filename, $currentWorkingDirectory)) {
            return str_replace('\\', '/', mb_substr($filename, mb_strlen($currentWorkingDirectory) + 1));
        }
        return str_replace('\\', '/', $filename);
    }
}
