<?php
session_start();

if (!isset($_SESSION['active_challenge'])) {
    $_SESSION['active_challenge'] = 0;
}
if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = '/home/player';
}
if (!isset($_SESSION['terminal_history'])) {
    $_SESSION['terminal_history'] = [];
}

$activeId = $_SESSION['active_challenge'];
$cwd = $_SESSION['cwd'];

// Load challenges for verification
$challengesFile = '/app/challenges.json';
$challengesData = [];
if (file_exists($challengesFile)) {
    $challengesData = json_decode(file_get_contents($challengesFile), true);
}
$challenges = $challengesData['challenges'] ?? [];
$currentChallenge = null;
foreach ($challenges as $ch) {
    if ($ch['id'] == $activeId) {
        $currentChallenge = $ch;
        break;
    }
}

// Handle Clear History
if (isset($_POST['clear_history'])) {
    $_SESSION['terminal_history'] = [];
    header("Location: interact.php");
    exit;
}

// Handle Command Execution
$commandOutput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $rawCmd = trim($_POST['command']);
    
    if (!empty($rawCmd)) {
        $outputLines = [];
        $outputLines[] = "player@dungeon:" . $cwd . "$ " . htmlspecialchars($rawCmd);
        
        if (preg_match('/^cd(?:\s+(.*))?$/', $rawCmd, $matches)) {
            // Handle cd command
            $targetDir = trim($matches[1] ?? '');
            if (empty($targetDir)) {
                $targetDir = '/home/player';
            }
            
            // Validate and resolve target directory as user player
            $escapedCwd = escapeshellarg($cwd);
            $escapedTarget = escapeshellarg($targetDir);
            $resolveCmd = "cd $escapedCwd && cd $escapedTarget && pwd";
            $resolved = shell_exec("sudo -u player bash -c " . escapeshellarg($resolveCmd) . " 2>&1");
            
            if ($resolved && !str_contains($resolved, 'No such file') && !str_contains($resolved, 'Permission denied')) {
                $_SESSION['cwd'] = trim($resolved);
                $cwd = $_SESSION['cwd'];
            } else {
                $outputLines[] = $resolved ? trim($resolved) : "cd: {$targetDir}: No such directory or access denied.";
            }
        } elseif ($rawCmd === 'clear') {
            $_SESSION['terminal_history'] = [];
            header("Location: interact.php");
            exit;
        } else {
            // Run other commands as player unprivileged user
            $escapedCwd = escapeshellarg($cwd);
            $runCmd = "cd $escapedCwd && " . $rawCmd;
            $output = shell_exec("sudo -u player bash -c " . escapeshellarg($runCmd) . " 2>&1");
            if ($output !== null && $output !== '') {
                $outputLines[] = rtrim($output);
            } else {
                $outputLines[] = ""; // Empty command or no output
            }
        }
        
        // Append to history
        $_SESSION['terminal_history'] = array_merge($_SESSION['terminal_history'], $outputLines);
    }
}

// Handle Flag Submission
$flagMessage = '';
$flagMessageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitted_flag'])) {
    $submitted = trim($_POST['submitted_flag']);
    $correctFlag = $currentChallenge['default_flag'] ?? 'FLAG{...}';
    
    // Check if custom flag was stored inside /root/<challenge_id>/flag.txt
    $flagFile = "/root/{$activeId}/flag.txt";
    if (file_exists($flagFile)) {
        $correctFlag = trim(file_get_contents($flagFile));
    }
    
    if ($submitted === $correctFlag) {
        $flagMessage = "SUCCESS: Flag verification passed! Level " . ($activeId + 1) . " cleared.";
        $flagMessageType = "success";
    } else {
        $flagMessage = "ERROR: Incorrect flag. Try again.";
        $flagMessageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Linux Dungeon - Terminal Terminal</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/assets/js/config.js"></script>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #050507;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #202026;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #3e3e4a;
        }
    </style>
</head>
<body class="bg-[#0a0a0c] text-gray-200 min-h-screen flex flex-col font-sans">

    <header class="header-bar border-b border-gray-800/80 bg-gray-950/80 backdrop-blur-md px-8 py-4 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-950/40 border border-red-500 flex items-center justify-center">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
            </div>
            <h1 class="text-lg font-bold scifi-font glow-text">LINUX-DUNGEON // SHELL</h1>
        </div>
        <nav class="flex gap-4">
            <a href="index.php" class="text-xs font-mono uppercase text-gray-400 hover:text-gray-200 tracking-wider">Dashboard</a>
            <a href="interact.php" class="text-xs font-mono uppercase text-red-500 tracking-wider font-bold border-b-2 border-red-500 pb-1">Terminal Interface</a>
        </nav>
    </header>

    <main class="flex-1 max-w-6xl w-full mx-auto p-6 flex flex-col gap-6">

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            
            <!-- Left Side: Terminal Connection (8 columns) -->
            <div class="md:col-span-8 flex flex-col gap-4">
                
                <div class="terminal-header">
                    <span class="text-xs font-mono text-gray-400">interactive_terminal_session [user: player]</span>
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="clear_history" value="1" class="text-[10px] font-mono uppercase text-gray-500 hover:text-red-500 transition-colors">Clear Output</button>
                    </form>
                </div>
                
                <div class="terminal custom-scrollbar flex flex-col gap-1.5" id="terminal-screen">
                    <?php if (empty($_SESSION['terminal_history'])): ?>
                        <div class="text-gray-600 text-xs select-none">
                            [*] Dungeon sandbox established.<br>
                            [*] Running unprivileged shell access as user: 'player' (pass: player123).<br>
                            [*] Active directory: <?php echo htmlspecialchars($cwd); ?><br>
                            Type 'help' or list files to begin.
                        </div>
                    <?php else: ?>
                        <?php foreach ($_SESSION['terminal_history'] as $line): ?>
                            <div class="text-xs font-mono whitespace-pre-wrap"><?php echo $line; ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" class="terminal-input-wrapper">
                    <span class="text-xs font-mono text-red-500 mr-2 select-none">$</span>
                    <input type="text" name="command" id="cmd-input" placeholder="Execute commands (e.g. ls, pwd, cat)..." class="terminal-input" autocomplete="off" autofocus>
                </form>

            </div>

            <!-- Right Side: Verify & Details (4 columns) -->
            <div class="md:col-span-4 flex flex-col gap-5">
                
                <!-- Flag verification -->
                <div class="card flex flex-col gap-4">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-gray-200 border-b border-gray-800 pb-3 font-mono">Flag Submission</h2>
                    
                    <?php if (!empty($flagMessage)): ?>
                        <div class="p-3 rounded-lg border <?php echo $flagMessageType === 'success' ? 'bg-emerald-950/20 border-emerald-500/50 text-emerald-400' : 'bg-red-950/20 border-red-500/50 text-red-400'; ?> text-xs font-mono">
                            <?php echo htmlspecialchars($flagMessage); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="flex flex-col gap-3">
                        <div>
                            <span class="block text-gray-500 text-[9px] uppercase font-mono tracking-wider">Submitting for</span>
                            <span class="text-gray-200 text-xs font-bold font-mono"><?php echo $currentChallenge ? htmlspecialchars($currentChallenge['title']) : 'No Active Level'; ?></span>
                        </div>
                        <input type="text" name="submitted_flag" placeholder="FLAG{...}" required class="w-full bg-gray-900 border border-gray-800 rounded-lg py-2.5 px-4 text-sm font-mono text-gray-200 focus:outline-none focus:border-red-500">
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-2.5 rounded-lg font-mono text-xs font-bold uppercase transition-all tracking-wider shadow-[0_0_15px_rgba(255,59,48,0.3)]">Verify Flag</button>
                    </form>
                </div>

                <!-- Active Level Info Card -->
                <div class="card flex flex-col gap-3">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-gray-200 border-b border-gray-800 pb-3 font-mono">Environment Metadata</h2>
                    <div class="flex flex-col gap-2.5 text-xs font-mono text-gray-400">
                        <div class="flex justify-between border-b border-gray-800/40 pb-1.5">
                            <span>Target Level</span>
                            <span class="text-red-400">Lvl <?php echo $activeId; ?></span>
                        </div>
                        <div class="flex justify-between border-b border-gray-800/40 pb-1.5">
                            <span>Container ID</span>
                            <span class="text-gray-200"><?php echo htmlspecialchars(gethostname()); ?></span>
                        </div>
                        <div class="flex justify-between border-b border-gray-800/40 pb-1.5">
                            <span>User Privilege</span>
                            <span class="text-gray-400">unprivileged (player)</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Target Root Flag</span>
                            <span class="text-gray-500">/root/<?php echo $activeId; ?>/flag.txt</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </main>

    <script>
        // Auto scroll terminal to the bottom
        const screen = document.getElementById('terminal-screen');
        if (screen) {
            screen.scrollTop = screen.scrollHeight;
        }

        // Keep input field focused
        const input = document.getElementById('cmd-input');
        if (input) {
            input.focus();
            document.addEventListener('click', () => {
                input.focus();
            });
        }
    </script>

</body>
</html>
