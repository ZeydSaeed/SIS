<?php

namespace App\Application\Results\Results;

/**
 * Per-student outcome of issuing the transcripts of a class: `issued`, `replayed`, `already_issued` (the current
 * transcript was issued after the current official GPA — nothing new to issue) or an error code of IssueTranscript.
 */
final readonly class IssueTranscriptsBatchResult extends ProcessTermResultsBatchResult
{
    protected const OK = ['issued', 'replayed', 'already_issued'];
}
