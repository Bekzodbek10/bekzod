<?php
// git.php - Oddiy TODO ilovasi (single file, SQLite)

// --- CONFIG ---
$dbFile = __DIR__ . '/todo.sqlite';

// --- DB init ---
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE IF NOT EXISTS todos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    done INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// --- Helper: CSRF token ---
session_start();
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}
function check_token($t) {
    return isset($_SESSION['token']) && hash_equals($_SESSION['token'], $t);
}

// --- POST actions ---
$action = $_POST['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    if (!check_token($_POST['token'] ?? '')) {
        http_response_code(403);
        echo "CSRF token xatosi.";
        exit;
    }
    if ($action === 'add') {
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title !== '') {
            $stmt = $pdo->prepare("INSERT INTO todos (title) VALUES (:title)");
            $stmt->execute([':title' => $title]);
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE todos SET done = 1 - done WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM todos WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } elseif ($action === 'clear_done') {
        $pdo->exec("DELETE FROM todos WHERE done = 1");
    }
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// --- Fetch todos ---
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM todos ORDER BY created_at DESC";
if ($filter === 'active') $sql = "SELECT * FROM todos WHERE done = 0 ORDER BY created_at DESC";
if ($filter === 'done')   $sql = "SELECT * FROM todos WHERE done = 1 ORDER BY created_at DESC";
$todos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Todo — git.php</title>
<style>
  body{font-family:system-ui;max-width:780px;margin:30px auto;padding:0 16px;color:#111}
  h1{display:flex;align-items:center;gap:.6rem}
  .card{background:#f9f9fb;padding:18px;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
  form.add{display:flex;gap:8px;margin-bottom:12px}
  input[type="text"]{flex:1;padding:10px;border-radius:8px;border:1px solid #ddd}
  button{padding:10px 12px;border-radius:8px;border:0;background:#2563eb;color:#fff;cursor:pointer}
  .todo{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #eee}
  .todo:last-child{border-bottom:0}
  .title.done{text-decoration:line-through;color:#888}
  .small{font-size:.9rem;color:#666}
  .filters a{margin-right:8px;text-decoration:none;color:#2563eb}
  .muted{color:#999}
  .actions button{margin-left:6px;background:#ef4444}
  .actions form{display:inline}
</style>
</head>
<body>
  <h1>✅ Todo — git.php</h1>

  <div class="card">
    <form class="add" method="post">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
      <input type="hidden" name="action" value="add">
      <input type="text" name="title" placeholder="Yangi vazifa..." required maxlength="200">
      <button type="submit">Qoʻshish</button>
    </form>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <div class="filters small">
        Filtr: 
        <a href="?filter=all"<?php if($filter==='all') echo ' style="font-weight:600"'; ?>>Hammasi</a>
        <a href="?filter=active"<?php if($filter==='active') echo ' style="font-weight:600"'; ?>>Faol</a>
        <a href="?filter=done"<?php if($filter==='done') echo ' style="font-weight:600"'; ?>>Bajarilgan</a>
      </div>
      <div class="small muted">Jami: <?php echo count($todos); ?></div>
    </div>

    <?php if (count($todos) === 0): ?>
      <p class="muted">Hozircha vazifa yoʻq ✨</p>
    <?php else: ?>
      <?php foreach ($todos as $t): ?>
        <div class="todo">
          <div>
            <form method="post" style="display:inline">
              <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
              <button type="submit" style="background:transparent;border:0;cursor:pointer;font-size:18px">
                <?php echo $t['done'] ? '✅' : '⬜'; ?>
              </button>
            </form>
            <span class="title <?php echo $t['done'] ? 'done' : ''; ?>"><?php echo htmlspecialchars($t['title']); ?></span>
            <div class="small muted"><?php echo date('Y-m-d H:i', strtotime($t['created_at'])); ?></div>
          </div>
          <div class="actions">
            <form method="post" onsubmit="return confirm('Oʻchirilsinmi?')">
              <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
              <button type="submit">🗑️</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <hr>
    <form method="post" onsubmit="return confirm('Bajarilganlarni tozalaysizmi?')">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
      <input type="hidden" name="action" value="clear_done">
      <button type="submit" style="background:#f59e0b">Bajarilganlarni oʻchirish</button>
    </form>
  </div>
</body>
</html>
