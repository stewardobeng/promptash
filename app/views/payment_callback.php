<?php
$page_title = "Payment Processing";
$user = $auth->getCurrentUser();

// Initialize payment processor
require_once __DIR__ . '/../../helpers/PaymentProcessor.php';

// Payment callback handling
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $paymentProcessor = new PaymentProcessor();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reference'])) {
        // Handle redirect from Paystack
        $reference = $_GET['reference'];
        $result = $paymentProcessor->handlePaymentCallback($reference);
        
        if ($result['success']) {
            // Redirect to success page
            session_start();
            $_SESSION['payment_success'] = true;
            $_SESSION['payment_message'] = 'Premium membership activated successfully!';
            header('Location: index.php?page=upgrade&success=1');
            exit();
        } else {
            // Redirect to failure page
            session_start();
            $_SESSION['payment_error'] = true;
            $_SESSION['payment_message'] = $result['message'];
            header('Location: index.php?page=upgrade&error=1');
            exit();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle webhook from Paystack
        $input = @file_get_contents("php://input");
        $event = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON payload');
        }
        
        // Verify webhook signature (recommended for production)
        // $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        // if (!$paymentProcessor->verifyWebhookSignature($input, $signature)) {
        //     throw new Exception('Invalid webhook signature');
        // }
        
        if ($event['event'] === 'charge.success') {
            $reference = $event['data']['reference'];
            $result = $paymentProcessor->handlePaymentCallback($reference);
            
            $response = $result;
        } else {
            $response = ['success' => true, 'message' => 'Event ignored'];
        }
    } else {
        throw new Exception('Invalid request method or missing parameters');
    }
    
} catch (Exception $e) {
    error_log('Payment callback error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => $e->getMessage()];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Redirect to error page for GET requests
        session_start();
        $_SESSION['payment_error'] = true;
        $_SESSION['payment_message'] = $e->getMessage();
        header('Location: index.php?page=upgrade&error=1');
        exit();
    }
}

// For webhook responses
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>