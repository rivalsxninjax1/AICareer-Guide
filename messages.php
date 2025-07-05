<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$current_user = $_SESSION['user_id'];

// Get distinct conversation partner IDs using a UNION query
$sql = "SELECT DISTINCT CASE 
            WHEN sender_id = ? THEN receiver_id 
            ELSE sender_id 
        END AS partner_id
        FROM chats
        WHERE sender_id = ? OR receiver_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$current_user, $current_user, $current_user]);
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

$conversationList = [];
foreach ($partners as $partner) {
    $partnerId = $partner['partner_id'];
    $stmtUser = $pdo->prepare("SELECT id, username, profile_pic FROM users WHERE id = ?");
    $stmtUser->execute([$partnerId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $conversationList[] = $userData;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Messages - Job Connect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
      --card-shadow: 0 10px 20px rgba(0,0,0,0.1);
      --card-hover-shadow: 0 15px 30px rgba(0,0,0,0.15);
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
    /* Messages Container */
    .messages-container {
      max-width: 600px;
      margin: 20px auto;
      background: #fff;
      padding: 20px;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .messages-container:hover {
      transform: perspective(1000px) rotateX(2deg) rotateY(2deg);
      box-shadow: var(--card-hover-shadow);
    }
    .messages-container h2 {
      color: var(--primary);
      margin-bottom: 20px;
      text-align: center;
    }
    .conversation {
      padding: 10px;
      border-bottom: 1px solid #eee;
      display: flex;
      align-items: center;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    .conversation:hover {
      background: rgba(67,97,238,0.05);
    }
    .conversation:last-child { border-bottom: none; }
    .conversation img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 10px;
    }
    .conversation a {
      text-decoration: none;
      color: var(--dark);
      font-size: 1.2em;
      transition: color 0.3s ease;
    }
    .conversation a:hover {
      color: var(--primary);
    }
  </style>
</head>
<body>
  <!-- Floating Shapes Background -->
  <div class="floating-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
  </div>
  <header data-aos="fade-down">
    <div class="header-container">
      <h1 data-aos="fade-right"><i class="fas fa-comments"></i> Messages</h1>
      <nav data-aos="fade-left">
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
  </header>
  <div class="messages-container" data-aos="fade-up">
    <h2>Your Conversations</h2>
    <?php if (empty($conversationList)): ?>
      <p>No conversations yet.</p>
    <?php else: ?>
      <?php foreach ($conversationList as $conv): ?>
        <div class="conversation" data-aos="fade-up" data-aos-delay="100">
          <img src="<?php echo (!empty($conv['profile_pic']) ? 'uploads/' . $conv['profile_pic'] : 'assets/default.png'); ?>" alt="Profile Picture">
          <a href="chat.php?receiver_id=<?php echo $conv['id']; ?>">
            <?php echo htmlspecialchars($conv['username']); ?>
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <!-- AOS Animation Library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
  <script>
    AOS.init({
      duration: 800,
      once: true
    });
  </script>
</body>
</html>
