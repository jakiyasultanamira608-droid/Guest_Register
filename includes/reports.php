<?php
// Report helpers (FR-11).

// The longest period one report may cover
const REPORT_MAX_DAYS = 366;

// A date from the URL in YYYY-MM-DD form, or '' if missing or invalid
function date_param(string $key): string
{
    $value = get($key);
    $date  = DateTime::createFromFormat('!Y-m-d', $value);

    return ($date && $date->format('Y-m-d') === $value) ? $value : '';
}

// A clean top value and step for a chart axis, e.g. max 13 -> [15, 5].
// Three to five gridlines, with steps of 1, 2 or 5 times a power of ten.
function nice_axis(int $max): array
{
    if ($max <= 4) {
        return [4, 1];
    }

    $raw       = $max / 4;
    $magnitude = 10 ** floor(log10($raw));
    $fraction  = $raw / $magnitude;

    $step = (int) (($fraction <= 1 ? 1 : ($fraction <= 2 ? 2 : ($fraction <= 5 ? 5 : 10))) * $magnitude);

    return [(int) (ceil($max / $step) * $step), $step];
}

// Every date from $from to $to inclusive, as YYYY-MM-DD strings
function report_days(string $from, string $to): array
{
    $days = [];
    for ($day = strtotime($from); $day <= strtotime($to); $day = strtotime('+1 day', $day)) {
        $days[] = date('Y-m-d', $day);
    }

    return $days;
}
