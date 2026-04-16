<?php

declare(strict_types=1);

function score_from_text(string $status, string $transcript, string $summary): string
{
    if ($status !== 'Completed') {
        return 'New';
    }

    $body = strtolower($transcript . ' ' . $summary);
    $positive = ['demo', 'budget', 'interested', 'decision maker', 'timeline', 'follow-up'];
    $medium = ['maybe', 'later', 'send details', 'call back', 'considering'];

    $positiveCount = 0;
    foreach ($positive as $needle) {
        if (str_contains($body, $needle)) {
            $positiveCount++;
        }
    }

    $mediumCount = 0;
    foreach ($medium as $needle) {
        if (str_contains($body, $needle)) {
            $mediumCount++;
        }
    }

    if ($positiveCount >= 2) {
        return 'Hot';
    }
    if ($positiveCount >= 1 || $mediumCount >= 2) {
        return 'Warm';
    }

    return 'Cold';
}

function simulate_call(array $lead, array $config, int $attempt): array
{
    $outcomes = [
        ['Connected', 'Prospect said they are interested and asked for a demo next week. They have budget and timeline this month.', 'Positive call. Prospect requested a demo and has active buying intent.', 188],
        ['No Answer', 'No one picked up the call.', 'No response from prospect.', 0],
        ['Connected', 'Prospect asked to send details and requested follow-up in two weeks after internal discussion.', 'Moderate interest. Follow-up required.', 124],
        ['Connected', 'Prospect not interested currently and prefers current vendor.', 'Low intent. Not a near-term opportunity.', 95]
    ];

    $pick = $outcomes[random_int(0, count($outcomes) - 1)];
    $flow = implode(' ', $config['question_flow']);
    $transcript = sprintf("[%s | %s] %s\nLead %s: %s\nQuestions asked: %s\nAgent closing: %s",
        $config['language_style'],
        $config['tone'],
        $config['opening_script'],
        $lead['name'],
        $pick[1],
        $flow,
        $config['closing_statement']
    );

    $finalStatus = ($pick[0] === 'No Answer' && $attempt < 2) ? 'No Answer' : 'Completed';
    $summary = $pick[0] === 'No Answer' ? 'Lead did not answer. Retry recommended if attempts remaining.' : $pick[2];

    return [
        'status' => $finalStatus,
        'duration' => $pick[3],
        'transcript' => $transcript,
        'summary' => $summary,
        'leadScore' => score_from_text($finalStatus, $transcript, $summary)
    ];
}
