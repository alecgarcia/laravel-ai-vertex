<?php

namespace AlecGarcia\AiVertex\Gateway\GenerateContent\Concerns;

use InvalidArgumentException;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;

/**
 * Adapted from laravel/ai v0.11.3 (MIT), see NOTICE.md.
 */
trait MapsGenerateContentMessages
{
    /**
     * Interactions steps carry one of these types, generateContent parts never do.
     *
     * @var array<int, string>
     */
    private const INTERACTIONS_STEP_TYPES = ['user_input', 'model_output', 'thought', 'function_call', 'function_result'];

    /**
     * Map the given messages to generateContent contents.
     *
     * @param  array<int, mixed>  $messages
     * @return array<int, array<string, mixed>>
     */
    protected function mapMessagesToContents(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            $message = Message::tryFrom($message);

            match ($message->role) {
                MessageRole::User => $this->mapUserMessage($message, $contents),
                MessageRole::Assistant => $this->mapAssistantMessage($message, $contents),
                MessageRole::ToolResult => $this->mapToolResultMessage($message, $contents),
            };
        }

        return $contents;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contents
     */
    protected function mapUserMessage(UserMessage|Message $message, array &$contents): void
    {
        if ($message instanceof UserMessage && $message->attachments->isNotEmpty()) {
            throw new InvalidArgumentException('Attachments are not supported on the Vertex generateContent text API yet.');
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $message->content]]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $contents
     */
    protected function mapAssistantMessage(AssistantMessage|Message $message, array &$contents): void
    {
        // Gemini wants its own parts, thought signatures included, sent back as it returned them...
        if ($message instanceof AssistantMessage && $this->canReplay($message->replayBlocks)) {
            $contents[] = ['role' => 'model', 'parts' => $message->replayBlocks];

            return;
        }

        $parts = [];

        if (filled($message->content)) {
            $parts[] = ['text' => $message->content];
        }

        if ($message instanceof AssistantMessage) {
            foreach ($message->toolCalls as $toolCall) {
                $functionCall = ['name' => $toolCall->name];

                if (filled($toolCall->arguments)) {
                    $functionCall['args'] = $toolCall->arguments;
                }

                $parts[] = ['functionCall' => $functionCall];
            }
        }

        if (filled($parts)) {
            $contents[] = ['role' => 'model', 'parts' => $parts];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $contents
     */
    protected function mapToolResultMessage(ToolResultMessage|Message $message, array &$contents): void
    {
        if (! $message instanceof ToolResultMessage) {
            return;
        }

        $parts = $this->buildFunctionResponseParts($message->toolResults->all());

        if (filled($parts)) {
            $contents[] = ['role' => 'user', 'parts' => $parts];
        }
    }

    /**
     * Replay blocks written by the Interactions API have another shape and must not be sent here.
     *
     * @param  array<array-key, mixed>  $blocks
     */
    protected function canReplay(array $blocks): bool
    {
        if (! filled($blocks)) {
            return false;
        }

        foreach ($blocks as $block) {
            if (! is_array($block) || in_array($block['type'] ?? null, self::INTERACTIONS_STEP_TYPES, true)) {
                return false;
            }
        }

        return true;
    }
}
