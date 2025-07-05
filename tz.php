<?php
// career_guide.php - Career Guidance page
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';
require_once 'grow_api.php';

// Fetch current user data (make sure your users table has an ai_response column)
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Retrieve stored AI response (if any)
$stored_response = $user['ai_response'] ?? "";

$response_text = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $linkedin    = trim($_POST['linkedin']);
    $github      = trim($_POST['github']);
    $career_path = trim($_POST['career_path']);

    // Generate a new AI response using the Grow API function
    $response_text = getCareerGuidance($linkedin, $github, $career_path);
    $_SESSION['ai_response'] = $response_text;

    // Update the stored AI response in the database
    $updateStmt = $pdo->prepare("UPDATE users SET ai_response = ? WHERE id = ?");
    $updateStmt->execute([$response_text, $user_id]);

    // Update tasks based on the new AI response
    updateUserTasks($pdo, $user_id, $response_text);

    // Refresh stored response variable
    $stored_response = $response_text;
}

// For new users: if a stored AI response exists but no tasks exist, create them.
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?");
$stmtCount->execute([$user_id]);
if ($stmtCount->fetchColumn() == 0 && !empty($stored_response)) {
    updateUserTasks($pdo, $user_id, $stored_response);
}

/**
 * Helper function that converts plain URLs in text into clickable links.
 */
function makeUrlsClickable($text) {
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return preg_replace('@(https?://[^\s]+)@', '<a href="$1" target="_blank">$1</a>', $escaped);
}
$response_text_html = makeUrlsClickable($stored_response);

/**
 * Parses the study plan portion from the AI response.
 * Expects the study plan to appear after "Study Plan:" with week headers.
 * Returns an associative array: keys are week labels and values are arrays of task descriptions.
 */
function parseStudyPlanWeeks($text) {
    $weeks = [];
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $currentWeek = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^Week\s+(.+):$/i', $line, $matches)) {
            $currentWeek = $matches[1];
            $weeks[$currentWeek] = [];
        } elseif (!empty($line) && $currentWeek !== null) {
            // Remove any leading hyphen, asterisk, or whitespace
            $clean = preg_replace('/^[-\*\s]+/', '', $line);
            if (!empty($clean)) {
                $weeks[$currentWeek][] = $clean;
            }
        }
    }
    return $weeks;
}

/**
 * Updates tasks for the user based on the AI response.
 * Deletes all previous tasks and inserts new tasks parsed from the study plan.
 * Due date for each task is set as today + (weekIndex - 1) * 7 days.
 */
function updateUserTasks($pdo, $user_id, $ai_response) {
    // Check if "Study Plan:" is present in the response
    if (stripos($ai_response, "Study Plan:") === false) {
        error_log("updateUserTasks: 'Study Plan:' not found for user $user_id");
        return;
    }
    // Split the response into two parts using "Study Plan:" as the delimiter
    $parts = explode("Study Plan:", $ai_response, 2);
    if (!isset($parts[1]) || empty(trim($parts[1]))) {
        error_log("updateUserTasks: Study plan text missing after delimiter for user $user_id. Full response: " . $ai_response);
        return;
    }
    $studyPlanText = trim($parts[1]);
    $weeks = parseStudyPlanWeeks($studyPlanText);
    if (empty($weeks)) {
        error_log("updateUserTasks: No weeks parsed for user $user_id. Response: " . $ai_response);
        return;
    }
    
    // Delete previous tasks for this user
    $delStmt = $pdo->prepare("DELETE FROM tasks WHERE user_id = ?");
    $delStmt->execute([$user_id]);
    
    // Use today's date as the base due date
    $baseDate = new DateTime();
    
    // Determine maximum numeric week (if any)
    $numericWeeks = [];
    foreach ($weeks as $key => $tasks) {
        if (is_numeric($key)) {
            $numericWeeks[] = intval($key);
        }
    }
    $maxWeek = !empty($numericWeeks) ? max($numericWeeks) : 0;
    
    // Prepare and execute the insertion of tasks
    $insertStmt = $pdo->prepare("INSERT INTO tasks (user_id, task_description, due_date, due_time, status) VALUES (?, ?, ?, ?, 'pending')");
    foreach ($weeks as $weekLabel => $tasks) {
        $weekIndex = is_numeric($weekLabel) ? intval($weekLabel) : ($maxWeek + 1);
        $dueDate = clone $baseDate;
        $daysToAdd = ($weekIndex - 1) * 7;
        $dueDate->modify("+{$daysToAdd} days");
        foreach ($tasks as $taskDescription) {
            $fullTask = "Week $weekLabel: " . $taskDescription;
            $insertStmt->execute([$user_id, $fullTask, $dueDate->format('Y-m-d'), '17:00']);
        }
    }
    error_log("updateUserTasks: Updated tasks for user $user_id");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Connect - Career Guide</title>
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- AOS Animation Library -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" />
  <style>
    /* Theme Variables */
    :root {
      --primary: #4361ee;
      --secondary: #3a0ca3;
      --accent: #4cc9f0;
      --light: #f8f9fa;
      --dark: #212529;
      --success: #4CAF50;
      --card-shadow: 0 10px 20px rgba(0,0,0,0.1);
      --card-hover-shadow: 0 15px 30px rgba(0,0,0,0.15);
    }
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
    /* Main Container */
    .career-container {
      max-width: 800px;
      margin: 2rem auto;
      padding: 0 2rem;
      display: flex;
      flex-direction: column;
      gap: 2rem;
      align-items: center;
      position: relative;
    }
    .card {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      box-shadow: var(--card-shadow);
      transition: box-shadow 0.3s ease;
      width: 100%;
      position: relative;
    }
    .card:hover { box-shadow: var(--card-hover-shadow); }
    .career-guide-form { display: flex; flex-direction: column; }
    .card h2 {
      color: var(--primary);
      margin-top: 0;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      font-size: 1.5rem;
    }
    form { display: flex; flex-direction: column; gap: 1rem; }
    label {
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.25rem;
    }
    /* Input Groups with Icon Alignment */
    .input-group {
      position: relative;
      transition: all 0.3s ease;
    }
    .input-group input,
    .input-group select {
      padding: 0.8rem 1rem 0.8rem 3rem;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-family: 'Poppins', sans-serif;
      font-size: 1rem;
      background-color: #f8f9fa;
      transition: all 0.3s ease;
    }
    .input-group input:focus,
    .input-group select:focus {
      border-color: var(--primary);
      outline: none;
      box-shadow: 0 0 0 3px rgba(67,97,238,0.15);
    }
    .input-group .icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #adb5bd;
      transition: all 0.3s ease;
    }
    .input-group:focus-within .icon { color: var(--primary); }
    /* Top-right Action Buttons */
    .top-actions {
      position: absolute;
      top: 1rem;
      right: 1rem;
      display: flex;
      gap: 0.5rem;
    }
    .top-actions button {
      padding: 0.5rem;
      font-size: 0.9rem;
      background: var(--success);
      border: none;
      color: #fff;
      border-radius: 6px;
      cursor: pointer;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .top-actions button:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(67,97,238,0.3);
    }
    /* Duplicate Action Buttons Below the Card */
    .action-buttons {
      display: flex;
      gap: 1rem;
      margin-top: 1rem;
    }
    .action-buttons button {
      background: var(--success);
      border: none;
      color: #fff;
      padding: 0.8rem 1.2rem;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1rem;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .action-buttons button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(67,97,238,0.3);
    }
    .career-response {
      background: linear-gradient(135deg, white, #f5f7fa);
      transition: box-shadow 0.3s ease;
    }
    .career-response:hover {
      box-shadow: var(--card-shadow);
    }
    .career-response p {
      line-height: 1.6;
      color: #4a4a4a;
    }
    /* Enhanced "Get Personalized Career Guidance" Button */
    button.tooltip {
      background: linear-gradient(45deg, var(--primary), var(--secondary));
      color: white;
      border: none;
      padding: 1rem 2rem;
      border-radius: 12px;
      cursor: pointer;
      font-weight: 600;
      font-size: 1.1rem;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 5px 15px rgba(67,97,238,0.3);
    }
    button.tooltip:hover {
      transform: translateY(-3px) scale(1.03);
      box-shadow: 0 8px 20px rgba(67,97,238,0.4);
    }
    /* Sticky Chatbot Widget (small robot icon toggles the chat window) */
    #aiChatIcon {
      position: fixed;
      right: 20px;
      bottom: 20px;
      width: 60px;
      height: 60px;
      background: var(--success);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      z-index: 1000;
      transition: transform 0.3s ease;
    }
    #aiChatIcon:hover {
      transform: scale(1.1);
    }
    #aiChatWidget {
      position: fixed;
      right: 100px;
      bottom: 20px;
      width: 350px;
      background: white;
      border: 1px solid #ccc;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      z-index: 1000;
      display: none;
      animation: fadeIn 1s ease;
    }
    #aiChatWidget header {
      background: var(--primary);
      color: white;
      padding: 10px;
      border-top-left-radius: 10px;
      border-top-right-radius: 10px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: move;
    }
    #aiChatWidget header .close-btn {
      cursor: pointer;
      font-size: 1.2em;
    }
    #aiChatWidget .chat-content {
      padding: 10px;
      max-height: 300px;
      overflow-y: auto;
    }
    #aiChatWidget .chat-input {
      display: flex;
      border-top: 1px solid #ccc;
    }
    #aiChatWidget .chat-input input {
      flex: 1;
      padding: 10px;
      border: none;
    }
    #aiChatWidget .chat-input button {
      border: none;
      background: var(--primary);
      color: white;
      padding: 10px 15px;
      cursor: pointer;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
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
      <h1 data-aos="fade-right"><i class="fas fa-lightbulb"></i> Career Guide</h1>
      <nav data-aos="fade-left">
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="career_guide.php" class="active"><i class="fas fa-lightbulb"></i> Career Guide</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
  </header>
  
  <div class="career-container">
    <div class="card career-guide-form" data-aos="fade-up" data-aos-delay="100">
      <?php if (!empty($stored_response)): ?>
      <!-- Top-right action buttons -->
      <div class="top-actions">
        <button onclick="window.location.href='download_pdf.php'" title="Download PDF">
          <i class="fas fa-file-pdf"></i>
        </button>
        <button onclick="window.location.href='task_manager.php'" title="Track Progress">
          <i class="fas fa-tasks"></i>
        </button>
      </div>
      <?php endif; ?>
      <h2><i class="fas fa-user-graduate"></i> Get Personalized Career Guidance</h2>
      <form action="career_guide.php" method="POST">
        <div class="input-group">
          <i class="fab fa-linkedin icon"></i>
          <label for="linkedin">LinkedIn Username:</label>
          <input type="text" id="linkedin" name="linkedin" value="<?php echo htmlspecialchars($user['linkedin'] ?? ''); ?>" required>
        </div>
        <div class="input-group">
          <i class="fab fa-github icon"></i>
          <label for="github">GitHub Username:</label>
          <input type="text" id="github" name="github" value="<?php echo htmlspecialchars($user['github'] ?? ''); ?>" required>
        </div>
        <div class="input-group">
          <i class="fas fa-briefcase icon"></i>
          <label for="career_path">Desired IT Career Path:</label>
          <select id="career_path" name="career_path" required>
            <option value="">--Select Career Path--</option>
            <option value="Software Developer">Software Developer</option>
            <option value="Data Scientist">Data Scientist</option>
            <option value="DevOps Engineer">DevOps Engineer</option>
            <option value="Cybersecurity Analyst">Cybersecurity Analyst</option>
            <option value="Cloud Architect">Cloud Architect</option>
            <option value="Machine Learning Engineer">Machine Learning Engineer</option>
            <option value="UI/UX Designer">UI/UX Designer</option>
            <option value="Systems Administrator">Systems Administrator</option>
            <option value="Database Administrator">Database Administrator</option>
            <option value="Network Engineer">Network Engineer</option>
            <option value="Product Manager">Product Manager</option>
            <option value="QA Engineer">QA Engineer</option>
            <option value="Business Intelligence Analyst">Business Intelligence Analyst</option>
            <option value="IT Project Manager">IT Project Manager</option>
            <option value="Full Stack Developer">Full Stack Developer</option>
            <option value="Mobile App Developer">Mobile App Developer</option>
            <option value="Game Developer">Game Developer</option>
            <option value="Blockchain Developer">Blockchain Developer</option>
            <option value="Technical Support Specialist">Technical Support Specialist</option>
            <option value="IT Consultant">IT Consultant</option>
          </select>
        </div>
        <button type="submit" class="tooltip">
          <i class="fas fa-rocket"></i> Get Personalized Career Guidance
          <span class="tooltiptext">Click to receive personalized guidance based on your profiles</span>
        </button>
      </form>
    </div>
    
    <?php if (!empty($stored_response)): ?>
    <div class="card career-response" data-aos="fade-up" data-aos-delay="200">
      <h2><i class="fas fa-chart-line"></i> Your Personalized Career Guidance</h2>
      <p><?php echo nl2br($response_text_html); ?></p>
    </div>
    <!-- Duplicate action buttons below the AI response -->
    <div class="action-buttons" data-aos="fade-up" data-aos-delay="300">
      <button onclick="window.location.href='download_pdf.php'">
        <i class="fas fa-file-pdf"></i> Download PDF
      </button>
      <button onclick="window.location.href='task_manager.php'" title="Track Progress">
        <i class="fas fa-tasks"></i> Track Progress
      </button>
    </div>
    <?php endif; ?>
  </div>
  
  <!-- Chatbot Icon (small robot icon as on index.php) -->
  <div id="aiChatIcon" onclick="toggleChatWidget()">
    <i class="fas fa-robot"></i>
  </div>
  
  <!-- Sticky Chatbot Widget -->
  <div id="aiChatWidget">
    <header id="aiChatWidgetHeader">
      <span>General Counselor</span>
      <span class="close-btn" onclick="toggleChatWidget()">&times;</span>
    </header>
    <div class="chat-content" id="chatContent">
      <!-- Chat messages will appear here -->
    </div>
    <div class="chat-input">
      <input type="text" id="chatMessage" placeholder="Type your message...">
      <button onclick="sendChatMessage()"><i class="fas fa-paper-plane"></i></button>
    </div>
  </div>
  
  <!-- AOS Animation Library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
  <script>
    AOS.init({ duration: 800, once: true });
    
    // 3D hover effect on cards (except the AI response card)
    document.querySelectorAll('.card:not(.career-response)').forEach(card => {
      card.addEventListener('mousemove', e => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateY = ((x - centerX) / centerX) * 3;
        const rotateX = ((centerY - y) / centerY) * 3;
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
      });
      card.addEventListener('mouseleave', () => {
        card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
      });
    });
    
    // Toggle Chat Widget Visibility
    function toggleChatWidget() {
      const widget = document.getElementById('aiChatWidget');
      widget.style.display = (widget.style.display === 'none' || widget.style.display === '') ? 'block' : 'none';
    }
    
    // Make the chat widget draggable
    dragElement(document.getElementById("aiChatWidget"));
    function dragElement(elmnt) {
      var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
      if (document.getElementById(elmnt.id + "Header")) {
        document.getElementById(elmnt.id + "Header").onmousedown = dragMouseDown;
      } else {
        elmnt.onmousedown = dragMouseDown;
      }
      function dragMouseDown(e) {
        e = e || window.event;
        e.preventDefault();
        pos3 = e.clientX;
        pos4 = e.clientY;
        document.onmouseup = closeDragElement;
        document.onmousemove = elementDrag;
      }
      function elementDrag(e) {
        e = e || window.event;
        e.preventDefault();
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        pos3 = e.clientX;
        pos4 = e.clientY;
        elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
        elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
      }
      function closeDragElement() {
        document.onmouseup = null;
        document.onmousemove = null;
      }
    }
    
    // Send Chat Message using the General Counselor API
    function sendChatMessage() {
      var messageInput = document.getElementById('chatMessage');
      var message = messageInput.value.trim();
      if (message === "") return;
      var chatContent = document.getElementById('chatContent');
      var userMessage = document.createElement('div');
      userMessage.className = 'chat-message user-message';
      userMessage.innerHTML = '<strong>You:</strong> ' + message;
      chatContent.appendChild(userMessage);
      chatContent.scrollTop = chatContent.scrollHeight;
      messageInput.value = "";
      
      var xhr = new XMLHttpRequest();
      xhr.open("POST", "grow_api.php", true);
      xhr.setRequestHeader("Content-Type", "application/json");
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
          if (xhr.status === 200) {
            try {
              var resp = JSON.parse(xhr.responseText);
              var aiResponse = document.createElement('div');
              aiResponse.className = 'chat-message ai-message';
              aiResponse.innerHTML = '<strong>AI:</strong> ' + resp.response;
              chatContent.appendChild(aiResponse);
              chatContent.scrollTop = chatContent.scrollHeight;
            } catch (err) {
              var aiResponse = document.createElement('div');
              aiResponse.className = 'chat-message ai-message';
              aiResponse.innerHTML = '<strong>AI:</strong> Error parsing response.';
              chatContent.appendChild(aiResponse);
            }
          } else {
            var aiResponse = document.createElement('div');
            aiResponse.className = 'chat-message ai-message';
            aiResponse.innerHTML = '<strong>AI:</strong> Error: ' + xhr.status;
            chatContent.appendChild(aiResponse);
          }
        }
      };
      var payload = {
        model: "llama3-70b-8192",
        messages: [
          { role: "system", content: "Provide career guidance as a general counselor" },
          { role: "user", content: message }
        ],
        temperature: 1,
        max_tokens: 1024
      };
      xhr.send(JSON.stringify(payload));
    }
  </script>
</body>
</html>
