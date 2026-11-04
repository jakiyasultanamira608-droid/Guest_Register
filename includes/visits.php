<?php
// Visit rules: registration, check-in and card issue (FR-03, FR-04, FR-05).

const VISIT_STATUSES = [
    'active'    => 'Checked in',
    'completed' => 'Checked out',
];

function visit_status_label(string $status): string
{
    return VISIT_STATUSES[$status] ?? $status;
}

// A business rule stopped the registration, e.g. the card was just taken.
// $field is the form field the message belongs to.
class VisitError extends Exception
{
    public string $field;

    public function __construct(string $field, string $message)
    {
        parent::__construct($message);
        $this->field = $field;
    }
}

// Phone numbers are stored without spaces or hyphens, e.g. "+8801712345678",
// so the same number typed differently still matches a returning visitor.
function normalize_phone(string $phone): string
{
    return preg_replace('/[\s-]+/', '', $phone);
}

function visit_form_data(): array
{
    return [
        'full_name'      => post('full_name'),
        'organization'   => post('organization'),
        'contact_number' => normalize_phone(post('contact_number')),
        'department_id'  => post('department_id'),
        'host_name'      => post('host_name'),
        'purpose'        => post('purpose'),
        'notes'          => post('notes'),
        'card'           => post('card'),   // a card id, 'none', or '' if not chosen
    ];
}

// Registration form: visitor and visit details plus the card choice.
function validate_visit(PDO $pdo, array $data): array
{
    $errors = validate_visit_details($pdo, $data);

    if ($data['card'] === '') {
        $errors['card'] = 'Choose a card to issue, or choose "No card".';
    } elseif ($data['card'] !== 'none' && !ctype_digit($data['card'])) {
        $errors['card'] = 'Choose a card from the list.';
    }

    return $errors;
}

// Visitor and visit details, shared by registration and the edit form (FR-09).
function validate_visit_details(PDO $pdo, array $data): array
{
    $errors = [];

    if ($data['full_name'] === '') {
        $errors['full_name'] = 'Enter the visitor\'s full name.';
    } elseif (mb_strlen($data['full_name']) > 150) {
        $errors['full_name'] = 'Name must be 150 characters or fewer.';
    }

    if (mb_strlen($data['organization']) > 150) {
        $errors['organization'] = 'Company must be 150 characters or fewer.';
    }

    if ($data['contact_number'] !== '' && !preg_match('/^\+?[0-9]{6,15}$/', $data['contact_number'])) {
        $errors['contact_number'] = 'Enter a valid phone number, like 01712345678 or +8801712345678.';
    }

    // A department that was later deactivated stays valid for visits that already use it
    $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = ? AND (is_active = 1 OR id = ?)');
    $stmt->execute([(int) $data['department_id'], (int) ($data['current_department_id'] ?? 0)]);
    if (!$stmt->fetch()) {
        $errors['department_id'] = 'Choose the department being visited.';
    }

    if (mb_strlen($data['host_name']) > 100) {
        $errors['host_name'] = 'Host name must be 100 characters or fewer.';
    }

    if ($data['purpose'] === '') {
        $errors['purpose'] = 'Enter the purpose of the visit.';
    } elseif (mb_strlen($data['purpose']) > 255) {
        $errors['purpose'] = 'Purpose must be 255 characters or fewer.';
    }

    if (mb_strlen($data['notes']) > 500) {
        $errors['notes'] = 'Notes must be 500 characters or fewer.';
    }

    return $errors;
}

// "45 min", "2 h 15 min", "3 d 4 h"
function format_duration(int $seconds): string
{
    $minutes = max(0, intdiv($seconds, 60));

    if ($minutes < 60) {
        return $minutes . ' min';
    }

    $hours = intdiv($minutes, 60);
    if ($hours < 24) {
        return $hours . ' h' . ($minutes % 60 ? ' ' . ($minutes % 60) . ' min' : '');
    }

    return intdiv($hours, 24) . ' d' . ($hours % 24 ? ' ' . ($hours % 24) . ' h' : '');
}

// Next reference for today, e.g. GR-20261104-0007.
function next_visit_reference(PDO $pdo, string $checkInAt): string
{
    $prefix = 'GR-' . date('Ymd', strtotime($checkInAt)) . '-';

    $stmt = $pdo->prepare('SELECT MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) FROM visits WHERE reference LIKE ?');
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);

    return $prefix . str_pad((string) ((int) $stmt->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
}

// Register the visitor, check them in and issue the card, all in one
// transaction: either everything is saved or nothing is.
// Returns the new visit id. Throws VisitError when a rule blocks it.
function register_visit(PDO $pdo, array $data, int $staffId): int
{
    $checkInAt = date('Y-m-d H:i:s');   // system time, Asia/Dhaka

    $pdo->beginTransaction();

    try {
        // 1. Returning visitor? Match on name + phone number
        $visitorId = null;
        if ($data['contact_number'] !== '') {
            $stmt = $pdo->prepare('SELECT id FROM visitors WHERE full_name = ? AND contact_number = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$data['full_name'], $data['contact_number']]);
            $visitorId = $stmt->fetchColumn() ?: null;
        }

        if ($visitorId) {
            // FR-04: the same visitor cannot be checked in twice
            $stmt = $pdo->prepare("SELECT reference FROM visits WHERE visitor_id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$visitorId]);
            $activeReference = $stmt->fetchColumn();

            if ($activeReference) {
                throw new VisitError('full_name', 'This visitor is already checked in (' . $activeReference . '). Check them out first.');
            }

            // Keep the latest company name, but never erase it when left blank
            if ($data['organization'] !== '') {
                $stmt = $pdo->prepare('UPDATE visitors SET organization = ? WHERE id = ?');
                $stmt->execute([$data['organization'], $visitorId]);
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO visitors (full_name, organization, contact_number) VALUES (?, ?, ?)');
            $stmt->execute([$data['full_name'], $data['organization'] ?: null, $data['contact_number'] ?: null]);
            $visitorId = (int) $pdo->lastInsertId();
        }

        // 2. Lock the chosen card so nobody else can issue it at the same moment
        $card = null;
        if ($data['card'] !== 'none') {
            $stmt = $pdo->prepare('SELECT id, card_number, status FROM cards WHERE id = ? FOR UPDATE');
            $stmt->execute([(int) $data['card']]);
            $card = $stmt->fetch();

            if (!$card || $card['status'] !== 'available') {
                throw new VisitError('card', 'That card is no longer available. Please choose another card.');
            }
        }

        // 3. Create the visit. If two visits get the same reference at the
        //    same moment, the UNIQUE key rejects one and we try the next number.
        $visitId = 0;
        for ($attempt = 0; $attempt < 5 && !$visitId; $attempt++) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO visits (reference, visitor_id, department_id, host_name, purpose, check_in_at, status, registered_by, notes)
                     VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?)"
                );
                $stmt->execute([
                    next_visit_reference($pdo, $checkInAt),
                    $visitorId,
                    (int) $data['department_id'],
                    $data['host_name'] ?: null,
                    $data['purpose'],
                    $checkInAt,
                    $staffId,
                    $data['notes'] ?: null,
                ]);
                $visitId = (int) $pdo->lastInsertId();
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
            }
        }

        if (!$visitId) {
            throw new RuntimeException('Could not create a unique visit reference.');
        }

        // 4. Issue the card and record it in the card history
        if ($card) {
            $stmt = $pdo->prepare("UPDATE cards SET status = 'issued', current_visit_id = ?, updated_by = ? WHERE id = ? AND status = 'available'");
            $stmt->execute([$visitId, $staffId, $card['id']]);

            if ($stmt->rowCount() !== 1) {
                throw new VisitError('card', 'That card is no longer available. Please choose another card.');
            }

            $stmt = $pdo->prepare('INSERT INTO card_assignments (visit_id, card_id, issued_at, issued_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$visitId, $card['id'], $checkInAt, $staffId]);
        }

        $pdo->commit();

        return $visitId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Correct the visitor and visit details of an existing visit (FR-09).
// Check-in/check-out times and the card are never changed here.
// Visitor details belong to the visitor, so they change on all their visits.
function update_visit_details(PDO $pdo, int $visitId, int $visitorId, array $data, int $staffId): void
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('UPDATE visitors SET full_name = ?, organization = ?, contact_number = ? WHERE id = ?');
        $stmt->execute([$data['full_name'], $data['organization'] ?: null, $data['contact_number'] ?: null, $visitorId]);

        $stmt = $pdo->prepare(
            'UPDATE visits SET department_id = ?, host_name = ?, purpose = ?, notes = ?, edited_at = ?, edited_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            (int) $data['department_id'],
            $data['host_name'] ?: null,
            $data['purpose'],
            $data['notes'] ?: null,
            date('Y-m-d H:i:s'),
            $staffId,
            $visitId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Check a visitor out and record what happened to their card (FR-06).
// $cardOutcome is 'returned' or 'lost' (ignored when no card was issued).
// Everything is saved in one transaction. Throws VisitError when a rule blocks it.
function check_out_visit(PDO $pdo, int $visitId, string $cardOutcome, string $note, int $staffId): void
{
    $now = date('Y-m-d H:i:s');

    $pdo->beginTransaction();

    try {
        // 1. Lock the visit so two staff cannot check out the same visitor at once
        $stmt = $pdo->prepare('SELECT status, check_in_at FROM visits WHERE id = ? FOR UPDATE');
        $stmt->execute([$visitId]);
        $visit = $stmt->fetch();

        if (!$visit || $visit['status'] !== 'active') {
            throw new VisitError('visit', 'This visitor has already been checked out.');
        }

        // Business rule 5: check-out can never be earlier than check-in
        $checkOutAt = max($now, $visit['check_in_at']);

        $stmt = $pdo->prepare("UPDATE visits SET status = 'completed', check_out_at = ?, checked_out_by = ? WHERE id = ?");
        $stmt->execute([$checkOutAt, $staffId, $visitId]);

        // 2. Close the card assignment, if a card was issued
        $stmt = $pdo->prepare('SELECT id, card_id FROM card_assignments WHERE visit_id = ? AND returned_at IS NULL FOR UPDATE');
        $stmt->execute([$visitId]);
        $assignment = $stmt->fetch();

        if ($assignment) {
            if (!in_array($cardOutcome, ['returned', 'lost'], true)) {
                throw new VisitError('card_outcome', 'Choose whether the card was returned.');
            }

            $stmt = $pdo->prepare('UPDATE card_assignments SET returned_at = ?, return_status = ?, received_by = ?, note = ? WHERE id = ?');
            $stmt->execute([$checkOutAt, $cardOutcome, $staffId, $note ?: null, $assignment['id']]);

            // Returned cards can be issued again; unreturned cards are flagged
            // as lost and cannot be issued until an administrator resolves them
            $cardStatus = $cardOutcome === 'returned' ? 'available' : 'lost';

            $stmt = $pdo->prepare('UPDATE cards SET status = ?, current_visit_id = NULL, updated_by = ? WHERE id = ? AND current_visit_id = ?');
            $stmt->execute([$cardStatus, $staffId, $assignment['card_id'], $visitId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Card record does not match this visit.');
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
