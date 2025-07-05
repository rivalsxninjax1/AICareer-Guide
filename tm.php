<?php
// task_manager.php - Displays and manages tasks for the logged-in user
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Error handling function
function handleError($message, $redirect = true) {
    $_SESSION['error_message'] = $message;
    if ($redirect) {
        header("Location: task_manager.php");
        exit;
    }
}

// Success message function
function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

try {
    require_once 'db.php';

    $user_id = $_SESSION['user_id'];

    // Handle manual task addition
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_task'])) {
        $new_task = trim($_POST['new_task']);
        $due_date = trim($_POST['due_date'] ?? '');
        $due_time = trim($_POST['due_time'] ?? '');
        
        // Validate inputs
        if (empty($new_task)) {
            handleError("Task description cannot be empty");
        }
        
        if (empty($due_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
            handleError("Invalid due date format");
        }
        
        if (empty($due_time) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $due_time)) {
            handleError("Invalid due time format");
        }
        
        try {
            $stmtAdd = $pdo->prepare("INSERT INTO tasks (user_id, task_description, due_date, due_time, status) VALUES (?, ?, ?, ?, 'pending')");
            $result = $stmtAdd->execute([$user_id, $new_task, $due_date, $due_time]);
            
            if (!$result) {
                handleError("Failed to add task");
            }
            
            setSuccessMessage("Task added successfully");
            header("Location: task_manager.php");
            exit;
        } catch (PDOException $e) {
            handleError("Database error: " . $e->getMessage());
        }
    }

    // Handle task deletion
    if (isset($_GET['delete_task'])) {
        $task_id = filter_input(INPUT_GET, 'delete_task', FILTER_VALIDATE_INT);
        
        if (!$task_id) {
            handleError("Invalid task ID");
        }
        
        try {
            $stmtDel = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
            $result = $stmtDel->execute([$task_id, $user_id]);
            
            if (!$result) {
                handleError("Failed to delete task");
            }
            
            setSuccessMessage("Task deleted successfully");
            header("Location: task_manager.php");
            exit;
        } catch (PDOException $e) {
            handleError("Database error: " . $e->getMessage());
        }
    }

    // Handle marking a task as complete
    if (isset($_GET['complete_task'])) {
        $task_id = filter_input(INPUT_GET, 'complete_task', FILTER_VALIDATE_INT);
        
        if (!$task_id) {
            handleError("Invalid task ID");
        }
        
        try {
            $stmtComp = $pdo->prepare("UPDATE tasks SET status = 'completed', updated_at = NOW() WHERE id = ? AND user_id = ?");
            $result = $stmtComp->execute([$task_id, $user_id]);
            
            if (!$result) {
                handleError("Failed to mark task as complete");
            }
            
            setSuccessMessage("Task marked as complete");
            header("Location: task_manager.php");
            exit;
        } catch (PDOException $e) {
            handleError("Database error: " . $e->getMessage());
        }
    }

    // Fetch tasks for this user ordered by due_date and due_time
    try {
        $stmtTasks = $pdo->prepare("SELECT *, DATE(created_at) AS created_date FROM tasks WHERE user_id = ? ORDER BY due_date ASC, due_time ASC");
        $stmtTasks->execute([$user_id]);
        $allTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $allTasks = [];
        handleError("Error fetching tasks: " . $e->getMessage(), false);
    }

    // Separate tasks into active and completed
    $completedTasks = [];
    $activeTasks = [];
    $current_date = date('Y-m-d');
    
    foreach ($allTasks as $task) {
        if ($task['status'] === 'completed') {
            $completedTasks[] = $task;
        } else {
            $activeTasks[] = $task;
        }
    }

    // Divide active tasks into tasks due today and future tasks
    $todayTasks = [];
    $futureTasks = [];
    $overdueTasks = [];
    
    foreach ($activeTasks as $task) {
        if ($task['due_date'] == $current_date) {
            $todayTasks[] = $task;
        } elseif ($task['due_date'] > $current_date) {
            $futureTasks[] = $task;
        } else {
            $overdueTasks[] = $task;
        }
    }

} catch (Exception $e) {
    handleError("An unexpected error occurred: " . $e->getMessage(), false);
}

/**
 * Calculates the progress percentage (time remaining) for an active task.
 */
function calculateProgress($created_at, $due_date, $due_time) {
    try {
        $start = strtotime($created_at);
        $end = strtotime($due_date . ' ' . $due_time);
        $now = time();
        
        if (!$start || !$end) {
            return 0; // Invalid date/time format
        }
        
        $total = $end - $start;
        $elapsed = $now - $start;
        
        if ($total <= 0) return 0;
        $percentage = 100 - ($elapsed / $total * 100);
        return max(0, min(100, round($percentage)));
    } catch (Exception $e) {
        error_log("Error calculating progress: " . $e->getMessage());
        return 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Track Progress - Task Manager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- AOS Animation Library -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" />
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #4361ee;
      --secondary: #3a0ca3;
      --accent: #4cc9f0;
      --light: #f8f9fa;
      --dark: #212529;
      --card-shadow: 0 10px 20px rgba(0,0,0,0.1);
      --card-hover-shadow: 0 15px 30px rgba(0,0,0,0.15);
      --danger: #dc3545;
      --success: #28a745;
      --warning: #ffc107;
    }
    * { box-sizing: border-box; }
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f5f7fa, #e4e7eb);
      margin: 0;
      padding: 0;
      min-height: 100vh;
      color: var(--dark);
      overflow-x: hidden;
    }
    /* Floating Shapes */
    .floating-shapes {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -1;
      overflow: hidden;
      pointer-events: none;
    }
    .shape {
      position: absolute;
      background: linear-gradient(45deg, rgba(67,97,238,0.15), rgba(76,201,240,0.15));
      border-radius: 50%;
      animation: float 15s linear infinite;
    }
    .shape:nth-child(1) { width: 100px; height: 100px; top: 10%; left: 5%; animation-duration: 45s; }
    .shape:nth-child(2) { width: 150px; height: 150px; top: 60%; left: 80%; animation-duration: 40s; animation-delay: 5s; }
    .shape:nth-child(3) { width: 80px; height: 80px; top: 70%; left: 20%; animation-duration: 35s; animation-delay: 7s; }
    .shape:nth-child(4) { width: 120px; height: 120px; top: 20%; left: 70%; animation-duration: 50s; animation-delay: 3s; }
    @keyframes float {
      0% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-100px) rotate(180deg); }
      100% { transform: translateY(0) rotate(360deg); }
    }
    /* Header and Navigation */
    header {
      background: rgba(255,255,255,0.9);
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 6px rgba(0,0,0,0.05);
      position: sticky;
      top: 0;
      z-index: 1000;
      transition: all 0.3s ease;
    }
    .header-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    header h1 {
      margin: 0;
      font-weight: 600;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: transform 0.3s ease;
    }
    header h1:hover { transform: translateZ(10px) scale(1.05); }
    nav {
      display: flex;
      gap: 1.5rem;
    }
    nav a {
      text-decoration: none;
      color: var(--dark);
      font-weight: 500;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    nav a::before {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 2px;
      background-color: var(--primary);
      transform: scaleX(0);
      transform-origin: right;
      transition: transform 0.3s ease;
    }
    nav a:hover {
      color: var(--primary);
      background: rgba(67,97,238,0.05);
    }
    nav a:hover::before {
      transform: scaleX(1);
      transform-origin: left;
    }
    /* Task Manager Container */
    .task-container {
      max-width: 800px;
      margin: 2rem auto;
      padding: 20px;
      background: #fff;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .task-container:hover {
      transform: perspective(1000px) rotateX(2deg) rotateY(2deg);
      box-shadow: var(--card-hover-shadow);
    }
    /* Alert Messages */
    .alert {
      padding: 1rem;
      margin-bottom: 1rem;
      border-radius: 8px;
      font-weight: 500;
    }
    .alert-success {
      background-color: rgba(40, 167, 69, 0.1);
      border-left: 4px solid var(--success);
      color: var(--success);
    }
    .alert-danger {
      background-color: rgba(220, 53, 69, 0.1);
      border-left: 4px solid var(--danger);
      color: var(--danger);
    }
    .alert-warning {
      background-color: rgba(255, 193, 7, 0.1);
      border-left: 4px solid var(--warning);
      color: #856404;
    }
    /* New Task Form */
    .new-task-form {
      margin-bottom: 2rem;
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .new-task-form input[type="text"],
    .new-task-form input[type="date"],
    .new-task-form input[type="time"] {
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
      flex: 1;
      font-size: 1em;
    }
    .new-task-form button {
      background: var(--primary);
      border: none;
      color: #fff;
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1em;
      transition: background 0.3s ease;
    }
    .new-task-form button:hover { background: var(--secondary); }
    /* Sections */
    .section { margin-bottom: 2rem; }
    .section h3 {
      border-bottom: 2px solid var(--primary);
      padding-bottom: 5px;
      margin-bottom: 1rem;
      color: var(--primary);
    }
    .task-list { list-style: none; padding: 0; }
    .task-list li {
      padding: 10px;
      border-bottom: 1px solid #eee;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .task-list li:last-child { border-bottom: none; }
    .task-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .task-header .task-info { font-weight: 500; }
    .task-header .task-actions {
      display: flex;
      gap: 5px;
    }
    .task-header .task-actions button {
      background: var(--secondary);
      border: none;
      color: #fff;
      padding: 5px 10px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 0.9em;
      transition: background 0.3s ease;
    }
    .task-header .task-actions button:hover { background: var(--primary); }
    /* Progress Bar */
    .progress-bar-container {
      background: #eee;
      border-radius: 5px;
      overflow: hidden;
      height: 10px;
    }
    .progress-bar {
      height: 10px;
      background: var(--accent);
      width: 0%;
      transition: width 0.5s ease;
    }
    /* Overdue task style */
    .overdue .task-info strong {
      color: var(--danger);
    }
    .overdue .progress-bar {
      background: var(--danger);
    }
  </style>
</head>
<body>
  <!-- Floating shapes background -->
  <div class="floating-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
  </div>
  <header data-aos="fade-down">
    <div class="header-container">
      <h1 data-aos="fade-right"><i class="fas fa-tasks"></i> Task Manager</h1>
      <nav data-aos="fade-left">
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="career_guide.php"><i class="fas fa-lightbulb"></i> Career Guide</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
  </header>
  <div class="task-container" data-aos="fade-up">
    <!-- Alert Messages -->
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <!-- New Task Form -->
    <form action="task_manager.php" method="POST" class="new-task-form" data-aos="fade-up" data-aos-delay="100" onsubmit="return validateForm()">
      <input type="text" name="new_task" id="new_task" placeholder="New task description" required>
      <input type="date" name="due_date" id="due_date" required>
      <input type="time" name="due_time" id="due_time" required>
      <button type="submit"><i class="fas fa-plus"></i> Add Task</button>
    </form>
    
    <?php if (empty($allTasks)): ?>
      <p>No tasks available. Please generate your career guidance for auto-creation of tasks, or add tasks manually.</p>
    <?php else: ?>
      <!-- Overdue Tasks Section -->
      <?php if (!empty($overdueTasks)): ?>
      <div class="section" data-aos="fade-up" data-aos-delay="150">
        <h3>Overdue Tasks</h3>
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle"></i> You have <?php echo count($overdueTasks); ?> overdue tasks.
        </div>
        <ul class="task-list">
          <?php foreach ($overdueTasks as $task): 
            $progress = calculateProgress($task['created_at'], $task['due_date'], $task['due_time']);
          ?>
          <li class="overdue">
            <div class="task-header">
              <div class="task-info">
                <strong><?php echo htmlspecialchars($task['task_description']); ?></strong>
                <small>Due: <?php echo htmlspecialchars($task['due_date'] . ' ' . $task['due_time']); ?> (Overdue)</small>
              </div>
              <div class="task-actions">
                <button onclick="confirmAction('complete', <?php echo $task['id']; ?>)">
                  <i class="fas fa-check"></i>
                </button>
                <button onclick="window.location.href='extend_task.php?task_id=<?php echo $task['id']; ?>'">
                  <i class="fas fa-calendar-plus"></i>
                </button>
                <button onclick="confirmAction('delete', <?php echo $task['id']; ?>)">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
            <div class="progress-bar-container">
              <div class="progress-bar" style="width: 0%;"></div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      
      <!-- Completed Tasks Section -->
      <?php if (!empty($completedTasks)): ?>
      <div class="section" data-aos="fade-up" data-aos-delay="200">
        <h3>Completed Tasks</h3>
        <ul class="task-list">
          <?php foreach ($completedTasks as $task): ?>
          <li>
            <div class="task-header">
              <div class="task-info">
                <strong><?php echo htmlspecialchars($task['task_description']); ?></strong>
                <small>Completed on: <?php echo date('Y-m-d', strtotime($task['updated_at'] ?? $task['created_at'])); ?></small>
              </div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      
      <!-- Active Tasks (Due Today) Section -->
      <div class="section" data-aos="fade-up" data-aos-delay="300">
        <h3>Active Tasks (Due Today)</h3>
        <?php if (empty($todayTasks)): ?>
          <p>No tasks due today.</p>
        <?php else: ?>
        <ul class="task-list">
          <?php foreach ($todayTasks as $task):
            $progress = calculateProgress($task['created_at'], $task['due_date'], $task['due_time']);
          ?>
          <li>
            <div class="task-header">
              <div class="task-info">
                <strong><?php echo htmlspecialchars($task['task_description']); ?></strong>
                <small>Due: <?php echo htmlspecialchars($task['due_date'] . ' ' . $task['due_time']); ?></small>
              </div>
              <div class="task-actions">
                <button onclick="confirmAction('complete', <?php echo $task['id']; ?>)">
                  <i class="fas fa-check"></i>
                </button>
                <button onclick="window.location.href='extend_task.php?task_id=<?php echo $task['id']; ?>'">
                  <i class="fas fa-calendar-plus"></i>
                </button>
                <button onclick="confirmAction('delete', <?php echo $task['id']; ?>)">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
            <div class="progress-bar-container">
              <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      
      <!-- Future Tasks Section -->
      <div class="section" data-aos="fade-up" data-aos-delay="400">
        <h3>Future Tasks</h3>
        <?php if (empty($futureTasks)): ?>
          <p>No future tasks scheduled.</p>
        <?php else: ?>
        <ul class="task-list">
          <?php foreach ($futureTasks as $task):
            $progress = calculateProgress($task['created_at'], $task['due_date'], $task['due_time']);
          ?>
          <li>
            <div class="task-header">
              <div class="task-info">
                <strong><?php echo htmlspecialchars($task['task_description']); ?></strong>
                <small>Due: <?php echo htmlspecialchars($task['due_date'] . ' ' . $task['due_time']); ?></small>
              </div>
              <div class="task-actions">
                <button onclick="confirmAction('complete', <?php echo $task['id']; ?>)">
                  <i class="fas fa-check"></i>
                </button>
                <button onclick="window.location.href='extend_task.php?task_id=<?php echo $task['id']; ?>'">
                  <i class="fas fa-calendar-plus"></i>
                </button>
                <button onclick="confirmAction('delete', <?php echo $task['id']; ?>)">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
            <div class="progress-bar-container">
              <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  
  <!-- JavaScript Libraries -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
  <script>
    // Initialize AOS animation library
    document.addEventListener('DOMContentLoaded', function() {
      AOS.init({ duration: 800, once: true });
      
      // Auto-hide alerts after 5 seconds
      setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
          alert.style.opacity = '0';
          alert.style.transition = 'opacity 0.5s ease';
          setTimeout(function() {
            alert.style.display = 'none';
          }, 500);
        });
      }, 5000);
      
      // Initialize progress bars
      document.querySelectorAll('.progress-bar').forEach(function(bar) {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(function() {
          bar.style.width = width;
        }, 300);
      });
    });
    
    // Form validation
    function validateForm() {
      const taskDesc = document.getElementById('new_task').value.trim();
      const dueDate = document.getElementById('due_date').value;
      const dueTime = document.getElementById('due_time').value;
      
      if (taskDesc === '') {
        showError('Task description cannot be empty');
        return false;
      }
      
      if (dueDate === '') {
        showError('Please select a due date');
        return false;
      }
      
      if (dueTime === '') {
        showError('Please select a due time');
        return false;
      }
      
      // Check if due date is in the past
      const now = new Date();
      const taskDate = new Date(dueDate + 'T' + dueTime);
      
      if (taskDate < now) {
        if (!confirm('The due date and time you selected is in the past. Do you want to continue anyway?')) {
          return false;
        }
      }
      
      return true;
    }
    
    // Show error message
    function showError(message) {
      // Create error alert
      const errorAlert = document.createElement('div');
      errorAlert.className = 'alert alert-danger';
      errorAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
      
      // Insert at the top of the task container
      const container = document.querySelector('.task-container');
      container.insertBefore(errorAlert, container.firstChild);
      
      // Auto remove after 5 seconds
      setTimeout(function() {
        errorAlert.style.opacity = '0';
        errorAlert.style.transition = 'opacity 0.5s ease';
        setTimeout(function() {
          errorAlert.remove();
        }, 500);
      }, 5000);
    }
    
    // Confirm action
    function confirmAction(action, taskId) {
      let message = '';
      let url = '';
      
      if (action === 'delete') {
        message = 'Are you sure you want to delete this task?';
        url = 'task_manager.php?delete_task=' + taskId;
      } else if (action === 'complete') {
        message = 'Mark this task as complete?';
        url = 'task_manager.php?complete_task=' + taskId;
      }
      
      if (confirm(message)) {
        window.location.href = url;
      }
    }
    
    // Optional 3D hover effect on the task container
    document.querySelectorAll('.task-container').forEach(container => {
      container.addEventListener('mousemove', e => {
        const rect = container.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateY = ((x - centerX) / centerX) * 2;
        const rotateX = ((centerY - y) / centerY) * 2;
        container.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
      });
      container.addEventListener('mouseleave', () => {
        container.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
      });
    });
  </script>
</body>
</html>