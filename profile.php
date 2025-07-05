<?php
// profile.php - Managed Profile View with Card Layout and Sidebar for Career Guide
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Job Connect - My Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Font Awesome for icons -->
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
    /* Header and Navigation */
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
    header h1 i {
      color: var(--accent);
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
      background: rgba(67, 97, 238, 0.05);
    }
    nav a:hover::before {
      transform: scaleX(1);
      transform-origin: left;
    }
    /* Profile Container and Cards */
    .profile-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 20px;
      margin: 2rem auto;
      padding: 0 2rem;
    }
    .card {
      background: white;
      padding: 20px;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      width: 100%;
      max-width: 400px;
    }
    .card:hover {
      transform: perspective(1000px) rotateX(2deg) rotateY(2deg);
      box-shadow: var(--card-hover-shadow);
    }
    .profile-info {
      text-align: center;
    }
    .profile-big {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 20px;
    }
    .profile-info h2 {
      color: var(--primary);
      margin: 0.5rem 0;
    }
    .profile-info p {
      margin: 0.3rem 0;
      font-size: 0.95rem;
      color: #555;
    }
    .profile-info a.edit-profile-btn {
      display: inline-block;
      margin-top: 1rem;
      background: linear-gradient(45deg, var(--primary), var(--secondary));
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .profile-info a.edit-profile-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--card-hover-shadow);
    }
    .profile-sidebar {
      text-align: center;
    }
    .profile-sidebar h3 {
      color: var(--primary);
      margin-bottom: 1rem;
    }
    .profile-sidebar p {
      margin-bottom: 1rem;
      font-size: 0.95rem;
      color: #555;
    }
    .profile-sidebar a.career-guide-btn {
      display: inline-block;
      background: linear-gradient(45deg, var(--primary), var(--secondary));
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .profile-sidebar a.career-guide-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--card-hover-shadow);
    }
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .header-container {
        flex-direction: column;
        padding: 1rem;
      }
      nav {
        margin-top: 1rem;
        flex-wrap: wrap;
        justify-content: center;
      }
      .profile-container {
        padding: 1rem;
      }
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
      <h1 data-aos="fade-right"><i class="fas fa-user-circle"></i> My Profile</h1>
      <nav data-aos="fade-left">
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a>
        <a href="career_guide.php"><i class="fas fa-lightbulb"></i> Career Guide</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
  </header>
  
  <div class="profile-container" data-aos="fade-up">
    <div class="card profile-info" data-aos="fade-up" data-aos-delay="100">
      <img src="<?php echo (!empty($user['profile_pic']) ? 'uploads/' . $user['profile_pic'] : 'assets/default.png'); ?>" alt="Profile Picture" class="profile-big">
      <h2><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h2>
      <p><strong><i class="fas fa-user"></i> Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
      <p><strong><i class="fas fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
      <p><strong><i class="fas fa-map-marker-alt"></i> Address:</strong> <?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></p>
      <p><strong><i class="fas fa-info-circle"></i> Bio:</strong> <?php echo htmlspecialchars($user['bio'] ?? ''); ?></p>
      <p><strong><i class="fab fa-linkedin"></i> LinkedIn:</strong> <?php echo htmlspecialchars($user['linkedin'] ?? ''); ?></p>
      <p><strong><i class="fab fa-github"></i> GitHub:</strong> <?php echo htmlspecialchars($user['github'] ?? ''); ?></p>
      <p><strong><i class="fas fa-briefcase"></i> Career Path:</strong> <?php echo htmlspecialchars($user['career_path'] ?? ''); ?></p>
      <a href="edit_profile.php" class="edit-profile-btn"><i class="fas fa-edit"></i> Edit Profile</a>
    </div>
    <div class="card profile-sidebar" data-aos="fade-up" data-aos-delay="200">
      <h3><i class="fas fa-lightbulb"></i> Career Guide</h3>
      <p>Get personalized career guidance based on your professional profiles and desired IT career path.</p>
      <a href="career_guide.php" class="career-guide-btn"><i class="fas fa-arrow-right"></i> Go to Career Guide</a>
    </div>
  </div>
  
  <!-- AOS Animation Library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
  <script>
    AOS.init({
      duration: 800,
      once: true
    });
  </script>
  
  <!-- Main JS (if any custom JS is needed) -->
  <script src="assets/js/main.js" defer></script>
</body>
</html>
