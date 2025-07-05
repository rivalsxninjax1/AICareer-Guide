<?php
define('FPDF_FONTPATH', __DIR__.'/fpdf/font/');
require_once('fpdf/fpdf.php');

// Enable detailed error reporting for development (remove in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log/php_errors.log');

// Start output buffering
ob_start();

try {
    // Validate session
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 1800,
            'read_and_close'  => false
        ]);
    }
    
    // Detailed session validation
    $sessionErrors = [];
    if (!isset($_SESSION['user_id'])) {
        $sessionErrors[] = "User ID not found in session";
    }
    if (!isset($_SESSION['ai_response'])) {
        $sessionErrors[] = "AI response not found in session";
    }
    if (!empty($sessionErrors)) {
        throw new RuntimeException("Session validation failed: " . implode(", ", $sessionErrors));
    }

    // Validate and sanitize response content
    $response = $_SESSION['ai_response'];
    if (!is_string($response)) {
        throw new DomainException("AI response is not a string (type: " . gettype($response) . ")");
    }
    
    $cleanResponse = trim($response);
    if ($cleanResponse === "") {
        throw new LengthException("AI response is empty after trimming");
    }
    
    $maxLength = 50000;
    if (strlen($cleanResponse) > $maxLength) {
        throw new OutOfBoundsException(sprintf(
            "Response exceeds maximum length (%d > %d characters)",
            strlen($cleanResponse),
            $maxLength
        ));
    }

    // Validate FPDF installation
    $fpdfPath = __DIR__ . '/fpdf/fpdf.php';
    if (!file_exists($fpdfPath)) {
        throw new UnexpectedValueException("FPDF library not found at: " . $fpdfPath);
    }
    
    // Load FPDF with verification
    require_once $fpdfPath;
    if (!class_exists('FPDF')) {
        throw new RuntimeException("FPDF class not loaded properly");
    }

    // PDF Generation with buffer validation
    class DebuggablePDF extends FPDF {
        public function safeMultiCell($w, $h, $txt, $border=0, $align='J', $fill=false) {
            try {
                $this->MultiCell($w, $h, $txt, $border, $align, $fill);
            } catch (Exception $e) {
                throw new LogicException("PDF content error: " . $e->getMessage());
            }
        }
    }

    // Create PDF
    $pdf = new DebuggablePDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);
    
    // Convert line endings and sanitize
    $pdfContent = preg_replace('/\r?\n/', "\n", $cleanResponse);
    $pdfContent = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $pdfContent);
    
    if ($pdfContent === false) {
        throw new RuntimeException("Character encoding conversion failed");
    }

    $pdf->safeMultiCell(0, 8, $pdfContent);
    
    // Verify output buffer
    if (ob_get_length() > 0) {
        $bufferContent = ob_get_clean();
        throw new LogicException("Unexpected output detected: " . substr($bufferContent, 0, 50));
    }

    // Final output
    $pdf->Output('D', 'Career_Report_' . date('Ymd-His') . '.pdf');
    exit;

} catch (Throwable $e) {
    // Detailed error logging
    $errorDetails = sprintf(
        "[%s] ERROR: %s\nFile: %s:%d\nStack Trace:\n%s\n\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    
    error_log($errorDetails, 3, __DIR__ . '/error_log/pdf_errors.log');

    // User-friendly messages with debug info
    $errorCode = bin2hex(random_bytes(4)); // Unique error ID
    $publicMessage = "PDF Generation Failed (Error ID: {$errorCode})";
    $debugMessage = "{$publicMessage} - Reason: {$e->getMessage()}";

    // Show different levels of detail based on environment
    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        // Development environment - show details
        die("<h2>PDF Generation Error</h2>
            <p><strong>{$debugMessage}</strong></p>
            <pre>Error Code: {$errorCode}
            File: {$e->getFile()}:{$e->getLine()}
            Trace ID: {$errorCode}</pre>");
    } else {
        // Production environment - generic message
        header("Location: career_guide.php?error=" . urlencode($publicMessage));
        exit;
    }
} finally {
    // Cleanup buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}