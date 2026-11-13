<?php

declare(strict_types=1);

namespace Initred\Langfuse\Dto;

/**
 * What a v3 score is attached to. `traceId` is only present for observation
 * subjects, because observation ids are scoped to a trace.
 */
readonly class ScoreSubject
{
    public const string KIND_TRACE = 'trace';

    public const string KIND_OBSERVATION = 'observation';

    public const string KIND_SESSION = 'session';

    public const string KIND_EXPERIMENT = 'experiment';

    public function __construct(
        public string $kind,
        public string $id,
        public ?string $traceId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            kind: is_string($data['kind'] ?? null) ? $data['kind'] : '',
            id: is_string($data['id'] ?? null) ? $data['id'] : '',
            traceId: is_string($data['traceId'] ?? null) ? $data['traceId'] : null,
        );
    }
}
