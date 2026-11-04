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

function validate_visit(PDO $pdo, array $data): array
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

    $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = ? AND is_active = 1');
    $stmt->execute([(int) $data['department_id']]);
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

    if ($data['card'] === '') {
        $errors['card'] = 'Choose a card to issue, or choose "No card".';
    } elseif ($data['card'] !== 'none' && !ctype_digit($data['card'])) {
        $errors['card'] = 'Choose a card from the list.';
    }

    return $errors;
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

            // Keep the latest company name on the visitor record
            $stmt = $pdo->prepare('UPDATE visitors SET organization = ? WHERE id = ?');
            $stmt->execute([$data['organization'] ?: null, $visitorId]);
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
