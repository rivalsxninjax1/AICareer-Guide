<?php
// new_post.php - Process new job post submissions
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = trim($_POST['content']);
    $company_category = trim($_POST['company_category']);
    $company_name = trim($_POST['company_name']);
    $company_location = trim($_POST['company_location']);
    $phone_number = trim($_POST['phone_number']);
    $role_needed = trim($_POST['role_needed']);
    $experience = trim($_POST['experience']);
    
    if (!empty($content) && !empty($company_category) && !empty($company_name) && !empty($role_needed)) {
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, company_category, company_name, company_location, phone_number, role_needed, experience) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $content, $company_category, $company_name, $company_location, $phone_number, $role_needed, $experience]);
    }
}
header("Location: index.php");
exit;
?>
