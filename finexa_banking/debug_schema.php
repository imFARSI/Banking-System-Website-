<?php
require_once 'includes/config.php';

function describeTable($conn, $table) {
    echo "<h3>Table: $table</h3>";
    $result = mysqli_query($conn, "DESCRIBE $table");
    if (!$result) {
        echo "Error: " . mysqli_error($conn) . "<br>";
        return;
    }
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        foreach ($row as $val) {
            echo "<td>" . ($val ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

describeTable($conn, 'transactions');
describeTable($conn, 'accounts');
describeTable($conn, 'notifications');
describeTable($conn, 'activity_logs');
?>
