<?php
/*
 * Meetings API
 *   GET    moms.php?q=&limit=&offset=     list (newest meeting first)
 *   GET    moms.php?id=1                  one MOM with its full data
 *   POST   moms.php          {data}       create            -> {id, version, updated_at}
 *   PUT    moms.php?id=1     {data, version, force?}
 *                                          update; 409 when version is stale unless force
 *   DELETE moms.php?id=1                  soft delete
 *   POST   moms.php?id=1&action=restore   undo a delete
 */
require __DIR__ . '/_bootstrap.php';

const STATUS_DONE = 'เสร็จแล้ว';

/** Validate the MOM document and derive the columns used for listing/search. */
function derive(array $data): array
{
    $title = str_limit($data['title'] ?? '', 255);
    if ($title === '') {
        fail(422, 'title_required', 'กรุณาตั้งชื่อการประชุม');
    }
    if (!isset($data['sections']) || !is_array($data['sections'])) {
        fail(422, 'bad_data', 'ข้อมูล MOM ไม่ครบ');
    }
    $date = $data['date'] ?? null;
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = null;
    }
    $search = [$title];
    $open = 0;
    foreach ($data['sections'] as $s) {
        if (!is_array($s)) {
            continue;
        }
        $items = is_array($s['items'] ?? null) ? $s['items'] : [];
        switch ($s['type'] ?? '') {
            case 'attendees':
                foreach ($items as $a) {
                    $search[] = ($a['name'] ?? '') . ' ' . ($a['email'] ?? '') . ' ' . ($a['role'] ?? '');
                }
                break;
            case 'agenda':
                foreach ($items as $a) {
                    $search[] = $a['topic'] ?? '';
                }
                break;
            case 'actions':
                foreach ($items as $a) {
                    if (trim((string)($a['task'] ?? '')) !== '' && ($a['status'] ?? '') !== STATUS_DONE) {
                        $open++;
                    }
                    $search[] = ($a['task'] ?? '') . ' ' . ($a['owner'] ?? '');
                }
                break;
        }
    }
    unset($data['id']);
    return [
        'title' => $title,
        'meeting_date' => $date,
        'location' => str_limit($data['location'] ?? '', 255) ?: null,
        'recorder' => str_limit($data['recorder'] ?? '', 255) ?: null,
        'open_actions' => min($open, 65535),
        'search_text' => mb_substr(trim(preg_replace('/\s+/u', ' ', implode(' ', array_map('strval', $search)))), 0, 20000),
        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

function meta_row(int $id): array
{
    $st = db()->prepare('SELECT id, version, updated_at FROM moms WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return ['id' => (int)$r['id'], 'version' => (int)$r['version'], 'updated_at' => $r['updated_at']];
}

$id = query_id();
$m = method();

if ($m === 'GET' && $id === null) {
    $q = str_limit($_GET['q'] ?? '', 200);
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $where = 'deleted_at IS NULL';
    $args = [];
    if ($q !== '') {
        $where .= ' AND (title LIKE ? OR search_text LIKE ?)';
        $args[] = like_escape($q);
        $args[] = like_escape($q);
    }
    $st = db()->prepare("SELECT COUNT(*) FROM moms WHERE $where");
    $st->execute($args);
    $total = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT id, title, meeting_date, location, recorder, open_actions, version, updated_at
        FROM moms WHERE $where ORDER BY meeting_date IS NULL, meeting_date DESC, updated_at DESC LIMIT $limit OFFSET $offset");
    $st->execute($args);
    $items = array_map(function ($r) {
        $r['id'] = (int)$r['id'];
        $r['open_actions'] = (int)$r['open_actions'];
        $r['version'] = (int)$r['version'];
        return $r;
    }, $st->fetchAll());
    respond(['items' => $items, 'total' => $total]);
}

if ($m === 'GET') {
    $st = db()->prepare('SELECT id, data, version, created_at, updated_at FROM moms WHERE id = ? AND deleted_at IS NULL');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        fail(404, 'not_found', 'ไม่พบ MOM นี้');
    }
    respond([
        'id' => (int)$r['id'],
        'version' => (int)$r['version'],
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
        'data' => json_decode($r['data'], true),
    ]);
}

if ($m === 'POST' && $id !== null && ($_GET['action'] ?? '') === 'restore') {
    $st = db()->prepare('UPDATE moms SET deleted_at = NULL, version = version + 1 WHERE id = ? AND deleted_at IS NOT NULL');
    $st->execute([$id]);
    if ($st->rowCount() === 0) {
        fail(404, 'not_found', 'ไม่พบ MOM ที่ถูกลบ');
    }
    respond(meta_row($id));
}

if ($m === 'POST' && $id === null) {
    $b = body();
    $d = derive(is_array($b['data'] ?? null) ? $b['data'] : []);
    $st = db()->prepare('INSERT INTO moms (title, meeting_date, location, recorder, open_actions, search_text, data)
        VALUES (:title, :meeting_date, :location, :recorder, :open_actions, :search_text, :data)');
    $st->execute($d);
    respond(meta_row((int)db()->lastInsertId()), 201);
}

if ($m === 'PUT' && $id !== null) {
    $b = body();
    $d = derive(is_array($b['data'] ?? null) ? $b['data'] : []);
    $force = !empty($b['force']);
    $sql = 'UPDATE moms SET title = :title, meeting_date = :meeting_date, location = :location, recorder = :recorder,
        open_actions = :open_actions, search_text = :search_text, data = :data, version = version + 1
        WHERE id = :id AND deleted_at IS NULL';
    $d['id'] = $id;
    if (!$force) {
        $sql .= ' AND version = :version';
        $d['version'] = (int)($b['version'] ?? 0);
    }
    $st = db()->prepare($sql);
    $st->execute($d);
    if ($st->rowCount() === 0) {
        $chk = db()->prepare('SELECT version FROM moms WHERE id = ? AND deleted_at IS NULL');
        $chk->execute([$id]);
        $cur = $chk->fetchColumn();
        if ($cur === false) {
            fail(404, 'not_found', 'ไม่พบ MOM นี้ (อาจถูกลบไปแล้ว)');
        }
        if (!$force) {
            respond(['error' => 'conflict', 'message' => 'มีการแก้ไข MOM นี้จากที่อื่น', 'version' => (int)$cur], 409);
        }
    }
    respond(meta_row($id));
}

if ($m === 'DELETE' && $id !== null) {
    $st = db()->prepare('UPDATE moms SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    $st->execute([$id]);
    if ($st->rowCount() === 0) {
        fail(404, 'not_found', 'ไม่พบ MOM นี้');
    }
    respond(['ok' => true]);
}

fail(405, 'method_not_allowed', 'ไม่รองรับคำสั่งนี้');
