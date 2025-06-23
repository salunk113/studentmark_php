<?php
// DB Connection
$host = 'localhost';
$user = 'root';
$password = ''; // Use your MySQL root password
$db = 'school';

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add student marks if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['student_name'];
    $subject = $_POST['subject'];
    $marks = $_POST['marks'];
    $stmt = $conn->prepare("INSERT INTO students_marks (student_name, subject, marks) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $subject, $marks);
    $stmt->execute();
    $stmt->close();

    // Redirect to avoid form resubmission
    header("Location: view_marks.php");
    exit;
}

// Fetch all data
$sql = "SELECT * FROM students_marks ORDER BY student_name";
$result = $conn->query($sql);

$students = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $students[$row['student_name']][] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Marks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            padding: 15px; 
            background-color: #f4f4f4; 
            line-height: 1.4;
        }
        
        h1, h2 { 
            color: #333; 
            text-align: center; 
            margin: 15px 0;
        }
        
        h1 {
            font-size: 1.8rem;
        }
        
        h2 {
            font-size: 1.4rem;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .grid-item {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 15px;
            text-align: center;
            min-width: 0; /* Allow shrinking */
        }
        
        .grid-item h3 {
            margin: 0 0 15px 0;
            font-size: 1.2rem;
            color: #333;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }
        
        th {
            background-color: #eaeaea;
            font-weight: bold;
        }
        
        tr.total-row {
            font-weight: bold;
            background: #d9f0d9;
        }
        
        form {
            background: #fff;
            padding: 20px;
            max-width: 400px;
            width: 100%;
            margin: 0 auto 30px auto;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="text"], 
        input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        input[type="submit"] {
            width: 100%;
            cursor: pointer;
            background: #eaeaea;
            color: #000;
            border: none;
            padding: 12px;
            border-radius: 5px;
            font-size: 1rem;
            transition: background 0.3s ease;
        }
        
        input[type="submit"]:hover {
            background:rgb(217, 217, 217);
        }

        /* Tablet styles */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            h2 {
                font-size: 1.2rem;
            }
            
            .grid-container {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 15px;
            }
            
            .grid-item {
                padding: 12px;
            }
            
            form {
                padding: 15px;
                margin: 0 auto 20px auto;
            }
            
            table {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 6px;
            }
        }

        /* Mobile styles */
        @media screen and (max-width: 480px) {
            body {
                padding: 5px;
            }
            
            h1 {
                font-size: 1.3rem;
                margin: 10px 0;
            }
            
            h2 {
                font-size: 1.1rem;
                margin: 15px 0 10px 0;
            }
            
            .grid-container {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .grid-item {
                padding: 10px;
                margin: 0 5px;
            }
            
            .grid-item h3 {
                font-size: 1.1rem;
                margin-bottom: 10px;
            }
            
            form {
                padding: 15px 10px;
                margin: 0 5px 15px 5px;
            }
            
            table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 4px 2px;
                font-size: 0.75rem;
            }
            
            input[type="text"], 
            input[type="number"] {
                padding: 8px;
                font-size: 0.9rem;
            }
            
            input[type="submit"] {
                padding: 10px;
                font-size: 0.9rem;
            }
        }

        /* Extra small mobile */
        @media screen and (max-width: 320px) {
            .grid-item {
                margin: 0;
            }
            
            form {
                margin: 0 0 15px 0;
            }
            
            table {
                font-size: 0.7rem;
            }
            
            th, td {
                padding: 3px 1px;
                font-size: 0.7rem;
            }
        }

        /* Landscape orientation adjustments */
        @media screen and (max-height: 500px) and (orientation: landscape) {
            h1, h2 {
                margin: 5px 0;
            }
            
            form {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <h1>Student Marks Management</h1>

    <h2>Add Student Marks</h2>
    <form method="POST">
        <label>Student Name:</label>
        <input type="text" name="student_name" required>
        
        <label>Subject:</label>
        <input type="text" name="subject" required>
        
        <label>Marks:</label>
        <input type="number" name="marks" required>
        
        <input type="submit" value="Add Mark">
    </form>

    <h2>Student Marks Table</h2>
    <div class="grid-container">
        <?php foreach ($students as $name => $records): ?>
            <div class="grid-item">
                <h3><?php echo htmlspecialchars($name); ?></h3>
                <table>
                    <tr>
                        <th>Subject</th>
                        <th>Marks</th>
                    </tr>
                    <?php 
                    $total = 0;
                    $count = 0;
                    foreach ($records as $record): 
                        $total += $record['marks'];
                        $count++;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['subject']); ?></td>
                        <td><?php echo $record['marks']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td>Total</td>
                        <td><?php echo $total; ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Average</td>
                        <td><?php echo round($total / $count, 2); ?></td>
                    </tr>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>