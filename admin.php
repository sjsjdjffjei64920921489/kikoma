<?php
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
]);
session_start();

function adminJsonResponse(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function adminJsonInput(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function adminDatabase(): mysqli
{
    require __DIR__ . '/config/connect.php';
    return $conn;
}

function ensureAdminAccount(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(80) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_admin_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $result = $conn->query("SELECT id FROM admin_users WHERE username = 'admin' LIMIT 1");
    if ($result->num_rows === 0) {
        $defaultPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT IGNORE INTO admin_users (username, password_hash) VALUES ('admin', ?)");
        $stmt->bind_param('s', $defaultPasswordHash);
        $stmt->execute();
        $stmt->close();
    }
    $result->free();
}

function requireAdminSession(): void
{
    if (($_SESSION['admin_authenticated'] ?? false) !== true) {
        adminJsonResponse(401, ['message' => 'Please sign in to view trip requests.']);
    }
}

function requireAdminCsrfToken(): void
{
    $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['admin_csrf_token'] ?? '';
    if (!is_string($providedToken) || !is_string($sessionToken)
        || $sessionToken === '' || !hash_equals($sessionToken, $providedToken)) {
        adminJsonResponse(403, ['message' => 'Your session has expired. Please sign in again.']);
    }
}

if (isset($_GET['api'])) {
    try {
        $action = (string) $_GET['api'];
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($action === 'session' && $method === 'GET') {
            $authenticated = ($_SESSION['admin_authenticated'] ?? false) === true;
            if ($authenticated && empty($_SESSION['admin_csrf_token'])) {
                $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            }
            adminJsonResponse(200, [
                'authenticated' => $authenticated,
                'csrfToken' => $authenticated ? $_SESSION['admin_csrf_token'] : null,
            ]);
        }

        if ($action === 'login' && $method === 'POST') {
            $input = adminJsonInput();
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            if (!is_string($username) || !is_string($password)) {
                adminJsonResponse(400, ['message' => 'Enter a valid username and password.']);
            }

            $conn = adminDatabase();
            ensureAdminAccount($conn);
            $stmt = $conn->prepare("SELECT password_hash FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$account || !password_verify($password, $account['password_hash'])) {
                adminJsonResponse(401, ['message' => 'Incorrect username or password.']);
            }

            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            adminJsonResponse(200, ['csrfToken' => $_SESSION['admin_csrf_token']]);
        }

        if ($action === 'requests' && $method === 'GET') {
            requireAdminSession();
            $conn = adminDatabase();
            $statsResult = $conn->query("
                SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'new'), 0) AS new_leads,
                    COALESCE(SUM(status <> 'replied'), 0) AS follow_up
                FROM inquiries
            ");
            $stats = $statsResult->fetch_assoc();

            $requestResult = $conn->query("
                SELECT name, email, message, status
                FROM inquiries
                ORDER BY created_at DESC, id DESC
                LIMIT 8
            ");
            $requests = [];
            while ($row = $requestResult->fetch_assoc()) {
                $date = 'Flexible';
                $experience = 'General enquiry';
                foreach (preg_split('/\R/', $row['message']) as $messageLine) {
                    if (preg_match('/^Preferred travel month:\s*(.*)$/', $messageLine, $matches)) {
                        $date = trim($matches[1]) ?: $date;
                    } elseif (preg_match('/^Experience:\s*(.*)$/', $messageLine, $matches)) {
                        $experience = trim($matches[1]) ?: $experience;
                    }
                }

                $requests[] = [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'experience' => $experience,
                    'date' => $date,
                    'status' => $row['status'],
                ];
            }
            adminJsonResponse(200, [
                'stats' => [
                    'total' => (int) $stats['total'],
                    'newLeads' => (int) $stats['new_leads'],
                    'followUp' => (int) $stats['follow_up'],
                ],
                'requests' => $requests,
            ]);
        }

        if ($action === 'password' && $method === 'POST') {
            requireAdminSession();
            requireAdminCsrfToken();
            $input = adminJsonInput();
            $currentPassword = $input['currentPassword'] ?? '';
            $newPassword = $input['newPassword'] ?? '';
            if (!is_string($currentPassword) || !is_string($newPassword)
                || strlen($newPassword) < 8 || strlen($newPassword) > 255) {
                adminJsonResponse(400, ['message' => 'New password must be between 8 and 255 characters.']);
            }

            $conn = adminDatabase();
            ensureAdminAccount($conn);
            $stmt = $conn->prepare("SELECT password_hash FROM admin_users WHERE username = 'admin' LIMIT 1");
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
                adminJsonResponse(400, ['message' => 'Current password is incorrect.']);
            }

            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE username = 'admin'");
            $stmt->bind_param('s', $newPasswordHash);
            $stmt->execute();
            $stmt->close();
            adminJsonResponse(200, ['message' => 'Password updated successfully.']);
        }

        if ($action === 'logout' && $method === 'POST') {
            requireAdminSession();
            requireAdminCsrfToken();
            $_SESSION = [];
            session_destroy();
            adminJsonResponse(200, ['message' => 'Signed out.']);
        }

        adminJsonResponse(404, ['message' => 'Admin API endpoint not found.']);
    } catch (Throwable $exception) {
        error_log('Admin dashboard error: ' . $exception->getMessage());
        adminJsonResponse(500, ['message' => 'The dashboard could not complete that request. Please try again.']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>KIKOMA Admin</title>
    <style>
        :root {
            --bg: #f5f3ef;
            --panel: #ffffff;
            --panel-alt: #f1efe9;
            --line: #e3dfd7;
            --green: #314c35;
            --green-dark: #253c29;
            --orange: #d88651;
            --text: #1a221c;
            --muted: #666d63;
            --danger: #b64545;
            --success: #2a7a4a;
            --shadow: 0 18px 42px rgba(38, 52, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(135deg, #f5f3ef 0%, #f0efe8 100%);
            color: var(--text);
        }

        .admin-shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            padding: 28px 22px;
            background: var(--green-dark);
            color: #edf0ea;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #dfe7bf;
        }

        .brand-mark svg {
            width: 22px;
            height: 22px;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .brand-title {
            letter-spacing: 0.14em;
            font-size: 12px;
            font-weight: 700;
        }

        .brand-subtitle {
            margin-top: 4px;
            font-size: 9px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.72);
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 28px;
        }

        .nav-item {
            display: block;
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
            color: #edf0ea;
            font-size: 14px;
            text-decoration: none;
        }

        .nav-item.active {
            background: rgba(216, 134, 81, 0.18);
            border: 1px solid rgba(216, 134, 81, 0.38);
        }

        .content {
            flex: 1;
            padding: 40px 24px;
        }

        .panel {
            max-width: 1100px;
            margin: 0 auto;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: var(--shadow);
        }

        .auth-panel {
            max-width: 500px;
            padding: 40px 30px;
            margin: 80px auto;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--green);
        }

        .eyebrow::before {
            content: "";
            width: 24px;
            height: 1px;
            background: var(--orange);
        }

        h1,
        h2,
        h3,
        p {
            margin-top: 0;
        }

        h1 {
            margin: 18px 0 8px;
            font-size: clamp(2rem, 3vw, 2.7rem);
            color: var(--text);
        }

        .subtext {
            margin-bottom: 24px;
            color: var(--muted);
            line-height: 1.7;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text);
        }

        .field input {
            width: 100%;
            min-height: 48px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 10px;
            font-size: 15px;
            background: #fff;
        }

        .field input:focus {
            outline: 2px solid rgba(49, 76, 53, 0.18);
            border-color: var(--green);
        }

        button {
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        button:hover {
            transform: translateY(-1px);
        }

        .primary-btn {
            min-height: 48px;
            padding: 0 18px;
            border-radius: 10px;
            background: var(--green);
            color: white;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .secondary-btn {
            min-height: 40px;
            padding: 0 16px;
            border-radius: 9px;
            background: var(--panel-alt);
            color: var(--text);
            font-weight: 600;
        }

        .message {
            min-height: 20px;
            margin: 0;
            font-size: 13px;
            color: var(--muted);
        }

        .message.error {
            color: var(--danger);
        }

        .message.success {
            color: var(--success);
        }

        .dashboard {
            display: none;
            padding: 22px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 18px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel-alt);
        }

        .topbar h2 {
            margin: 0;
            font-size: 1.1rem;
        }

        .topbar-actions {
            display: flex;
            gap: 10px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 22px;
        }

        .password-panel {
            margin-top: 22px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
        }

        .password-panel h3 {
            margin-bottom: 16px;
            font-size: 1.1rem;
        }

        .password-form {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            align-items: end;
        }

        .password-form .field {
            min-width: 0;
        }

        .stat-card {
            padding: 20px 18px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: linear-gradient(180deg, #fff 0%, #f8f7f3 100%);
        }

        .stat-label {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .stat-value {
            margin-top: 12px;
            font-size: clamp(1.8rem, 2vw, 2.5rem);
            font-weight: 700;
            color: var(--green);
        }

        .table-panel {
            margin-top: 22px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
        }

        .table-panel h3 {
            margin-bottom: 14px;
            font-size: 1.1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
        }

        th,
        td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            font-size: 13px;
        }

        th {
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .chip {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #edf5ef;
            color: var(--success);
            font-size: 11px;
            font-weight: 700;
        }

        .chip.pending {
            background: #fff1e3;
            color: #9a5d1d;
        }

        .chip.reviewed {
            background: #eaf0ff;
            color: #4059b9;
        }

        @media (max-width: 760px) {
            .admin-shell {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding-bottom: 18px;
            }

            .content {
                padding: 18px 16px 28px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <div class="admin-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M6 23.5 15.8 7l9.8 16.5H6Z" stroke="currentColor" stroke-width="1.5"
                            stroke-linejoin="round" />
                        <path d="m11.3 23.5 4.5-7.4 4.5 7.4M4 26h24" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" />
                    </svg>
                </span>
                <span class="brand-text">
                    <span class="brand-title">KIKOMA</span>
                    <span class="brand-subtitle">Admin</span>
                </span>
            </div>

            <nav class="sidebar-nav">
                <a class="nav-item active" href="#">Dashboard</a>
                <a class="nav-item" href="index.php">View website</a>
            </nav>
        </aside>

        <main class="content">
            <section class="panel auth-panel" id="login-screen">
                <span class="eyebrow">Admin access</span>
                <h1>Sign in</h1>
                <p class="subtext">Use your admin account to view trip requests and manage the KIKOMA website dashboard.
                </p>

                <form id="signin-form">
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" type="text" value="admin" autocomplete="username" required />
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" type="password" placeholder="Enter your password"
                            autocomplete="current-password" required />
                    </div>

                    <button class="primary-btn" type="submit">Sign in</button>
                    <p id="login-message" class="message" aria-live="polite"></p>
                </form>
            </section>

            <section class="panel dashboard" id="dashboard-screen">
                <div class="topbar">
                    <h2>Welcome, Admin</h2>
                    <div class="topbar-actions">
                        <button class="secondary-btn" id="refresh-button" type="button">Refresh</button>
                        <button class="secondary-btn" id="signout-button" type="button">Sign out</button>
                    </div>
                </div>
                <p id="dashboard-message" class="message" aria-live="polite"></p>

                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-label">Total requests</div>
                        <div class="stat-value" id="total-requests">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">New leads</div>
                        <div class="stat-value" id="new-leads">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Follow-up</div>
                        <div class="stat-value" id="follow-up">0</div>
                    </div>
                </div>

                <div class="password-panel">
                    <h3>Change password</h3>
                    <form id="change-password-form" class="password-form">
                        <div class="field">
                            <label for="current-password">Current password</label>
                            <input id="current-password" type="password" autocomplete="current-password" required />
                        </div>
                        <div class="field">
                            <label for="new-password">New password</label>
                            <input id="new-password" type="password" autocomplete="new-password" required />
                        </div>
                        <div class="field">
                            <label for="confirm-password">Confirm password</label>
                            <input id="confirm-password" type="password" autocomplete="new-password" required />
                        </div>
                        <button class="secondary-btn" type="submit">Update password</button>
                    </form>
                    <p id="password-message" class="message" aria-live="polite"></p>
                </div>

                <div class="table-panel">
                    <h3>Recent trip requests</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Plan</th>
                                <th>Preferred date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="requests-body"></tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        const API_URL = 'admin.php?api=';
        let csrfToken = '';

        const loginScreen = document.getElementById('login-screen');
        const dashboardScreen = document.getElementById('dashboard-screen');
        const loginForm = document.getElementById('signin-form');
        const loginMessage = document.getElementById('login-message');
        const refreshButton = document.getElementById('refresh-button');
        const signoutButton = document.getElementById('signout-button');
        const requestsBody = document.getElementById('requests-body');
        const passwordForm = document.getElementById('change-password-form');
        const passwordMessage = document.getElementById('password-message');
        const dashboardMessage = document.getElementById('dashboard-message');

        async function apiRequest(action, options = {}) {
            const response = await fetch(`${API_URL}${action}`, {
                credentials: 'same-origin',
                ...options
            });
            let data;
            try {
                data = await response.json();
            } catch {
                throw new Error('The server returned an invalid response. Please try again.');
            }
            if (!response.ok) {
                const error = new Error(data.message || 'The request could not be completed.');
                error.status = response.status;
                throw error;
            }
            return data;
        }

        function postJson(action, body) {
            return apiRequest(action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(body)
            });
        }

        function showDashboard() {
            loginScreen.style.display = 'none';
            dashboardScreen.style.display = 'block';
            renderDashboard();
        }

        function showLogin() {
            dashboardScreen.style.display = 'none';
            loginScreen.style.display = 'block';
            loginMessage.textContent = '';
            loginMessage.className = 'message';
        }

        function appendCell(row, value) {
            const cell = document.createElement('td');
            cell.textContent = value;
            row.appendChild(cell);
            return cell;
        }

        async function renderDashboard() {
            dashboardMessage.textContent = '';
            dashboardMessage.className = 'message';
            requestsBody.replaceChildren();

            try {
                const data = await apiRequest('requests');
                document.getElementById('total-requests').textContent = data.stats.total;
                document.getElementById('new-leads').textContent = data.stats.newLeads;
                document.getElementById('follow-up').textContent = data.stats.followUp;

                if (data.requests.length === 0) {
                    const row = document.createElement('tr');
                    const cell = appendCell(row, 'No trip requests yet.');
                    cell.colSpan = 5;
                    requestsBody.appendChild(row);
                    return;
                }

                data.requests.forEach(request => {
                    const row = document.createElement('tr');
                    appendCell(row, request.name || 'Unknown');
                    appendCell(row, request.email || 'No email');
                    appendCell(row, request.experience || 'General enquiry');
                    appendCell(row, request.date || 'Flexible');

                    const status = request.status || 'new';
                    const statusLabel = status === 'replied'
                        ? 'Reviewed'
                        : status.charAt(0).toUpperCase() + status.slice(1);
                    const statusClass = status === 'replied'
                        ? 'reviewed'
                        : status === 'new' ? 'new' : 'pending';
                    const statusCell = document.createElement('td');
                    const chip = document.createElement('span');
                    chip.className = `chip ${statusClass}`;
                    chip.textContent = statusLabel;
                    statusCell.appendChild(chip);
                    row.appendChild(statusCell);
                    requestsBody.appendChild(row);
                });
            } catch (error) {
                if (error.status === 401) {
                    showLogin();
                    loginMessage.textContent = error.message;
                    loginMessage.className = 'message error';
                    return;
                }
                dashboardMessage.textContent = error.message;
                dashboardMessage.className = 'message error';
            }
        }

        loginForm.addEventListener('submit', async event => {
            event.preventDefault();
            loginMessage.textContent = '';
            loginMessage.className = 'message';
            try {
                const result = await apiRequest('login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: document.getElementById('username').value.trim(),
                        password: document.getElementById('password').value
                    })
                });
                csrfToken = result.csrfToken;
                showDashboard();
            } catch (error) {
                loginMessage.textContent = error.message;
                loginMessage.className = 'message error';
            }
        });

        passwordForm.addEventListener('submit', async event => {
            event.preventDefault();
            passwordMessage.textContent = '';
            passwordMessage.className = 'message';
            const newPassword = document.getElementById('new-password').value;
            if (newPassword !== document.getElementById('confirm-password').value) {
                passwordMessage.textContent = 'New password and confirm password do not match.';
                passwordMessage.className = 'message error';
                return;
            }

            try {
                const result = await postJson('password', {
                    currentPassword: document.getElementById('current-password').value,
                    newPassword
                });
                passwordMessage.textContent = result.message;
                passwordMessage.className = 'message success';
                passwordForm.reset();
            } catch (error) {
                passwordMessage.textContent = error.message;
                passwordMessage.className = 'message error';
            }
        });

        signoutButton.addEventListener('click', async () => {
            try {
                await postJson('logout', {});
                csrfToken = '';
                showLogin();
            } catch (error) {
                dashboardMessage.textContent = error.message;
                dashboardMessage.className = 'message error';
            }
        });

        refreshButton.addEventListener('click', renderDashboard);

        async function initializeAdmin() {
            try {
                const session = await apiRequest('session');
                if (session.authenticated) {
                    csrfToken = session.csrfToken;
                    showDashboard();
                } else {
                    showLogin();
                }
            } catch (error) {
                showLogin();
                loginMessage.textContent = error.message;
                loginMessage.className = 'message error';
            }
        }

        initializeAdmin();
    </script>
</body>

</html>