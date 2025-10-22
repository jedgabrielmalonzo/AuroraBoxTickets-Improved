<?php
require_once __DIR__ . '/config.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendTicketReceipt($toEmail, $toName, $reference_number, $amount, $payment_method, $orderDetails, $mysqli) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;       // From .env
    $mail->Password   = SMTP_PASS;       // From .env
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
        
        // Recipients
    $mail->setFrom(SMTP_USER, 'AuroraBox Tickets');
        $mail->addAddress($toEmail, $toName);
        
        // Build order items HTML
        $orderItemsHtml = '';
        $totalAmount = 0;
        
        if (!empty($orderDetails)) {
            foreach ($orderDetails as $order) {
                // Get park and ticket details
                $parkName = 'Unknown Park';
                $ticketName = 'General Admission';
                
                // Get park name
                try {
                    $parkStmt = $mysqli->prepare("SELECT name FROM parks WHERE id = ?");
                    $parkStmt->bind_param("i", $order['park_id']);
                    $parkStmt->execute();
                    $parkResult = $parkStmt->get_result();
                    if ($parkRow = $parkResult->fetch_assoc()) {
                        $parkName = $parkRow['name'];
                    }
                    $parkStmt->close();
                } catch (Exception $e) {
                    $parkName = 'Park #' . $order['park_id'];
                }
                
                // Get ticket name if ticket_id exists and tickets table exists
                if ($order['ticket_id']) {
                    try {
                        // Check if tickets table exists first
                        $tableCheck = $mysqli->query("SHOW TABLES LIKE 'tickets'");
                        if ($tableCheck && $tableCheck->num_rows > 0) {
                            $ticketStmt = $mysqli->prepare("SELECT name FROM tickets WHERE id = ?");
                            $ticketStmt->bind_param("i", $order['ticket_id']);
                            $ticketStmt->execute();
                            $ticketResult = $ticketStmt->get_result();
                            if ($ticketRow = $ticketResult->fetch_assoc()) {
                                $ticketName = $ticketRow['name'];
                            }
                            $ticketStmt->close();
                        } else {
                            // Fallback ticket names if table doesn't exist
                            $ticketName = 'Adult Admission'; // Based on your PayMongo data
                        }
                    } catch (Exception $e) {
                        $ticketName = 'Adult Admission';
                    }
                }
                
                $itemTotal = $order['quantity'] * $order['price'];
                $totalAmount += $itemTotal;
                
                $orderItemsHtml .= "
                    <tr style='border-bottom: 1px solid #eee;'>
                        <td style='padding: 10px; border-right: 1px solid #eee;'>
                            <strong>{$parkName}</strong><br>
                            <small style='color: #666;'>{$ticketName}</small>
                            " . ($order['visit_date'] ? "<br><small style='color: #666;'>Visit: {$order['visit_date']}</small>" : "") . "
                        </td>
                        <td style='padding: 10px; text-align: center; border-right: 1px solid #eee;'>{$order['quantity']}</td>
                        <td style='padding: 10px; text-align: right; border-right: 1px solid #eee;'>₱" . number_format($order['price'], 2) . "</td>
                        <td style='padding: 10px; text-align: right;'>₱" . number_format($itemTotal, 2) . "</td>
                    </tr>
                ";
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your AuroraBox Ticket Receipt - ' . $reference_number;
        $mail->Body    = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 20px; border: 1px solid #ddd; }
                .footer { background: #f8f9fa; padding: 15px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px; color: #666; }
                .receipt-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .receipt-table th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; }
                .total-row { background: #f8f9fa; font-weight: bold; }
                .status-badge { background: #28a745; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 24px;'>🎟️ AuroraBox</h1>
                    <p style='margin: 5px 0 0 0; opacity: 0.9;'>Your Ticket Receipt</p>
                </div>
                
                <div class='content'>
                    <h2 style='color: #667eea; margin-top: 0;'>Thank you for your purchase!</h2>
                    
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3 style='margin: 0 0 10px 0; color: #333;'>Payment Details</h3>
                        <table style='width: 100%;'>
                            <tr><td><strong>Reference Number:</strong></td><td>{$reference_number}</td></tr>
                            <tr><td><strong>Amount Paid:</strong></td><td>₱" . number_format($amount, 2) . "</td></tr>
                            <tr><td><strong>Payment Method:</strong></td><td>" . ucfirst($payment_method) . "</td></tr>
                            <tr><td><strong>Payment Date:</strong></td><td>" . date('F j, Y g:i A') . "</td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class='status-badge'>CONFIRMED</span></td></tr>
                        </table>
                    </div>
                    
                    " . (!empty($orderDetails) ? "
                    <h3 style='color: #333; margin-bottom: 10px;'>Order Items</h3>
                    <table class='receipt-table'>
                        <thead>
                            <tr>
                                <th>Item Details</th>
                                <th style='text-align: center;'>Qty</th>
                                <th style='text-align: right;'>Unit Price</th>
                                <th style='text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$orderItemsHtml}
                            <tr class='total-row'>
                                <td colspan='3' style='padding: 15px; text-align: right; font-size: 16px;'>TOTAL AMOUNT:</td>
                                <td style='padding: 15px; text-align: right; font-size: 16px;'>₱" . number_format($amount, 2) . "</td>
                            </tr>
                        </tbody>
                    </table>
                    " : "") . "
                    
                    <div style='background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;'>
                        <h4 style='margin: 0 0 10px 0; color: #1976D2;'>📱 Next Steps</h4>
                        <ul style='margin: 0; padding-left: 20px;'>
                            <li>Save this receipt for your records</li>
                            <li>Visit your AuroraBox account to view booking details</li>
                            <li>Present your reference number at the park entrance</li>
                            <li>Bring a valid ID for verification</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <p>Need help? Contact us at <a href='mailto:auroraboxtickets@gmail.com'>support@aurorabox.com</a></p>
                    </div>
                </div>
                
                <div class='footer'>
                    <p style='margin: 0;'>This is an automated email. Please do not reply to this message.</p>
                    <p style='margin: 5px 0 0 0;'>© 2024 AuroraBox. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        file_put_contents("webhook_debug.txt", "Email error: {$mail->ErrorInfo}\n", FILE_APPEND);
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// Keep your original simple function for backward compatibility
function sendTicketEmail($toEmail, $toName, $reference_number, $amount, $payment_method) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;       // From .env
    $mail->Password   = SMTP_PASS;       // From .env
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
        
        // Recipients
    $mail->setFrom(SMTP_USER, 'AuroraBox Tickets');
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8'; // Add charset for emoji support
        $mail->Subject = 'Your AuroraBox Ticket Confirmation';
        $mail->Body    = "
            <h2>Thank you for your purchase!</h2>
            <p>Here are your ticket details:</p>
            <ul>
                <li><b>Reference Number:</b> $reference_number</li>
                <li><b>Amount Paid:</b> ₱" . number_format($amount, 2) . "</li>
                <li><b>Payment Method:</b> $payment_method</li>
            </ul>
            <p>You can view your booking in your AuroraBox account.</p>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}
?>