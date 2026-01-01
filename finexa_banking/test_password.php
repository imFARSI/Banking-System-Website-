<?php
// TEST PASSWORD HASHING
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Password Test</h3>";
echo "Password: admin123<br>";
echo "Hash: " . $hash . "<br><br>";

// Test verification
if (password_verify('admin123', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFGJXLzoJEZ7TTeXxjXvWqY9Wqy1C4Ga')) {
    echo "✅ Password verification WORKS!<br>";
} else {
    echo "❌ Password verification FAILED!<br>";
}

echo "<hr>";
echo "<h4>Use this hash in database:</h4>";
echo "<textarea style='width:100%; height:100px;'>$hash</textarea>";
?>