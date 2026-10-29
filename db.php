<?php
// ============================================
// Database connection — PostgreSQL (Render)
//
// Reads Render's DATABASE_URL environment variable, e.g.
//   postgres://user:password@host:5432/dbname
//
// The small "MysqliCompat" classes below let the rest of the site keep using
// the same $conn->query() / prepare() / bind_param() / get_result() calls it
// used with MySQL, so only a few SQL lines needed changing.
// ============================================

class CompatResult {
    public $num_rows = 0;
    private $rows = [];
    private $i = 0;
    public function __construct(array $rows) { $this->rows = $rows; $this->num_rows = count($rows); }
    public function fetch_assoc() { return $this->rows[$this->i++] ?? null; }
    public function fetch_all($mode = null) { return $this->rows; }
}

class CompatStmt {
    private $pdo; private $conn; private $stmt; private $sql; private $params = [];
    public $num_rows = 0;
    public function __construct($pdo, $conn, $sql) { $this->pdo = $pdo; $this->conn = $conn; $this->stmt = $pdo->prepare($sql); $this->sql = $sql; }
    public function bind_param($types, &...$vars) {
        $this->params = [];
        foreach ($vars as $i => $v) {
            $t = $types[$i] ?? 's';
            if ($v === null)      $this->params[] = [null, PDO::PARAM_NULL];
            elseif ($t === 'i')   $this->params[] = [(int)$v, PDO::PARAM_INT];
            else                  $this->params[] = [(string)$v, PDO::PARAM_STR]; // 's' and 'd'
        }
        return true;
    }
    public function execute() {
        foreach ($this->params as $i => $p) $this->stmt->bindValue($i + 1, $p[0], $p[1]);
        $ok = $this->stmt->execute();
        if (preg_match('/^\s*INSERT/i', $this->sql)) $this->conn->capture_insert_id();
        return $ok;
    }
    public function get_result() {
        $rows = $this->stmt->columnCount() > 0 ? $this->stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $this->num_rows = count($rows);
        return new CompatResult($rows);
    }
}

class CompatConn {
    private $pdo;
    public $insert_id = 0;
    public $connect_error = null;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function query($sql) {
        $stmt = $this->pdo->query($sql);
        if (preg_match('/^\s*INSERT/i', $sql)) $this->capture_insert_id();
        if ($stmt && $stmt->columnCount() > 0) return new CompatResult($stmt->fetchAll(PDO::FETCH_ASSOC));
        return true;
    }
    public function prepare($sql) { return new CompatStmt($this->pdo, $this, $sql); }
    public function real_escape_string($s) { return substr($this->pdo->quote((string)$s), 1, -1); }
    public function capture_insert_id() {
        try { $this->insert_id = (int)$this->pdo->query("SELECT lastval()")->fetchColumn(); }
        catch (Throwable $e) { /* table without a serial id (nothing to capture) */ }
    }
}

$url = getenv('DATABASE_URL');
if (!$url) { die("DATABASE_URL is not set. Add it in Render → your web service → Environment."); }

$p = parse_url($url);
$dsn = sprintf(
    "pgsql:host=%s;port=%d;dbname=%s;sslmode=%s",
    $p['host'],
    $p['port'] ?? 5432,
    ltrim($p['path'], '/'),
    getenv('DB_SSLMODE') ?: 'prefer'
);

try {
    $pdo = new PDO($dsn, urldecode($p['user']), urldecode($p['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$conn = new CompatConn($pdo);
