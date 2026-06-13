<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/login_logger.php';
require_once __DIR__ . '/../includes/routes.php';
require_once __DIR__ . '/../includes/attendance.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $user = login($email, $password);

    if ($user) {

        // SECURITY: regenerate session
        session_regenerate_id(true);

        // session token
        $session_token = bin2hex(random_bytes(32));

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];
        $_SESSION['session_token'] = $session_token;
        $_SESSION['last_ping'] = time();

        // LOG LOGIN (MYSQLI)
        logLogin(
            $conn,
            $user['id'],
            $user['full_name'],
            $user['role'],
            $session_token
        );

        recordAttendanceOnLogin($conn, (int) $user['id']);

        // REDIRECT BY ROLE
        switch ($user['role']) {

            case 'admin':
                header("Location: " . route('admin.dashboard'));
                break;

            case 'hr':
                header("Location: " . route('hr.dashboard'));
                break;

            case 'employee':
                header("Location: " . route('employee.dashboard'));
                break;

            default:
                header("Location: " . route('login') . "?error=1");
                break;
        }

        exit();

    } else {

        header("Location: " . route('login') . "?error=1");
        exit();
    }
}
?>
