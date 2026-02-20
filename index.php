<?php
/**
  Capstone Todo App - PHP/Nginx/RDS with Tailwind CSS
 */
mysqli_report(MYSQLI_REPORT_OFF);

// 1. Get DB Credentials (Use $_SERVER for Nginx FastCGI params)
$db_host = $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
$db_user = $_SERVER['DB_USER'] ?? getenv('DB_USER');
$db_pass = $_SERVER['DB_PASS'] ?? getenv('DB_PASS');
$db_name = "capstone_db";

// 2. Get AWS Metadata
$instance_id = $_SERVER['AWS_INSTANCE_ID'] ?? "Unknown Instance";
$az          = $_SERVER['AWS_AZ'] ?? "Unknown AZ";
$region      = $_SERVER['AWS_REGION'] ?? "Unknown Region";

$db_error = "";
$result = false;

// 3. Connect to RDS
$conn = @new mysqli($db_host, $db_user, $db_pass);

if ($conn->connect_error) {
    $db_error = "Connection failed: " . $conn->connect_error;
} else {
    // 4. Initialize Database and Table safely
    if (!$conn->query("CREATE DATABASE IF NOT EXISTS $db_name")) {
        $db_error = "Failed to create DB: " . $conn->error;
    } else {
        $conn->select_db($db_name);
        if (!$conn->query("CREATE TABLE IF NOT EXISTS todos (id INT AUTO_INCREMENT PRIMARY KEY, task VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)")) {
            $db_error = "Failed to create table: " . $conn->error;
        }
    }

    // 5. Handle Add Task
    if (empty($db_error) && isset($_POST['add_task']) && !empty(trim($_POST['task']))) {
        $task = $conn->real_escape_string($_POST['task']);
        if (!$conn->query("INSERT INTO todos (task) VALUES ('$task')")) {
            die("Insert failed: " . $conn->error);
        }
        header("Location: index.php");
        exit();
    }

    // 6. Handle Delete Task
    if (empty($db_error) && isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        $conn->query("DELETE FROM todos WHERE id=$id");
        header("Location: index.php");
        exit();
    }

    // 7. Fetch all tasks
    if (empty($db_error)) {
        $result = $conn->query("SELECT * FROM todos ORDER BY created_at DESC");
        if (!$result) {
            $db_error = "Failed to fetch tasks: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capstone App - <?php echo htmlspecialchars($region); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen font-sans">
    <div class="max-w-3xl mx-auto py-12 px-4">
        
        <div class="bg-white rounded-t-2xl shadow-sm border-b border-gray-100 p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Demo To-do app</h1>
                    <p class="text-slate-500 text-sm">Deployment: <span class="text-blue-600 font-semibold">capstone-region-a-vpc</span></p>
                </div>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs font-mono bg-slate-50 p-3 rounded-lg border border-slate-200">
                    <div class="text-slate-400 uppercase tracking-tighter">Region</div>
                    <div class="text-slate-700 font-bold"><?php echo htmlspecialchars($region); ?></div>
                    <div class="text-slate-400 uppercase tracking-tighter">AZ</div>
                    <div class="text-slate-700 font-bold"><?php echo htmlspecialchars($az); ?></div>
                    <div class="text-slate-400 uppercase tracking-tighter">Instance</div>
                    <div class="text-slate-700 font-bold"><?php echo htmlspecialchars($instance_id); ?></div>
                    <div class="text-slate-400 uppercase tracking-tighter">DB Status</div>
                    <?php if (empty($db_error)): ?>
                        <div class="text-green-600 font-bold italic">Connected</div>
                    <?php else: ?>
                        <div class="text-red-600 font-bold italic">Offline</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-b-2xl shadow-lg p-8">
            
            <?php if (!empty($db_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <p class="font-bold">Database Error Detected:</p>
                    <p class="font-mono text-sm mt-1"><?php echo htmlspecialchars($db_error); ?></p>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST" class="flex gap-3 mb-8">
                <input type="text" name="task" placeholder="Add a new task..." required <?php echo !empty($db_error) ? 'disabled' : ''; ?>
                    class="flex-1 border-2 border-slate-200 rounded-xl px-4 py-3 focus:border-blue-500 focus:outline-none transition-all disabled:bg-gray-100">
                <button type="submit" name="add_task" <?php echo !empty($db_error) ? 'disabled' : ''; ?>
                    class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700 active:scale-95 transition-all disabled:opacity-50">
                    Add
                </button>
            </form>

            <div class="space-y-3">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="flex items-center justify-between bg-slate-50 p-4 rounded-xl border border-slate-100 hover:border-blue-200 transition-all">
                            <span class="text-slate-700"><?php echo htmlspecialchars($row['task']); ?></span>
                            <a href="index.php?delete=<?php echo $row['id']; ?>" class="text-slate-300 hover:text-red-500 transition-colors px-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php elseif(empty($db_error)): ?>
                    <div class="text-center py-10">
                        <p class="text-slate-400 italic">No tasks yet. Add one above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <footer class="mt-8 text-center text-slate-400 text-xs uppercase tracking-widest">
            Powered by Nginx & PHP-FPM on AWS
        </footer>
    </div>
</body>
</html>
<?php if(isset($conn) && !$conn->connect_error) $conn->close(); ?>
