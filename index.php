<?php
include 'db_connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'login') {
        // Handle login
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];

        $query = "SELECT user_id, password FROM User WHERE username = '$username'";
        $result = $conn->query($query);

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                header('Location: chat.php');
                exit();
            } else {
                $error_message = "Invalid password.";
            }
        } else {
            $error_message = "User not found.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'register') {
        // Handle registration
        $username = $conn->real_escape_string($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $display_name = $conn->real_escape_string($_POST['display_name']);

        $checkQuery = "SELECT user_id FROM User WHERE username = '$username'";
        $checkResult = $conn->query($checkQuery);

        if ($checkResult->num_rows > 0) {
            $error_message = "Username already exists.";
        } else {
            $query = "INSERT INTO User (username, password, display_name) 
                      VALUES ('$username', '$password', '$display_name')";

            if ($conn->query($query) === TRUE) {
                $success_message = "Registration successful. Please log in.";
            } else {
                $error_message = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bracket - Login</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="container" id="loginContainer" style="<?= isset($_POST['action']) && $_POST['action'] == 'register' ? 'display: none;' : 'display: block;' ?>">
        <h2>Login</h2>
        <?php if (isset($error_message) && $_POST['action'] == 'login'): ?>
            <p class="error"><?= $error_message ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <div class="toggle-link">
            <a href="#" onclick="toggleForm()">Don't have an account? Register</a>
        </div>
    </div>

    <div class="container" id="registerContainer" style="<?= isset($_POST['action']) && $_POST['action'] == 'register' ? 'display: block;' : 'display: none;' ?>">
        <h2>Register</h2>
        <?php if (isset($error_message) && $_POST['action'] == 'register'): ?>
            <p class="error"><?= $error_message ?></p>
        <?php endif; ?>
        <?php if (isset($success_message)): ?>
            <p class="success"><?= $success_message ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="register">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="display_name" placeholder="Display Name" required>
            <button type="submit">Register</button>
        </form>
        <div class="toggle-link">
            <a href="#" onclick="toggleForm()">Already have an account? Login</a>
        </div>
    </div>

    <script>
        function toggleForm() {
            const loginContainer = document.getElementById('loginContainer');
            const registerContainer = document.getElementById('registerContainer');
            loginContainer.style.display = loginContainer.style.display === 'none' ? 'block' : 'none';
            registerContainer.style.display = registerContainer.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
