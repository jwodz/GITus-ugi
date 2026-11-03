<?php

const PER_PAGE = 10;

function log_action(string $action, string $description): void
{
    $user = current_user();

    $stmt = db()->prepare('INSERT INTO dziennik_operacji (uzytkownik_id, akcja, opis) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'] ?? null, $action, mb_substr($description, 0, 255)]);
}

function get_string(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function get_int(string $key): int
{
    $value = $_GET[$key] ?? '';
    return is_string($value) && ctype_digit($value) ? (int) $value : 0;
}

function post_int(string $key): int
{
    $value = $_POST[$key] ?? '';
    return is_string($value) && ctype_digit($value) ? (int) $value : 0;
}

function get_date(string $key): string
{
    $value = get_string($key);
    return is_valid_date($value) ? $value : '';
}

function is_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function is_valid_time(string $value): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
}

function like_pattern(string $text): string
{
    return '%' . addcslashes($text, '%_\\') . '%';
}

function paginate(int $total, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, get_int('strona')), $pages);

    return [
        'strona' => $page,
        'stron' => $pages,
        'razem' => $total,
        'limit' => $perPage,
        'offset' => ($page - 1) * $perPage,
    ];
}

function sql_limit(array $pagination): string
{
    return ' LIMIT ' . (int) $pagination['limit'] . ' OFFSET ' . (int) $pagination['offset'];
}

function list_url(array $changes = []): string
{
    $query = array_merge($_GET, $changes);
    $query = array_filter($query, fn($value) => is_string($value) || is_int($value));
    $query = array_filter($query, fn($value) => $value !== '' && $value !== 0);

    $page = basename($_SERVER['SCRIPT_NAME']);
    return $page . ($query ? '?' . http_build_query($query) : '');
}

function pagination_html(array $pagination): string
{
    if ($pagination['razem'] === 0) {
        return '';
    }

    $from = $pagination['offset'] + 1;
    $to = min($pagination['offset'] + $pagination['limit'], $pagination['razem']);
    $html = '<nav class="stronicowanie" aria-label="Strony wyników">';
    $html .= '<span class="stronicowanie__info">Wyniki ' . $from . '–' . $to . ' z ' . $pagination['razem'] . '</span>';

    if ($pagination['stron'] > 1) {
        $html .= '<span class="stronicowanie__linki">';
        if ($pagination['strona'] > 1) {
            $html .= '<a href="' . e(list_url(['strona' => $pagination['strona'] - 1])) . '">« Poprzednia</a>';
        }
        for ($i = 1; $i <= $pagination['stron']; $i++) {
            if ($i === $pagination['strona']) {
                $html .= '<span class="aktywna" aria-current="page">' . $i . '</span>';
            } else {
                $html .= '<a href="' . e(list_url(['strona' => $i])) . '">' . $i . '</a>';
            }
        }
        if ($pagination['strona'] < $pagination['stron']) {
            $html .= '<a href="' . e(list_url(['strona' => $pagination['strona'] + 1])) . '">Następna »</a>';
        }
        $html .= '</span>';
    }

    return $html . '</nav>';
}

function plural(int $n, string $one, string $few, string $many): string
{
    if ($n === 1) {
        $word = $one;
    } elseif ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14)) {
        $word = $few;
    } else {
        $word = $many;
    }
    return $n . ' ' . $word;
}

function active_badge(int $active, string $yes = 'Aktywna', string $no = 'Nieaktywna'): string
{
    return $active === 1
        ? '<span class="znacznik znacznik--tak">' . e($yes) . '</span>'
        : '<span class="znacznik znacznik--nie">' . e($no) . '</span>';
}