<?php
$servername = "localhost";
$username = "motorsss";
$password = "4JJnTEgH3Qppl0qQojBY";
$dbname = "motorsss";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get tables
$tables = [];
$sql = "SHOW TABLES";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Tables</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
    <h2>Database Tables in <code><?php echo htmlspecialchars($dbname); ?></code></h2>
    <div class="accordion" id="tablesAccordion">
        <?php foreach($tables as $i => $table): ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?php echo $i; ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $i; ?>" aria-expanded="false" aria-controls="collapse<?php echo $i; ?>">
                    <?php echo htmlspecialchars($table); ?>
                </button>
            </h2>
            <div id="collapse<?php echo $i; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $i; ?>" data-bs-parent="#tablesAccordion">
                <div class="accordion-body">
                    <?php
                    // Get rows for this table
                    $sql2 = "SELECT * FROM `$table` LIMIT 10";
                    $res2 = $conn->query($sql2);
                    if ($res2 && $res2->num_rows > 0) {
                        echo "<table class='table table-bordered table-sm'><thead><tr>";
                        // Table headers
                        while ($fieldinfo = $res2->fetch_field()) {
                            echo "<th>" . htmlspecialchars($fieldinfo->name) . "</th>";
                        }
                        echo "</tr></thead><tbody>";
                        // Table rows
                        while($row2 = $res2->fetch_assoc()) {
                            echo "<tr>";
                            foreach($row2 as $cell) {
                                echo "<td>" . htmlspecialchars($cell) . "</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</tbody></table>";
                    } else {
                        echo "<em>No rows found or table is empty.</em>";
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- Bootstrap JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
