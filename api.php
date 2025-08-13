<?php
header('Content-Type: application/json; charset=utf-8');
$db = new PDO('sqlite:' . __DIR__ . '/data.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_REQUEST['action'] ?? '';

function json($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'list') {
    $filter = $_GET['filter'] ?? 'all';
    if ($filter === 'complete') {
        $stmt = $db->query("SELECT * FROM todos WHERE status='complete' ORDER BY created_at DESC");
    } elseif ($filter === 'uncomplete') {
        $stmt = $db->query("SELECT * FROM todos WHERE status!='complete' ORDER BY created_at DESC");
    } else {
        $stmt = $db->query("SELECT * FROM todos ORDER BY created_at DESC");
    }
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($todos as &$t) {
        $t['items'] = $db->prepare("SELECT * FROM todo_items WHERE todo_id=?")->executeAndFetchAll([$t['id']]);
    }
    json($todos);
}

PDO::class;

function fetchAllQuery($db, $sql, $params = []) {
    $s = $db->prepare($sql);
    $s->execute($params);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function fetchOne($db, $sql, $params = []) {
    $s = $db->prepare($sql);
    $s->execute($params);
    return $s->fetch(PDO::FETCH_ASSOC);
}

switch($action) {
    case 'list':
        break;
    case 'get_todos':
        $filter = $_GET['filter'] ?? 'all';
        if ($filter === 'complete') $todos = fetchAllQuery($db, "SELECT * FROM todos WHERE status='complete' ORDER BY created_at DESC");
        elseif ($filter === 'uncomplete') $todos = fetchAllQuery($db, "SELECT * FROM todos WHERE status!='complete' ORDER BY created_at DESC");
        else $todos = fetchAllQuery($db, "SELECT * FROM todos ORDER BY created_at DESC");
        foreach ($todos as &$t) {
            $t['items'] = fetchAllQuery($db, "SELECT * FROM todo_items WHERE todo_id=? ORDER BY id", [$t['id']]);
            $t['participants'] = fetchAllQuery($db, "SELECT * FROM participants WHERE todo_id=? ORDER BY id", [$t['id']]);
        }
        json(['ok'=>true,'todos'=>$todos]);
        break;

    case 'create_todo':
        $name = trim($_POST['name'] ?? '');
        $tags = trim($_POST['tags'] ?? 'Work,Event,Life achievement');
        
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';

        if ($name === '') json(['ok'=>false,'error'=>'ต้องระบุชื่อ Todo list']);
        if ($start === '' || $end === '') json(['ok'=>false,'error'=>'ต้องระบุวันเริ่มต้นและวันสิ้นสุด']);
        $sd = DateTime::createFromFormat('Y-m-d',$start);
        $ed = DateTime::createFromFormat('Y-m-d',$end);
        if (!$sd || !$ed) json(['ok'=>false,'error'=>'รูปแบบวันที่ไม่ถูกต้อง (YYYY-MM-DD)']);
        if ($ed <= $sd) json(['ok'=>false,'error'=>'วันสิ้นสุดต้องมากกว่าวันเริ่มต้น']);

        $stmt = $db->prepare("INSERT INTO todos (name,tags,start_date,end_date,status,created_at) VALUES (?,?,?,?,?,datetime('now'))");
$stmt->execute([$name,$tags,$start,$end,'draft']);

        json(['ok'=>true,'id'=>$db->lastInsertId()]);
        
        break;

    case 'update_todo':
        $id = intval($_POST['id'] ?? 0);
        $todo = fetchOne($db, "SELECT * FROM todos WHERE id=?", [$id]);
        if (!$todo) json(['ok'=>false,'error'=>'ไม่พบ Todo']);
        if ($todo['status'] === 'complete') json(['ok'=>false,'error'=>'ไม่สามารถแก้ไข Todo ที่สถานะ complete ได้']);

        $name = trim($_POST['name'] ?? $todo['name']);
        $tags = trim($_POST['tags'] ?? $todo['tags']);
        $start = $_POST['start_date'] ?? $todo['start_date'];
        $end = $_POST['end_date'] ?? $todo['end_date'];

        $sd = DateTime::createFromFormat('Y-m-d',$start);
        $ed = DateTime::createFromFormat('Y-m-d',$end);
        if (!$sd || !$ed) json(['ok'=>false,'error'=>'รูปแบบวันที่ไม่ถูกต้อง (YYYY-MM-DD)']);
        if ($ed <= $sd) json(['ok'=>false,'error'=>'วันสิ้นสุดต้องมากกว่าวันเริ่มต้น']);

        $stmt = $db->prepare("UPDATE todos SET name=?, tags=?, start_date=?, end_date=? WHERE id=?");
        $stmt->execute([$name,$tags,$start,$end,$id]);
        json(['ok'=>true]);
        break;

    case 'change_status':
        $id = intval($_POST['id'] ?? 0);
        $target = $_POST['target'] ?? '';
        $todo = fetchOne($db, "SELECT * FROM todos WHERE id=?", [$id]);
        if (!$todo) json(['ok'=>false,'error'=>'ไม่พบ Todo']);
        if ($todo['status'] === 'complete') json(['ok'=>false,'error'=>'Todo เป็นสถานะ complete แล้ว']);

        if ($target === 'in_progress') {
            if ($todo['status'] !== 'draft') json(['ok'=>false,'error'=>'สามารถเปลี่ยนเป็น in_progress ได้จาก draft เท่านั้น']);
            $db->prepare("UPDATE todos SET status='in_progress' WHERE id=?")->execute([$id]);
            json(['ok'=>true]);
        } elseif ($target === 'complete') {
            $undone = fetchAllQuery($db, "SELECT * FROM todo_items WHERE todo_id=? AND done=0", [$id]);
            if (count($undone) > 0) json(['ok'=>false,'error'=>'ยังมีรายการย่อยที่ยังไม่เสร็จ']);
            $db->prepare("UPDATE todos SET status='complete' WHERE id=?")->execute([$id]);
            json(['ok'=>true]);
        } else {
            json(['ok'=>false,'error'=>'target ไม่ถูกต้อง']);
        }
        break;

    case 'add_item':
        $todo_id = intval($_POST['todo_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') json(['ok'=>false,'error'=>'ต้องระบุชื่่อรายการย่อย']);
        $todo = fetchOne($db, "SELECT * FROM todos WHERE id=?", [$todo_id]);
        if (!$todo) json(['ok'=>false,'error'=>'ไม่พบ Todo']);
        if ($todo['status'] === 'complete') json(['ok'=>false,'error'=>'ไม่สามารถเพิ่มรายการใน Todo ที่สถานะ complete ได้']);

        $stmt = $db->prepare("INSERT INTO todo_items (todo_id, name, description, done) VALUES (?,?,?,0)");
        $stmt->execute([$todo_id, $name, $desc]);
        json(['ok'=>true,'id'=>$db->lastInsertId()]);
        break;

    case 'toggle_item_done':
        $id = intval($_POST['id'] ?? 0);
        $item = fetchOne($db, "SELECT * FROM todo_items WHERE id=?", [$id]);
        if (!$item) json(['ok'=>false,'error'=>'ไม่พบรายการย่อย']);
        $todo = fetchOne($db, "SELECT * FROM todos WHERE id=?", [$item['todo_id']]);
        if (!$todo) json(['ok'=>false,'error'=>'ไม่พบ Todo']);
        if ($todo['status'] !== 'in_progress') json(['ok'=>false,'error'=>'checkbox ปรากฏเฉพาะเมื่อสถานะเป็น in_progress เท่านั้น']);
        $new = $item['done'] ? 0 : 1;
        $db->prepare("UPDATE todo_items SET done=? WHERE id=?")->execute([$new, $id]);
        json(['ok'=>true,'done'=>$new]);
        break;

    case 'add_participant':
        $todo_id = intval($_POST['todo_id'] ?? 0);
        $name = trim($_POST['user_name'] ?? '');
        if ($name === '') json(['ok'=>false,'error'=>'ต้องระบุชื่อผู้ใช้งาน']);
        $todo = fetchOne($db, "SELECT * FROM todos WHERE id=?", [$todo_id]);
        if (!$todo) json(['ok'=>false,'error'=>'ไม่พบ Todo']);
        if ($todo['status'] === 'complete') json(['ok'=>false,'error'=>'ไม่สามารถเพิ่มผู้เข้าร่วมเมื่อ Todo เป็น complete']);
        $db->prepare("INSERT INTO participants (todo_id, user_name) VALUES (?,?)")->execute([$todo_id, $name]);
        json(['ok'=>true,'id'=>$db->lastInsertId()]);
        break;

    default:
        json(['ok'=>false,'error'=>'action ไม่ถูกต้อง']);

}
