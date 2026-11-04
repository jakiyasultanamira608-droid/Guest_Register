<?php
// Visitor card rules shared by the card pages (FR-05, FR-06, FR-10).

const CARD_STATUSES = [
    'available' => 'Available',
    'issued'    => 'Issued',
    'lost'      => 'Lost',
    'inactive'  => 'Inactive',
];

// Statuses an administrator may set by hand. 'issued' is only ever set by
// checking a visitor in, and only check-out can clear it.
const CARD_MANUAL_STATUSES = [
    'available' => 'Available - can be issued to visitors',
    'lost'      => 'Lost - missing, cannot be issued',
    'inactive'  => 'Inactive - damaged or retired, cannot be issued',
];

function card_status_label(string $status): string
{
    return CARD_STATUSES[$status] ?? $status;
}

// Card numbers are stored in capitals without surrounding spaces, e.g. "V-011".
function normalize_card_number(string $number): string
{
    return mb_strtoupper(trim($number));
}

// Returns an error message, or '' if the card number is valid.
function validate_card_number(PDO $pdo, string $number, ?int $cardId): string
{
    if ($number === '') {
        return 'Enter the number printed on the card.';
    }

    if (mb_strlen($number) > 20) {
        return 'Card number must be 20 characters or fewer.';
    }

    if (!preg_match('/^[A-Z0-9-]+$/', $number)) {
        return 'Use only letters, numbers and hyphens, like V-011.';
    }

    $stmt = $pdo->prepare('SELECT id FROM cards WHERE card_number = ? AND id <> ?');
    $stmt->execute([$number, $cardId ?? 0]);

    return $stmt->fetch() ? 'A card with this number already exists.' : '';
}
