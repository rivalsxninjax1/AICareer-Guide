<?php
// edit_profile.php - Allows user to edit profile info and upload profile picture
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name   = trim($_POST['full_name']);
    $address     = trim($_POST['address']);
    $bio         = trim($_POST['bio']);
    $linkedin    = trim($_POST['linkedin']);
    $github      = trim($_POST['github']);
    $career_path = trim($_POST['career_path']);
    
    // Handle file upload if provided
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed  = ['jpg', 'jpeg', 'png', 'gif'];
        $fileName = $_FILES['profile_pic']['name'];
        $fileTmp  = $_FILES['profile_pic']['tmp_name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowed)) {
            $errors[] = "Invalid file type for profile picture. Allowed types: " . implode(", ", $allowed);
        } else {
            $newFileName = uniqid() . '.' . $fileExt;
            $uploadPath  = "uploads/" . $newFileName;
            if (!move_uploaded_file($fileTmp, $uploadPath)) {
                $errors[] = "Failed to upload profile picture.";
            } else {
                // Set file permissions for Mac (read & write for all)
                chmod($uploadPath, 0777);
            }
        }
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($errors)) {
        if (isset($newFileName)) {
            $stmtUpdate = $pdo->prepare("UPDATE users SET full_name = ?, address = ?, bio = ?, linkedin = ?, github = ?, career_path = ?, profile_pic = ? WHERE id = ?");
            $updated = $stmtUpdate->execute([$full_name, $address, $bio, $linkedin, $github, $career_path, $newFileName, $user_id]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE users SET full_name = ?, address = ?, bio = ?, linkedin = ?, github = ?, career_path = ? WHERE id = ?");
            $updated = $stmtUpdate->execute([$full_name, $address, $bio, $linkedin, $github, $career_path, $user_id]);
        }
        
        if ($updated) {
            header("Location: profile.php");
            exit;
        } else {
            $errors[] = "Profile update failed.";
        }
    }
}

// Fetch updated user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Connect - Edit Profile</title>
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
        }
        * { box-sizing: border-box; }
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
        /* Edit Profile Container */
        .edit-profile-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .edit-profile-container:hover {
            transform: perspective(1000px) rotateX(2deg) rotateY(2deg);
            box-shadow: var(--card-hover-shadow);
        }
        .edit-profile-container form label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        .edit-profile-container form input[type="text"],
        .edit-profile-container form textarea,
        .edit-profile-container form select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1em;
        }
        .edit-profile-container form button {
            padding: 10px 20px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.3s ease;
        }
        .edit-profile-container form button:hover {
            background: var(--secondary);
        }
        .success, .error {
            max-width: 600px;
            margin: 10px auto;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.95em;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .profile-big {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 20px auto;
            display: block;
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
            <h1 data-aos="fade-right">Edit Profile</h1>
            <nav data-aos="fade-left">
                <a href="index.php">Home</a>
                <a href="profile.php">My Profile</a>
                <a href="career_guide.php">Career Guide</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    <?php if (!empty($errors)): ?>
        <div class="error" data-aos="fade-up">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success" data-aos="fade-up">
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
    <?php endif; ?>
    <div class="edit-profile-container" data-aos="fade-up">
        <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
            <label>Full Name:</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
            
            <label>Address:</label>
            <input type="text" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
            
            <label>Bio:</label>
            <textarea name="bio" required><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            
            <label>LinkedIn Username:</label>
            <input type="text" name="linkedin" value="<?php echo htmlspecialchars($user['linkedin'] ?? ''); ?>">
            
            <label>GitHub Username:</label>
            <input type="text" name="github" value="<?php echo htmlspecialchars($user['github'] ?? ''); ?>">
            
            <label>Desired IT Career Path:</label>
            <select name="career_path" required>
                <option value="">--Select Career Path--</option>
                <option value="Software Developer" <?php if(($user['career_path'] ?? '') == 'Software Developer') echo 'selected'; ?>>Software Developer</option>
                <option value="Data Scientist" <?php if(($user['career_path'] ?? '') == 'Data Scientist') echo 'selected'; ?>>Data Scientist</option>
                <option value="DevOps Engineer" <?php if(($user['career_path'] ?? '') == 'DevOps Engineer') echo 'selected'; ?>>DevOps Engineer</option>
                <option value="Cybersecurity Analyst" <?php if(($user['career_path'] ?? '') == 'Cybersecurity Analyst') echo 'selected'; ?>>Cybersecurity Analyst</option>
            </select>
            
            <label>Profile Picture:</label>
            <input type="file" name="profile_pic" accept="image/*">
            <?php if (!empty($user['profile_pic'])): ?>
                <img src="uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile Picture" class="profile-big">
            <?php endif; ?>
            
            <button type="submit">Update Profile</button>
        </form>
    </div>
    <!-- AOS Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
    </script>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
