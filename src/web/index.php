<?php
session_start();

// Initialize active challenge and cwd if not set
if (!isset($_SESSION['active_challenge'])) {
    $_SESSION['active_challenge'] = 0;
}
if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = '/home/player';
}

// Load challenges metadata
$challengesFile = '/app/challenges.json';
$challengesData = [];
if (file_exists($challengesFile)) {
    $challengesData = json_decode(file_get_contents($challengesFile), true);
}
$challenges = $challengesData['challenges'] ?? [];
$challengeCount = $challengesData['count'] ?? 0;

$activeId = $_SESSION['active_challenge'];
$currentChallenge = null;
foreach ($challenges as $ch) {
    if ($ch['id'] == $activeId) {
        $currentChallenge = $ch;
        break;
    }
}

// Process deploy/restart challenge action
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $chId = intval($_POST['challenge_id'] ?? 0);
    
    // Find default flag
    $flag = $_POST['custom_flag'] ?? '';
    if (empty($flag)) {
        foreach ($challenges as $ch) {
            if ($ch['id'] == $chId) {
                $flag = $ch['default_flag'] ?? 'FLAG{default}';
                break;
            }
        }
    }

    if ($action === 'deploy' || $action === 'restart') {
        $containerId = gethostname();
        
        // Execute the challenge script as root via sudo
        $scriptPath = "/app/scripts/{$chId}.sh";
        if (file_exists($scriptPath)) {
            $cmd = "sudo " . escapeshellarg($scriptPath) . " " . escapeshellarg($containerId) . " " . escapeshellarg($flag);
            $output = shell_exec($cmd . " 2>&1");
            
            $_SESSION['active_challenge'] = $chId;
            $_SESSION['cwd'] = '/home/player'; // Reset directory
            
            $message = "Challenge Level {$chId} successfully deployed!\n\nSetup Output:\n" . htmlspecialchars($output);
            $messageType = "success";
            
            // Reload page to update info
            header("Location: index.php?msg=" . urlencode($message) . "&type=" . $messageType);
            exit;
        } else {
            $message = "Setup script not found: {$scriptPath}";
            $messageType = "error";
        }
    }
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'info';
}

// Load active brief
$briefContent = "No brief available.";
if ($currentChallenge) {
    $briefPath = "/app/briefs/" . $currentChallenge['brief'];
    if (file_exists($briefPath)) {
        $briefContent = file_get_contents($briefPath);
    }
}

// Helper to convert simple markdown to HTML
function renderMarkdown($text) {
    $text = htmlspecialchars($text);
    $text = preg_replace('/^# (.*)$/m', '<h1 class="text-xl font-bold text-red-500 mb-4 font-mono">$1</h1>', $text);
    $text = preg_replace('/^## (.*)$/m', '<h2 class="text-sm font-bold text-gray-200 mt-4 mb-2 uppercase tracking-wider">$1</h2>', $text);
    $text = preg_replace('/^### (.*)$/m', '<h3 class="text-xs font-bold text-gray-300 mt-3 mb-1 uppercase">$1</h3>', $text);
    $text = preg_replace('/^- (.*)$/m', '<li class="text-xs text-gray-400 ml-4 list-disc mb-1">$1</li>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code class="bg-gray-900 border border-gray-800 text-red-400 px-1.5 py-0.5 rounded font-mono text-[11px]">$1</code>', $text);
    return nl2br($text);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Linux Dungeon - Control Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/assets/js/config.js"></script>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="bg-[#0a0a0c] text-gray-200 min-h-screen flex flex-col font-sans">

    <header class="header-bar border-b border-gray-800/80 bg-gray-950/80 backdrop-blur-md px-8 py-4 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-950/40 border border-red-500 flex items-center justify-center">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
            </div>
            <h1 class="text-lg font-bold scifi-font glow-text">LINUX-DUNGEON // PORTAL</h1>
        </div>
        <nav class="flex gap-4">
            <a href="index.php" class="text-xs font-mono uppercase text-red-500 tracking-wider font-bold border-b-2 border-red-500 pb-1">Dashboard</a>
            <a href="interact.php" class="text-xs font-mono uppercase text-gray-400 hover:text-gray-200 tracking-wider">Terminal Interface</a>
        </nav>
    </header>

    <main class="flex-1 max-w-6xl w-full mx-auto p-6 flex flex-col gap-6">

        <?php if (!empty($message)): ?>
            <div class="p-4 rounded-xl border <?php echo $messageType === 'success' ? 'bg-emerald-950/20 border-emerald-500/50 text-emerald-400' : 'bg-red-950/20 border-red-500/50 text-red-400'; ?> text-xs font-mono whitespace-pre-wrap">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Left Panel: Levels & Control -->
            <div class="flex flex-col gap-6">
                <div class="card flex flex-col gap-4">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-gray-200 border-b border-gray-800 pb-3 font-mono">Challenge Rooms</h2>
                    <div class="flex flex-col gap-3">
                        <?php foreach ($challenges as $ch): ?>
                            <div class="p-4 rounded-xl border <?php echo $ch['id'] == $activeId ? 'border-red-500/50 bg-red-950/5' : 'border-gray-800 bg-gray-900/30'; ?> flex flex-col gap-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-xs font-bold text-gray-200"><?php echo htmlspecialchars($ch['title']); ?></h3>
                                        <span class="text-[9px] font-mono text-gray-500">ID: <?php echo $ch['id']; ?> // script: <?php echo $ch['script']; ?></span>
                                    </div>
                                    <?php if ($ch['id'] == $activeId): ?>
                                        <span class="bg-red-500/10 text-red-500 border border-red-500/20 text-[9px] font-mono px-2 py-0.5 rounded">Active</span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" class="flex flex-col gap-2">
                                    <input type="hidden" name="challenge_id" value="<?php echo $ch['id']; ?>">
                                    <input type="text" name="custom_flag" placeholder="Optional: Custom --flag" class="w-full bg-gray-900 border border-gray-800 rounded px-2.5 py-1 text-[10px] font-mono text-gray-300 focus:outline-none focus:border-red-500">
                                    <button type="submit" name="action" value="<?php echo $ch['id'] == $activeId ? 'restart' : 'deploy'; ?>" class="w-full bg-red-950/20 border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white py-1.5 rounded text-[10px] font-mono font-bold tracking-wider uppercase transition-all">
                                        <?php echo $ch['id'] == $activeId ? 'Restart Level' : 'Deploy Level'; ?>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card flex flex-col gap-3">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-gray-200 border-b border-gray-800 pb-3 font-mono">System Telemetry</h2>
                    <div class="grid grid-cols-2 gap-4 text-xs font-mono text-gray-400">
                        <div>
                            <span class="block text-gray-500 text-[9px] uppercase">Container ID</span>
                            <span class="text-gray-300"><?php echo htmlspecialchars(gethostname()); ?></span>
                        </div>
                        <div>
                            <span class="block text-gray-500 text-[9px] uppercase">Active Level</span>
                            <span class="text-red-500 font-bold">Lvl <?php echo $activeId; ?></span>
                        </div>
                        <div>
                            <span class="block text-gray-500 text-[9px] uppercase">Shell User</span>
                            <span class="text-gray-300">player</span>
                        </div>
                        <div>
                            <span class="block text-gray-500 text-[9px] uppercase">Base Workspace</span>
                            <span class="text-gray-300">/home/player</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Brief & Commands Cheat Sheet (2 columns) -->
            <div class="md:col-span-2 flex flex-col gap-6">
                <!-- Mission Brief Card -->
                <div class="card">
                    <div class="border-b border-gray-800 pb-3 mb-4 flex justify-between items-center">
                        <h2 class="text-sm font-bold uppercase tracking-widest text-gray-200 font-mono">Current Mission Briefing</h2>
                        <a href="interact.php" class="bg-red-500 hover:bg-red-600 text-white font-mono text-[10px] font-bold uppercase px-3 py-1.5 rounded transition-all shadow-[0_0_10px_rgba(255,59,48,0.3)]">Enter Terminal</a>
                    </div>
                    <div class="prose prose-invert max-w-none text-xs leading-relaxed">
                        <?php echo renderMarkdown($briefContent); ?>
                    </div>
                </div>

                <!-- Command Cheat Sheet -->
                <div class="card flex flex-col gap-4">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-gray-200 border-b border-gray-800 pb-3 font-mono">Linux Command Directory</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div class="bg-gray-900/20 border border-gray-800/80 rounded-xl p-4 flex flex-col gap-2">
                            <h3 class="font-bold text-red-400 font-mono">File & Directory System</h3>
                            <ul class="flex flex-col gap-1.5 font-mono text-gray-400 text-[11px]">
                                <li><span class="text-gray-200">ls -la</span> - List files in details (incl. hidden)</li>
                                <li><span class="text-gray-200">cd &lt;dir&gt;</span> - Change active working directory</li>
                                <li><span class="text-gray-200">pwd</span> - Print current working directory path</li>
                                <li><span class="text-gray-200">cat &lt;file&gt;</span> - Display the content of a file</li>
                                <li><span class="text-gray-200">find &lt;path&gt;</span> - Search for files in directory tree</li>
                            </ul>
                        </div>
                        <div class="bg-gray-900/20 border border-gray-800/80 rounded-xl p-4 flex flex-col gap-2">
                            <h3 class="font-bold text-red-400 font-mono">Permissions & Ownership</h3>
                            <ul class="flex flex-col gap-1.5 font-mono text-gray-400 text-[11px]">
                                <li><span class="text-gray-200">chmod &lt;octal&gt;</span> - Alter read/write/exec permissions</li>
                                <li><span class="text-gray-200">chown &lt;user:gp&gt;</span> - Update file ownership credentials</li>
                                <li><span class="text-gray-200">sudo &lt;cmd&gt;</span> - Run commands with administrative rights</li>
                                <li><span class="text-gray-200">find -perm -4000</span> - Query all SetUID binaries on system</li>
                            </ul>
                        </div>
                        <div class="bg-gray-900/20 border border-gray-800/80 rounded-xl p-4 flex flex-col gap-2">
                            <h3 class="font-bold text-red-400 font-mono">Processes & Jobs</h3>
                            <ul class="flex flex-col gap-1.5 font-mono text-gray-400 text-[11px]">
                                <li><span class="text-gray-200">ps aux</span> - Report snapshot of current active processes</li>
                                <li><span class="text-gray-200">top</span> - Monitor system resources and active jobs</li>
                                <li><span class="text-gray-200">kill &lt;pid&gt;</span> - Send termination signal to process ID</li>
                                <li><span class="text-gray-200">pkill &lt;pattern&gt;</span> - Terminate processes matching signature</li>
                            </ul>
                        </div>
                        <div class="bg-gray-900/20 border border-gray-800/80 rounded-xl p-4 flex flex-col gap-2">
                            <h3 class="font-bold text-red-400 font-mono">Network Utilities</h3>
                            <ul class="flex flex-col gap-1.5 font-mono text-gray-400 text-[11px]">
                                <li><span class="text-gray-200">netstat -tlnp</span> - Display TCP listening network ports</li>
                                <li><span class="text-gray-200">ss -tlnp</span> - List listening sockets (faster netstat)</li>
                                <li><span class="text-gray-200">curl &lt;url&gt;</span> - Transfer data from/to network server</li>
                                <li><span class="text-gray-200">nc &lt;host&gt; &lt;port&gt;</span> - Arbitrary TCP and UDP connections</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>

</body>
</html>
