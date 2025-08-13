<?php
// init_db.php
$dbfile = __DIR__ . '/data.db';
if (file_exists($dbfile)) {
    echo "Database already exists at $dbfile\n";
    exit;
}
$db = new PDO('sqlite:' . $dbfile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// todos: main todo list
$db->exec("
CREATE TABLE todos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    tags TEXT DEFAULT 'Work,Event,Life achievement',
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft', -- draft, in_progress, complete
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
");

// todo_items: line items under a todo
$db->exec("
CREATE TABLE todo_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    todo_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    done INTEGER DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(todo_id) REFERENCES todos(id) ON DELETE CASCADE
);
");

// participants (res.users)
$db->exec("
CREATE TABLE participants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    todo_id INTEGER NOT NULL,
    user_name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(todo_id) REFERENCES todos(id) ON DELETE CASCADE
);
");

// convenience: small seed
$stmt = $db->prepare("INSERT INTO todos (name, tags, start_date, end_date, status) VALUES (:n, :t, :s, :e, :st)");
$stmt->execute([
    ':n' => 'ตัวอย่าง Todo 1',
    ':t' => 'Work,Event',
    ':s' => date('Y-m-d'),
    ':e' => date('Y-m-d', strtotime('+3 days')),
    ':st' => 'draft'
]);
$todoId = $db->lastInsertId();
$db->prepare("INSERT INTO todo_items (todo_id, name, description, done) VALUES (?,?,?,?)")
   ->execute([$todoId, 'รายการย่อยตัวอย่าง 1', 'คำอธิบาย', 0]);
$db->prepare("INSERT INTO participants (todo_id, user_name) VALUES (?,?)")
   ->execute([$todoId, 'user_a']);

echo "Database created at $dbfile\n";
