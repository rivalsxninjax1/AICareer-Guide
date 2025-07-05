<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Process search query (if any)
$searchQuery = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = trim($_GET['search']);
    $stmt = $pdo->prepare("SELECT p.*, u.username, u.profile_pic, p.user_id 
                           FROM posts p 
                           JOIN users u ON p.user_id = u.id 
                           WHERE p.content LIKE ? OR p.company_category LIKE ? OR p.company_location LIKE ? 
                           ORDER BY p.created_at DESC");
    $likeQuery = "%" . $searchQuery . "%";
    $stmt->execute([$likeQuery, $likeQuery, $likeQuery]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query("SELECT p.*, u.username, u.profile_pic, p.user_id 
                         FROM posts p 
                         JOIN users u ON p.user_id = u.id 
                         ORDER BY p.created_at DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Job Connect - Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- AOS Animation Library -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" />
  <!-- Custom CSS -->
  <style>
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
    * {
      box-sizing: border-box;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #e4e7eb 100%);
      margin: 0;
      padding: 0;
      min-height: 100vh;
      color: var(--dark);
      overflow-x: hidden;
    }
    /* Floating Shapes Background */
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
      background: linear-gradient(45deg, rgba(67, 97, 238, 0.15), rgba(76, 201, 240, 0.15));
      border-radius: 50%;
      animation: float 15s linear infinite;
    }
    .shape:nth-child(1) {
      width: 100px;
      height: 100px;
      top: 10%;
      left: 5%;
      animation-duration: 45s;
    }
    .shape:nth-child(2) {
      width: 150px;
      height: 150px;
      top: 60%;
      left: 80%;
      animation-duration: 40s;
      animation-delay: 5s;
    }
    .shape:nth-child(3) {
      width: 80px;
      height: 80px;
      top: 70%;
      left: 20%;
      animation-duration: 35s;
      animation-delay: 7s;
    }
    .shape:nth-child(4) {
      width: 120px;
      height: 120px;
      top: 20%;
      left: 70%;
      animation-duration: 50s;
      animation-delay: 3s;
    }
    @keyframes float {
      0% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-100px) rotate(180deg); }
      100% { transform: translateY(0) rotate(360deg); }
    }
    header {
      background: rgba(255, 255, 255, 0.9);
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
    header h1:hover {
      transform: translateZ(10px) scale(1.05);
    }
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
      background: rgba(67, 97, 238, 0.05);
    }
    nav a:hover::before {
      transform: scaleX(1);
      transform-origin: left;
    }
    /* Search Bar */
    .search-bar {
      margin: 2rem auto;
      max-width: 600px;
      display: flex;
      border: 1px solid rgba(0,0,0,0.1);
      border-radius: 30px;
      overflow: hidden;
      box-shadow: var(--card-shadow);
      background: var(--light);
    }
    .search-bar input {
      flex: 1;
      padding: 12px 20px;
      border: none;
      font-size: 1em;
      background: var(--light);
    }
    .search-bar button {
      padding: 12px 20px;
      border: none;
      background: var(--primary);
      color: white;
      cursor: pointer;
      font-size: 1em;
      transition: background 0.3s ease;
    }
    .search-bar button:hover {
      background: var(--secondary);
    }
    /* Write Post Section */
    .write-post-container {
      max-width: 800px;
      margin: 1rem auto;
      padding: 0 2rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
    }
    .write-post-icon {
      background: var(--primary);
      color: white;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: var(--card-shadow);
      transition: transform 0.3s ease;
    }
    .write-post-icon:hover {
      transform: scale(1.1);
    }
    .write-post-label {
      font-weight: 500;
      color: var(--dark);
    }
    /* New Post Form */
    .new-post-form {
      max-width: 800px;
      margin: 1rem auto;
      padding: 1rem 2rem;
      background: white;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      transition: box-shadow 0.3s ease;
    }
    .new-post-form textarea,
    .new-post-form input {
      width: 100%;
      padding: 10px 15px;
      margin: 0.5rem 0;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      background-color: var(--light);
      font-family: 'Poppins', sans-serif;
      font-size: 1rem;
    }
    .new-post-form button {
      background: linear-gradient(45deg, var(--primary), var(--secondary));
      color: white;
      border: none;
      padding: 0.8rem 1.2rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 500;
      font-size: 1rem;
      margin-top: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .new-post-form button:hover {
      transform: translateY(-2px);
      box-shadow: var(--card-hover-shadow);
    }
    /* Feed and Post Cards */
    .feed {
      max-width: 800px;
      margin: 2rem auto;
      padding: 0 2rem;
      display: flex;
      flex-direction: column;
      gap: 2rem;
    }
    .post {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: var(--card-shadow);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      perspective: 1000px;
    }
    .post:hover {
      box-shadow: var(--card-hover-shadow);
    }
    .post-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .profile-pic {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--primary);
    }
    .post-content {
      margin-bottom: 1rem;
      line-height: 1.6;
      color: #4a4a4a;
    }
    .post-details p {
      margin: 0.3rem 0;
      font-size: 0.9rem;
      color: #555;
    }
    .post-actions {
      display: flex;
      gap: 1rem;
      margin-top: 1rem;
    }
    .post-actions button,
    .post-actions a {
      background: var(--primary);
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 0.3rem;
      transition: background 0.3s ease;
    }
    .post-actions button:hover,
    .post-actions a:hover {
      background: var(--secondary);
    }
    /* Comments */
    .comments {
      margin-top: 1rem;
      border-top: 1px solid #eee;
      padding-top: 1rem;
    }
    .comment-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
    }
    .comment-form {
      display: flex;
      gap: 0.5rem;
      margin-top: 0.5rem;
    }
    .comment-form input {
      flex: 1;
      padding: 0.5rem;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
    }
    .comment-form button {
      background: var(--primary);
      color: white;
      border: none;
      padding: 0.5rem 0.8rem;
      border-radius: 8px;
      transition: background 0.3s ease;
    }
    .comment-form button:hover {
      background: var(--secondary);
    }
    /* AI Chat Icon & Widget */
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
    }
    #aiChatWidget header {
      background: var(--primary);
      color: white;
      padding: 10px;
      cursor: move;
      border-top-left-radius: 10px;
      border-top-right-radius: 10px;
      display: flex;
      justify-content: space-between;
      align-items: center;
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
      <h1><i class="fas fa-briefcase"></i> Job Connect</h1>
      <nav>
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="messages.php"><i class="fas fa-comments"></i> Messages</a>
        <a href="career_guide.php"><i class="fas fa-lightbulb"></i> Career Guide</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
  </header>
  
  <!-- Modern Search Bar -->
  <div class="search-bar" data-aos="fade-up">
    <form action="index.php" method="GET" style="display: flex; width: 100%;">
      <input type="text" name="search" placeholder="Search jobs by keyword, category, or location..." value="<?php echo htmlspecialchars($searchQuery); ?>">
      <button type="submit"><i class="fas fa-search"></i></button>
    </form>
  </div>
  
  <!-- Write a Post Section -->
  <div class="write-post-container" data-aos="fade-up">
    <div class="write-post-icon" onclick="togglePostForm()">
      <i class="fas fa-plus"></i>
    </div>
    <div class="write-post-label">Write a Post</div>
  </div>
  
  <!-- New Post Form -->
  <div id="new-post-form" class="new-post-form" style="display: none;" data-aos="fade-up">
    <form action="new_post.php" method="POST">
      <textarea name="content" placeholder="What's on your mind?" required></textarea>
      <input type="text" name="company_category" placeholder="Company Category" required>
      <input type="text" name="company_name" placeholder="Company Name" required>
      <input type="text" name="company_location" placeholder="Company Location" required>
      <input type="text" name="phone_number" placeholder="Phone Number" required>
      <input type="text" name="role_needed" placeholder="Role Needed" required>
      <input type="text" name="experience" placeholder="Experience Required" required>
      <button type="submit"><i class="fas fa-paper-plane"></i> Post</button>
    </form>
  </div>
  
  <!-- Feed of Posts -->
  <div class="feed" data-aos="fade-up">
    <?php foreach($posts as $post): ?>
      <div class="post" data-aos="fade-up">
        <div class="post-header">
          <img src="<?php echo (!empty($post['profile_pic']) ? 'uploads/' . $post['profile_pic'] : 'assets/default.png'); ?>" alt="Profile Picture" class="profile-pic">
          <h3><?php echo htmlspecialchars($post['username']); ?></h3>
        </div>
        <div class="post-content">
          <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
        </div>
        <div class="post-details">
          <p><i class="fas fa-building"></i> <strong>Category:</strong> <?php echo htmlspecialchars($post['company_category']); ?></p>
          <p><i class="fas fa-industry"></i> <strong>Company:</strong> <?php echo htmlspecialchars($post['company_name']); ?></p>
          <p><i class="fas fa-map-marker-alt"></i> <strong>Location:</strong> <?php echo htmlspecialchars($post['company_location']); ?></p>
          <p><i class="fas fa-phone"></i> <strong>Phone:</strong> <?php echo htmlspecialchars($post['phone_number']); ?></p>
          <p><i class="fas fa-user-tie"></i> <strong>Role:</strong> <?php echo htmlspecialchars($post['role_needed']); ?></p>
          <p><i class="fas fa-briefcase"></i> <strong>Experience:</strong> <?php echo htmlspecialchars($post['experience']); ?> years</p>
        </div>
        <div class="post-actions">
          <button class="like-btn" data-post-id="<?php echo $post['id']; ?>"><i class="fas fa-thumbs-up"></i> Like</button>
          <button class="comment-btn" data-post-id="<?php echo $post['id']; ?>"><i class="fas fa-comment"></i> Comment</button>
          <a href="chat.php?receiver_id=<?php echo $post['user_id']; ?>" class="apply-btn"><i class="fas fa-briefcase"></i> Apply</a>
        </div>
        <!-- Comments Section -->
        <div class="comments" id="comments-<?php echo $post['id']; ?>">
          <?php 
              $stmtComments = $pdo->prepare("SELECT c.*, u.username, u.profile_pic FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC");
              $stmtComments->execute([$post['id']]);
              $comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);
              foreach($comments as $comment):
          ?>
            <div class="comment-item">
              <img src="<?php echo (!empty($comment['profile_pic']) ? 'uploads/' . $comment['profile_pic'] : 'assets/default.png'); ?>" alt="Profile Picture" class="profile-pic">
              <p><strong><?php echo htmlspecialchars($comment['username']); ?>:</strong> <?php echo htmlspecialchars($comment['comment']); ?></p>
            </div>
          <?php endforeach; ?>
        </div>
        
        <form action="comment.php" method="POST" class="comment-form">
          <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
          <input type="text" name="comment" placeholder="Write a comment..." required>
          <button type="submit"><i class="fas fa-paper-plane"></i></button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  
  <!-- Movable Chat with AI Career Guide Icon -->
  <div id="aiChatIcon" onclick="toggleChatWidget()">
    <i class="fas fa-robot"></i>
  </div>
  <!-- AI Chat Widget -->
  <div id="aiChatWidget">
    <header id="aiChatWidgetHeader">
      <span>AI Career Guide</span>
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
  <!-- Main JS -->
  <script src="assets/js/main.js" defer></script>
  <!-- Custom JS for 3D card effect and chat widget dragging -->
  <script>
    AOS.init({
      duration: 800,
      once: true
    });
    
    // 3D card effect for posts
    document.querySelectorAll('.post').forEach(card => {
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
    
    // Toggle Post Form
    function togglePostForm() {
      const form = document.getElementById('new-post-form');
      form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
    }
    
    // Toggle Chat Widget
    function toggleChatWidget() {
      const widget = document.getElementById('aiChatWidget');
      widget.style.display = (widget.style.display === 'none' || widget.style.display === '') ? 'block' : 'none';
    }
    
    // Make chat widget draggable
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
    
    // Function to send chat message to AI via Grow API
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
          {
            role: "system",
            content: "Provide a chat assistance with career guidance help"
          },
          {
            role: "user",
            content: message
          }
        ],
        temperature: 1,
        max_tokens: 1024
      };
      xhr.send(JSON.stringify(payload));
    }
  </script>
</body>
</html>
