<?php

declare(strict_types=1);

namespace NickSdot\PrettierStanOutput;

use Nette\Utils\Json;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use Symfony\Component\Console\Formatter\OutputFormatter;

use function array_key_exists;
use function count;

// Based on the PHPStan built-in `JsonErrorFormatter`; version 1.11.9 from August 2024

/** @api */
final class CustomJsonFormatter implements ErrorFormatter
{
    /** @api */
    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $errorsArray = [
            'totals' => [
                'errors' => count($analysisResult->getNotFileSpecificErrors()),
                'file_errors' => count($analysisResult->getFileSpecificErrors()),
            ],
            'files' => [],
            'errors' => [],
        ];

        $tipFormatter = new OutputFormatter(false);

        foreach ($analysisResult->getFileSpecificErrors() as $fileSpecificError) {
            $file = $fileSpecificError->getFile();
            if (!array_key_exists($file, $errorsArray['files'])) {
                $errorsArray['files'][$file] = [
                    'errors' => 0,
                    'messages' => [],
                ];
            }
            $errorsArray['files'][$file]['errors']++;

            $message = Message::applyCustomFormatting(
                $fileSpecificError->getMessage()
            );

            $message = [
                'message' => $message,
                'line' => $fileSpecificError->getLine(),
                'ignorable' => $fileSpecificError->canBeIgnored(),
            ];

            if (null !== $fileSpecificError->getTip()) {
                $message['tip'] = $tipFormatter->format($fileSpecificError->getTip());
            }

            if (null !== $fileSpecificError->getIdentifier()) {
                $message['identifier'] = $fileSpecificError->getIdentifier();
            }

            $errorsArray['files'][$file]['messages'][] = $message;
        }

        foreach ($analysisResult->getNotFileSpecificErrors() as $notFileSpecificError) {
            $errorsArray['errors'][] = $notFileSpecificError;
        }

        $json = Json::encode($errorsArray);

        $output->writeRaw($json);

        return $analysisResult->hasErrors() ? 1 : 0;
    }
}
