<?php
// chat.php - Displays conversation between logged-in user and receiver
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';

// Get receiver's user ID from GET parameter
if (!isset($_GET['receiver_id'])) {
    header("Location: messages.php");
    exit;
}
$receiver_id = intval($_GET['receiver_id']);

// Fetch receiver info including profile picture
$stmt = $pdo->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$stmt->execute([$receiver_id]);
$receiver = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$receiver) {
    echo "User not found.";
    exit;
}

// Process sending a new message
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO chats (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $receiver_id, $message]);
        } catch (PDOException $e) {
            die("Error inserting message: " . $e->getMessage());
        }
        // Redirect after successful message send
        header("Location: chat.php?receiver_id=" . $receiver_id);
        exit;
    }
}

// Fetch conversation (both directions) along with sender profile_pic
$stmt = $pdo->prepare("SELECT c.*, u.username, u.profile_pic 
                       FROM chats c 
                       JOIN users u ON c.sender_id = u.id 
                       WHERE (c.sender_id = ? AND c.receiver_id = ?) 
                          OR (c.sender_id = ? AND c.receiver_id = ?)
                       ORDER BY c.created_at ASC");
$stmt->execute([$_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id']]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat with <?php echo htmlspecialchars($receiver['username']); ?></title>
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
        /* Chat Container and Messages */
        .chat-container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .chat-container:hover {
            transform: perspective(1000px) rotateX(2deg) rotateY(2deg);
            box-shadow: var(--card-hover-shadow);
        }
        .chat-header {
            font-size: 1.5em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .chat-header img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        .chat-messages {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.95em;
        }
        .message.sent {
            background: #e0f7fa;
            text-align: right;
            flex-direction: row-reverse;
        }
        .message.received {
            background: #f1f8e9;
            text-align: left;
        }
        .message img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 10px;
        }
        .message small {
            display: block;
            font-size: 0.75em;
            color: #666;
        }
        .chat-form {
            display: flex;
            margin-top: 20px;
        }
        .chat-form input[type="text"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1em;
        }
        .chat-form button {
            padding: 10px 20px;
            margin-left: 10px;
            border: none;
            background: var(--primary);
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .chat-form button:hover {
            background: var(--secondary);
        }
    </style>
</head>
<body>
    <!-- Floating Shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    <header data-aos="fade-down">
        <div class="header-container">
            <h1 data-aos="fade-right">Chat with <?php echo htmlspecialchars($receiver['username']); ?></h1>
            <nav data-aos="fade-left">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="messages.php"><i class="fas fa-comments"></i> Messages</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>
    <div class="chat-container" data-aos="fade-up">
        <div class="chat-header">
            <img src="<?php echo (!empty($receiver['profile_pic']) ? 'uploads/' . $receiver['profile_pic'] : 'assets/default.png'); ?>" alt="Profile Picture">
            <span><?php echo htmlspecialchars($receiver['username']); ?></span>
        </div>
        <div class="chat-messages">
            <?php foreach ($messages as $msg): ?>
                <?php if ($msg['sender_id'] == $_SESSION['user_id']): ?>
                    <div class="message sent">
                        <span><?php echo htmlspecialchars($msg['message']); ?></span>
                        <small><?php echo $msg['created_at']; ?></small>
                    </div>
                <?php else: ?>
                    <div class="message received">
                        <img src="<?php echo (!empty($msg['profile_pic']) ? 'uploads/' . $msg['profile_pic'] : 'assets/default.png'); ?>" alt="Profile Picture">
                        <span><?php echo htmlspecialchars($msg['message']); ?></span>
                        <small><?php echo $msg['created_at']; ?></small>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <form action="chat.php?receiver_id=<?php echo $receiver_id; ?>" method="POST" class="chat-form" data-aos="fade-up" data-aos-delay="100">
            <input type="text" name="message" placeholder="Type your message..." required>
            <button type="submit"><i class="fas fa-paper-plane"></i> Send</button>
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
</body>
</html>
