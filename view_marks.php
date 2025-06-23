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

// Initialize variables
$edit_mode = false;
$edit_id = null;
$edit_data = [];
$filter_subject = isset($_GET['subject']) ? $_GET['subject'] : '';
$success_message = '';
$error_message = '';
$form_errors = [];

// Handle delete operation
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM students_marks WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success_message = "Record deleted successfully!";
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF'] . ($filter_subject ? '?subject=' . urlencode($filter_subject) . '&success=deleted' : '?success=deleted') . '#records-section');
    exit;
}

// Handle edit - load data for editing
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM students_marks WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_data = $result->fetch_assoc();
        $edit_mode = true;
    }
    $stmt->close();
}

// Handle success messages from URL parameters
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = "New record added successfully!";
            break;
        case 'updated':
            $success_message = "Record updated successfully!";
            break;
        case 'deleted':
            $success_message = "Record deleted successfully!";
            break;
    }
}

// Form validation function
function validateForm($name, $subject, $marks, $conn, $edit_id = null) {
    $errors = [];
    
    // Validate student name
    if (empty(trim($name))) {
        $errors['student_name'] = "Student name is required.";
    } else {
        $name = trim($name);
        if (strlen($name) < 2) {
            $errors['student_name'] = "Student name must be at least 2 characters long.";
        } elseif (strlen($name) > 100) {
            $errors['student_name'] = "Student name must not exceed 100 characters.";
        } elseif (!preg_match("/^[a-zA-Z\s\.'-]+$/", $name)) {
            $errors['student_name'] = "Student name can only contain letters, spaces, dots, apostrophes, and hyphens.";
        }
    }
    
    // Validate subject
    if (empty(trim($subject))) {
        $errors['subject'] = "Subject is required.";
    } else {
        $subject = trim($subject);
        if (strlen($subject) < 2) {
            $errors['subject'] = "Subject must be at least 2 characters long.";
        } elseif (strlen($subject) > 50) {
            $errors['subject'] = "Subject must not exceed 50 characters.";
        } elseif (!preg_match("/^[a-zA-Z0-9\s\&\-\.]+$/", $subject)) {
            $errors['subject'] = "Subject can only contain letters, numbers, spaces, ampersands, hyphens, and dots.";
        }
    }
    
    // Validate marks
    if (!isset($marks) || $marks === '') {
        $errors['marks'] = "Marks is required.";
    } else {
        if (!is_numeric($marks)) {
            $errors['marks'] = "Marks must be a valid number.";
        } else {
            $marks = (int)$marks;
            if ($marks < 0) {
                $errors['marks'] = "Marks cannot be negative.";
            } elseif ($marks > 100) {
                $errors['marks'] = "Marks cannot exceed 100.";
            }
        }
    }
    
    // Check for duplicate entry (same student and subject)
    if (empty($errors)) {
        if ($edit_id) {
            // For updates, check if another record exists with same name and subject
            $stmt = $conn->prepare("SELECT id FROM students_marks WHERE student_name = ? AND subject = ? AND id != ?");
            $stmt->bind_param("ssi", $name, $subject, $edit_id);
        } else {
            // For new records, check if record already exists
            $stmt = $conn->prepare("SELECT id FROM students_marks WHERE student_name = ? AND subject = ?");
            $stmt->bind_param("ss", $name, $subject);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors['duplicate'] = "A record for this student and subject already exists.";
        }
        $stmt->close();
    }
    
    return $errors;
}

// Add or update student marks if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['student_name'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $marks = $_POST['marks'] ?? '';
    $edit_id_post = $_POST['edit_id'] ?? null;
    
    // Validate form data
    $form_errors = validateForm($name, $subject, $marks, $conn, $edit_id_post);
    
    if (empty($form_errors)) {
        // Sanitize inputs
        $name = trim($name);
        $subject = trim($subject);
        $marks = (int)$marks;
        
        if (!empty($edit_id_post)) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE students_marks SET student_name = ?, subject = ?, marks = ? WHERE id = ?");
            $stmt->bind_param("ssii", $name, $subject, $marks, $edit_id_post);
            $success_type = 'updated';
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO students_marks (student_name, subject, marks) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $name, $subject, $marks);
            $success_type = 'added';
        }
        
        if ($stmt->execute()) {
            $stmt->close();
            // Redirect to avoid form resubmission with success message
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . $success_type);
            exit;
        } else {
            $error_message = "Database error: " . $stmt->error;
            $stmt->close();
        }
    }
}

// Fetch all data for grid view
$sql = "SELECT * FROM students_marks ORDER BY student_name";
$result = $conn->query($sql);

$students = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $students[$row['student_name']][] = $row;
    }
}

// Fetch all unique subjects for filter buttons
$sql_subjects = "SELECT DISTINCT subject FROM students_marks ORDER BY subject";
$result_subjects = $conn->query($sql_subjects);
$subjects = [];
if ($result_subjects->num_rows > 0) {
    while ($row = $result_subjects->fetch_assoc()) {
        $subjects[] = $row['subject'];
    }
}

// Fetch filtered data for table view
if ($filter_subject) {
    $sql_all = "SELECT * FROM students_marks WHERE subject = ? ORDER BY student_name, id DESC";
    $stmt = $conn->prepare($sql_all);
    $stmt->bind_param("s", $filter_subject);
    $stmt->execute();
    $result_all = $stmt->get_result();
} else {
    $sql_all = "SELECT * FROM students_marks ORDER BY id DESC";
    $result_all = $conn->query($sql_all);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Marks Management</title>
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
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            margin: 20px auto;
            text-align: center;
            font-weight: bold;
            max-width: 600px;
            position: relative;
            animation: slideIn 0.5s ease-out;
        }
        
        .success-message::before {
            content: "✅ ";
            font-size: 1.2em;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            margin: 20px auto;
            text-align: center;
            font-weight: bold;
            max-width: 600px;
            position: relative;
            animation: slideIn 0.5s ease-out;
        }
        
        .error-message::before {
            content: "❌ ";
            font-size: 1.2em;
        }
        
        .field-error {
            color: #721c24;
            font-size: 0.9rem;
            margin-top: 5px;
            margin-bottom: 10px;
            padding: 5px;
            background-color: #f8d7da;
            border-radius: 3px;
            border: 1px solid #f5c6cb;
        }
        
        .field-error::before {
            content: "⚠️ ";
        }
        
        .input-error {
            border-color: #dc3545 !important;
            background-color: #fff5f5;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            min-width: 0;
        }
        
        .grid-item h3 {
            margin: 0 0 15px 0;
            font-size: 1.2rem;
            color: #333;
        }
        
        .table-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin: 20px 0;
            overflow-x: auto;
        }
        
        .filter-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            align-items: center;
            margin-top: 15px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            background-color: #e0e0e0;
            color: #333;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background-color: #d0d0d0;
            transform: translateY(-1px);
        }
        
        .filter-btn.active {
            background-color: #4CAF50;
            color: white;
            border-color: #45a049;
        }
        
        .filter-btn.clear {
            background-color: #f44336;
            color: white;
        }
        
        .filter-btn.clear:hover {
            background-color: #d32f2f;
        }
        
        .filter-info {
            margin: 15px 0;
            padding: 10px;
            background-color: #e8f5e8;
            border-radius: 5px;
            color: #2e7d32;
            font-weight: bold;
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
        
        .subject-clickable {
            cursor: pointer;
            color: #2196F3;
            text-decoration: underline;
            font-weight: bold;
        }
        
        .subject-clickable:hover {
            color: #1976D2;
            background-color: #e3f2fd;
        }
        
        tr.total-row {
            font-weight: bold;
            background: #d9f0d9;
        }
        
        .main-table th {
            background-color: #4CAF50;
            color: white;
        }
        
        .main-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .main-table tr:hover {
            background-color: #e8f5e8;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        .btn {
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #1976D2;
        }
        
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #d32f2f;
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
        
        .form-header {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
        }
        
        .form-add {
            background-color: #e8f5e8;
            color: #2e7d32;
        }
        
        .form-edit {
            background-color: #e3f2fd;
            color: #1565c0;
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
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            font-size: 1rem;
            transition: background 0.3s ease;
        }
        
        input[type="submit"]:hover {
            background: #45a049;
        }
        
        .cancel-btn {
            width: 100%;
            cursor: pointer;
            background: #f44336;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin-top: 10px;
            transition: background 0.3s ease;
        }
        
        .cancel-btn:hover {
            background: #d32f2f;
        }
        
        .section-divider {
            border-top: 3px solid #4CAF50;
            margin: 40px 0 20px 0;
        }

        /* Responsive styles */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .table-container, .filter-container {
                padding: 10px;
                overflow-x: auto;
            }
            
            .filter-buttons {
                gap: 5px;
            }
            
            .filter-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            table {
                font-size: 0.8rem;
                min-width: 600px;
            }
            
            th, td {
                padding: 6px 4px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 3px;
            }
            
            .btn {
                font-size: 0.7rem;
                padding: 4px 8px;
            }
        }

        @media screen and (max-width: 480px) {
            h1 {
                font-size: 1.3rem;
            }
            
            h2 {
                font-size: 1.1rem;
            }
            
            .table-container, .filter-container {
                padding: 5px;
            }
            
            table {
                font-size: 0.7rem;
                min-width: 500px;
            }
            
            th, td {
                padding: 4px 2px;
            }
        }
    </style>
    <script>
        function confirmDelete(studentName, subject) {
            return confirm('Are you sure you want to delete the record for ' + studentName + ' - ' + subject + '?');
        }
        
        // Auto-hide success message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.querySelector('.success-message');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.opacity = '0';
                    successMessage.style.transform = 'translateY(-20px)';
                    setTimeout(function() {
                        successMessage.remove();
                    }, 500);
                }, 5000);
            }
            
            // Auto-hide error message after 7 seconds
            const errorMessage = document.querySelector('.error-message');
            if (errorMessage) {
                setTimeout(function() {
                    errorMessage.style.opacity = '0';
                    errorMessage.style.transform = 'translateY(-20px)';
                    setTimeout(function() {
                        errorMessage.remove();
                    }, 500);
                }, 7000);
            }
        });
    </script>
</head>
<body>
    <h1>Student Marks Management System</h1>

    <?php if ($success_message): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <h2><?php echo $edit_mode ? 'Edit Student Marks' : 'Add Student Marks'; ?></h2>
    <form method="POST">
        <div class="form-header <?php echo $edit_mode ? 'form-edit' : 'form-add'; ?>">
            <strong><?php echo $edit_mode ? '📝 Edit Mode' : '➕ Add New Record'; ?></strong>
        </div>
        
        <?php if ($edit_mode): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_data['id']; ?>">
        <?php endif; ?>
        
        <label>Student Name: <span style="color: red;">*</span></label>
        <input type="text" 
               name="student_name" 
               value="<?php echo $edit_mode ? htmlspecialchars($edit_data['student_name']) : htmlspecialchars($_POST['student_name'] ?? ''); ?>" 
               class="<?php echo isset($form_errors['student_name']) ? 'input-error' : ''; ?>"
               placeholder="Enter student's full name"
               maxlength="100"
               required>
        <?php if (isset($form_errors['student_name'])): ?>
            <div class="field-error"><?php echo htmlspecialchars($form_errors['student_name']); ?></div>
        <?php endif; ?>
        
        <label>Subject: <span style="color: red;">*</span></label>
        <input type="text" 
               name="subject" 
               value="<?php echo $edit_mode ? htmlspecialchars($edit_data['subject']) : htmlspecialchars($_POST['subject'] ?? ''); ?>" 
               class="<?php echo isset($form_errors['subject']) ? 'input-error' : ''; ?>"
               placeholder="Enter subject name"
               maxlength="50"
               required>
        <?php if (isset($form_errors['subject'])): ?>
            <div class="field-error"><?php echo htmlspecialchars($form_errors['subject']); ?></div>
        <?php endif; ?>
        
        <label>Marks (0-100): <span style="color: red;">*</span></label>
        <input type="number" 
               name="marks" 
               value="<?php echo $edit_mode ? $edit_data['marks'] : htmlspecialchars($_POST['marks'] ?? ''); ?>" 
               class="<?php echo isset($form_errors['marks']) ? 'input-error' : ''; ?>"
               placeholder="Enter marks (0-100)"
               min="0" 
               max="100" 
               required>
        <?php if (isset($form_errors['marks'])): ?>
            <div class="field-error"><?php echo htmlspecialchars($form_errors['marks']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($form_errors['duplicate'])): ?>
            <div class="field-error"><?php echo htmlspecialchars($form_errors['duplicate']); ?></div>
        <?php endif; ?>
        
        <input type="submit" value="<?php echo $edit_mode ? 'Update Record' : 'Add Record'; ?>">
        
        <?php if ($edit_mode): ?>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="cancel-btn">Cancel Edit</a>
        <?php endif; ?>
    </form>

    <div class="section-divider"></div>

    <div id="records-section">
        <h2>📊 All Student Marks Records</h2>
        
        <?php if (!empty($subjects)): ?>
        <div class="filter-container">
            <h3>🔍 Filter by Subject</h3>
            <div class="filter-buttons">
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>#records-section" class="filter-btn <?php echo !$filter_subject ? 'active' : ''; ?>">
                    📋 All Subjects
                </a>
                <?php foreach ($subjects as $subject): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?subject=<?php echo urlencode($subject); ?>#records-section" 
                       class="filter-btn <?php echo $filter_subject === $subject ? 'active' : ''; ?>">
                        📚 <?php echo htmlspecialchars($subject); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if ($filter_subject): ?>
            <div class="filter-info">
                🎯 Showing results for: <strong><?php echo htmlspecialchars($filter_subject); ?></strong>
                <br>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>#records-section" style="color: #2e7d32; text-decoration: underline;">
                    ← Back to All Records
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="table-container">
            <?php if ($result_all->num_rows > 0): ?>
                <table class="main-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student Name</th>
                            <th>Subject</th>
                            <th>Marks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result_all->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                            <td>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?subject=<?php echo urlencode($row['subject']); ?>#records-section" 
                                   class="subject-clickable" 
                                   title="Click to filter by <?php echo htmlspecialchars($row['subject']); ?>">
                                    <?php echo htmlspecialchars($row['subject']); ?>
                                </a>
                            </td>
                            <td><?php echo $row['marks']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?edit=<?php echo $row['id']; ?><?php echo $filter_subject ? '&subject=' . urlencode($filter_subject) : ''; ?>" class="btn btn-edit">✏️ Edit</a>
                                    <a href="?delete=<?php echo $row['id']; ?><?php echo $filter_subject ? '&subject=' . urlencode($filter_subject) : ''; ?>" 
                                       class="btn btn-delete" 
                                       onclick="return confirmDelete('<?php echo htmlspecialchars($row['student_name']); ?>', '<?php echo htmlspecialchars($row['subject']); ?>')">
                                       🗑️ Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666; font-style: italic;">
                    <?php echo $filter_subject ? 'No records found for ' . htmlspecialchars($filter_subject) . '.' : 'No records found. Add some student marks to get started!'; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-divider"></div>

    <h2>📈 Student-wise Summary</h2>
    <div class="grid-container">
        <?php if (!empty($students)): ?>
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
                            <td>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?subject=<?php echo urlencode($record['subject']); ?>#records-section" 
                                   class="subject-clickable" 
                                   title="Click to filter by <?php echo htmlspecialchars($record['subject']); ?>">
                                    <?php echo htmlspecialchars($record['subject']); ?>
                                </a>
                            </td>
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
        <?php else: ?>
            <div class="grid-item">
                <p style="color: #666; font-style: italic;">No student data available yet.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>