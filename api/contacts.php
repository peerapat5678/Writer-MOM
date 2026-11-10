<?php
/*
 * Address book API
 *   GET    contacts.php?q=                     list (by name)
 *   POST   contacts.php  {name, email, role}   create, or update the contact with the same email
 *   POST   contacts.php  {items: [...]}        bulk create/update (same rule)
 *   PUT    contacts.php?id=1 {name, email, role}
 *   DELETE contacts.php?id=1
 */
require __DIR__ . '/_bootstrap.php';

function clean_contact($c): array
{
    if (!is_array($c)) {
        fail(422, 'bad_data', 'ข้อมูลรายชื่อไม่ถูกต้อง');
    }
    $name = str_limit($c['name'] ?? '', 255);
    $email = mb_strtolower(str_limit($c['email'] ?? '', 255));
    $role = str_limit($c['role'] ?? '', 255);
    if ($name === '') {
        fail(422, 'name_required', 'กรุณาใส่ชื่อ');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail(422, 'bad_email', 'รูปแบบอีเมลไม่ถูกต้อง: ' . $email);
    }
    return ['name' => $name, 'email' => $email === '' ? null : $email, 'role' => $role === '' ? null : $role];
}

function upsert(array $c): int
{
    if ($c['email'] !== null) {
        $st = db()->prepare('SELECT id FROM contacts WHERE email = ?');
        $st->execute([$c['email']]);
        $found = $st->fetchColumn();
        if ($found !== false) {
            db()->prepare('UPDATE contacts SET name = ?, role = COALESCE(?, role) WHERE id = ?')
                ->execute([$c['name'], $c['role'], $found]);
            return (int)$found;
        }
    }
    db()->prepare('INSERT INTO contacts (name, email, role) VALUES (?, ?, ?)')
        ->execute([$c['name'], $c['email'], $c['role']]);
    return (int)db()->lastInsertId();
}

function one(int $id): array
{
    $st = db()->prepare('SELECT id, name, email, role FROM contacts WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        fail(404, 'not_found', 'ไม่พบรายชื่อนี้');
    }
    $r['id'] = (int)$r['id'];
    return $r;
}

$id = query_id();
$m = method();

if ($m === 'GET') {
    $q = str_limit($_GET['q'] ?? '', 200);
    $sql = 'SELECT id, name, email, role FROM contacts';
    $args = [];
    if ($q !== '') {
        $sql .= ' WHERE name LIKE ? OR email LIKE ? OR role LIKE ?';
        $args = [like_escape($q), like_escape($q), like_escape($q)];
    }
    $st = db()->prepare($sql . ' ORDER BY name LIMIT 2000');
    $st->execute($args);
    $items = array_map(function ($r) {
        $r['id'] = (int)$r['id'];
        return $r;
    }, $st->fetchAll());
    respond(['items' => $items]);
}

if ($m === 'POST' && $id === null) {
    $b = body();
    if (isset($b['items'])) {
        if (!is_array($b['items']) || count($b['items']) > 500) {
            fail(422, 'bad_data', 'รายการไม่ถูกต้อง');
        }
        $clean = array_map('clean_contact', $b['items']);
        db()->beginTransaction();
        $ids = array_map('upsert', $clean);
        db()->commit();
        respond(['ids' => $ids], 201);
    }
    respond(one(upsert(clean_contact($b))), 201);
}

if ($m === 'PUT' && $id !== null) {
    $c = clean_contact(body());
    one($id);
    try {
        db()->prepare('UPDATE contacts SET name = ?, email = ?, role = ? WHERE id = ?')
            ->execute([$c['name'], $c['email'], $c['role'], $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            fail(409, 'duplicate_email', 'อีเมลนี้มีอยู่ในสมุดรายชื่อแล้ว');
        }
        throw $e;
    }
    respond(one($id));
}

if ($m === 'DELETE' && $id !== null) {
    $st = db()->prepare('DELETE FROM contacts WHERE id = ?');
    $st->execute([$id]);
    if ($st->rowCount() === 0) {
        fail(404, 'not_found', 'ไม่พบรายชื่อนี้');
    }
    respond(['ok' => true]);
}

fail(405, 'method_not_allowed', 'ไม่รองรับคำสั่งนี้');
