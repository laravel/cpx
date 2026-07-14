<?php

declare(strict_types=1);

namespace Cpx\Support;

use Closure;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use Laravel\Prompts\NumberPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\TextPrompt;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

use function Laravel\Prompts\error;

/** Question-helper implementations for terminals where prompts cannot render, such as native Windows. */
class PromptFallbacks
{
    public static function register(InputInterface $input, OutputInterface $output): void
    {
        Prompt::fallbackWhen(PHP_OS_FAMILY === 'Windows');

        $ask = function (Question $question) use ($input, $output): mixed {
            $input->setInteractive(self::isInteractive($input));

            return (new QuestionHelper)->ask($input, $output, $question);
        };

        TextPrompt::fallbackUsing(fn (TextPrompt $prompt): string => self::untilValid(
            fn (): string => (string) ($ask(new Question($prompt->label, $prompt->default === '' ? null : $prompt->default)) ?? ''),
            $prompt->required,
            $prompt->validate,
            $input,
        ));

        NumberPrompt::fallbackUsing(fn (NumberPrompt $prompt): string => self::untilValid(
            fn (): string => (string) ($ask(new Question($prompt->label, $prompt->default === '' ? null : $prompt->default)) ?? ''),
            $prompt->required,
            $prompt->validate,
            $input,
        ));

        ConfirmPrompt::fallbackUsing(fn (ConfirmPrompt $prompt): bool => self::untilValid(
            fn (): bool => (bool) $ask(new ConfirmationQuestion($prompt->label, $prompt->default)),
            $prompt->required,
            $prompt->validate,
            $input,
        ));

        SelectPrompt::fallbackUsing(fn (SelectPrompt $prompt): int|string => self::untilValid(
            fn (): int|string => $ask(new ChoiceQuestion($prompt->label, $prompt->options, $prompt->default)) ?? '',
            $prompt->required,
            $prompt->validate,
            $input,
        ));
    }

    private static function isInteractive(InputInterface $input): bool
    {
        return match (true) {
            ! $input->isInteractive() => false,
            $input instanceof StreamableInputInterface && $input->getStream() !== null => true,
            default => defined('STDIN') && stream_isatty(STDIN),
        };
    }

    private static function untilValid(Closure $ask, bool|string $required, mixed $validate, InputInterface $input): mixed
    {
        while (true) {
            $result = $ask();

            $error = match (true) {
                $required !== false && in_array($result, ['', [], false, null], true) => is_string($required) && $required !== '' ? $required : 'Required.',
                is_callable($validate) => $validate($result),
                default => null,
            };

            if (! is_string($error) || $error === '') {
                return $result;
            }

            if (! $input->isInteractive()) {
                throw new NonInteractiveValidationException($error);
            }

            error($error);
        }
    }
}
