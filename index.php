<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Configuration
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE');
define('USERS_FILE', __DIR__ . '/users.json');
define('ERROR_LOG', __DIR__ . '/error.log');

// Initialize users file if it doesn't exist
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode([]));
    chmod(USERS_FILE, 0666);
}

// Initialize error log
if (!file_exists(ERROR_LOG)) {
    file_put_contents(ERROR_LOG, '');
    chmod(ERROR_LOG, 0666);
}

// Read users data
function readUsers() {
    if (!file_exists(USERS_FILE)) {
        return [];
    }
    $content = file_get_contents(USERS_FILE);
    return json_decode($content, true) ?: [];
}

// Write users data
function writeUsers($users) {
    $result = file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
    if ($result === false) {
        error_log("Failed to write to users.json");
    }
    return $result !== false;
}

// Telegram API functions
function sendTelegramMessage($chatId, $text, $replyMarkup = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    return makeTelegramRequest('sendMessage', $data);
}

function makeTelegramRequest($method, $data = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
    }
    
    curl_close($ch);
    
    return ['code' => $httpCode, 'response' => $response];
}

// Handle webhook
function handleWebhook($update) {
    if (!isset($update['message'])) {
        return;
    }
    
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $text = $message['text'] ?? '';
    
    $users = readUsers();
    
    // Initialize user if not exists
    if (!isset($users[$userId])) {
        $users[$userId] = [
            'id' => $userId,
            'username' => $message['from']['username'] ?? '',
            'first_name' => $message['from']['first_name'] ?? '',
            'hacks_completed' => 0,
            'subscription' => 'none',
            'created_at' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s')
        ];
    }
    
    $user = &$users[$userId];
    $user['last_active'] = date('Y-m-d H:i:s');
    
    // Handle commands
    switch ($text) {
        case '/start':
            $response = "👑 <b>Darknet Security Suite v4.2</b>\n\n";
            $response .= "🛡️ Status: OPERATIONAL\n";
            $response .= "⚡ User: " . htmlspecialchars($user['first_name']) . "\n";
            $response .= "💰 Hacks: " . $user['hacks_completed'] . "\n";
            $response .= "🔐 Subscription: " . strtoupper($user['subscription']) . "\n\n";
            $response .= "━━━━━━━━━━━━━━━\n";
            $response .= "SELECT A MODULE:\n";
            $response .= "━━━━━━━━━━━━━━━";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📞 OTP BYPASS', 'callback_data' => 'otp_menu'],
                        ['text' => '📸 INSTAGRAM', 'callback_data' => 'ig_menu']
                    ],
                    [
                        ['text' => '👥 FACEBOOK', 'callback_data' => 'fb_menu'],
                        ['text' => '📧 GMAIL', 'callback_data' => 'gm_menu']
                    ],
                    [
                        ['text' => '📷 CAMERA', 'callback_data' => 'cam_menu'],
                        ['text' => '💰 SUBSCRIPTION', 'callback_data' => 'sub_menu']
                    ]
                ]
            ];
            
            sendTelegramMessage($chatId, $response, $keyboard);
            break;
            
        case '/help':
            sendTelegramMessage($chatId, "Available commands:\n/start - Main menu\n/help - This help message\n/stats - Your statistics");
            break;
            
        case '/stats':
            $stats = "📊 <b>Your Statistics</b>\n\n";
            $stats .= "👤 User ID: " . $userId . "\n";
            $stats .= "💰 Hacks Completed: " . $user['hacks_completed'] . "\n";
            $stats .= "🔐 Subscription: " . strtoupper($user['subscription']) . "\n";
            $stats .= "🕒 Last Active: " . $user['last_active'] . "\n";
            $stats .= "📅 Created: " . $user['created_at'];
            sendTelegramMessage($chatId, $stats);
            break;
            
        default:
            // Handle callback queries
            if (isset($update['callback_query'])) {
                handleCallbackQuery($update['callback_query'], $users, $user);
            } else {
                sendTelegramMessage($chatId, "Unknown command. Use /start to begin.");
            }
    }
    
    writeUsers($users);
}

function handleCallbackQuery($callback, &$users, &$user) {
    $data = $callback['data'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    
    // Check subscription for hacking features
    $hackingFeatures = ['otp_menu', 'ig_menu', 'fb_menu', 'gm_menu', 'cam_menu'];
    
    if (in_array($data, $hackingFeatures) && $user['subscription'] === 'none') {
        // Show subscription page
        $message = "🔒 <b>SUBSCRIPTION REQUIRED</b>\n\n";
        $message .= "You need VIP access to use advanced hacking tools.\n\n";
        $message .= "━━━━━━━━━━━━━━━\n";
        $message .= "<b>CHOOSE YOUR PLAN:</b>\n";
        $message .= "━━━━━━━━━━━━━━━\n\n";
        $message .= "⚡ <b>24-Hour Access</b> - ₹49\n";
        $message .= "Perfect for quick operations\n\n";
        $message .= "🔥 <b>Weekly Pro Plan</b> - ₹149\n";
        $message .= "Best value for regular users\n\n";
        $message .= "⭐ <b>Monthly Elite</b> - ₹399\n";
        $message .= "Unlimited access for 30 days\n\n";
        $message .= "👑 <b>Lifetime VIP</b> - ₹999\n";
        $message .= "One-time payment, lifetime access";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⚡ 24-Hour - ₹49', 'url' => 'https://rzp.io/rzp/24-Hour-Access']
                ],
                [
                    ['text' => '🔥 Weekly - ₹149', 'url' => 'https://pages.razorpay.com/7day-149']
                ],
                [
                    ['text' => '⭐ Monthly - ₹399', 'url' => 'https://rzp.io/rzp/r6DI69sF']
                ],
                [
                    ['text' => '👑 Lifetime - ₹999', 'url' => 'https://rzp.io/rzp/u3p0JXnA']
                ],
                [
                    ['text' => '⬅️ BACK', 'callback_data' => 'back_home']
                ]
            ]
        ];
        
        makeTelegramRequest('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard
        ]);
        
        return;
    }
    
    // Handle other callback data
    switch ($data) {
        case 'back_home':
            $response = "👑 <b>Darknet Security Suite v4.2</b>\n\n";
            $response .= "🛡️ Status: OPERATIONAL\n";
            $response .= "💰 Hacks: " . $user['hacks_completed'] . "\n";
            $response .= "🔐 Subscription: " . strtoupper($user['subscription']) . "\n\n";
            $response .= "━━━━━━━━━━━━━━━\n";
            $response .= "SELECT A MODULE:\n";
            $response .= "━━━━━━━━━━━━━━━";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📞 OTP BYPASS', 'callback_data' => 'otp_menu'],
                        ['text' => '📸 INSTAGRAM', 'callback_data' => 'ig_menu']
                    ],
                    [
                        ['text' => '👥 FACEBOOK', 'callback_data' => 'fb_menu'],
                        ['text' => '📧 GMAIL', 'callback_data' => 'gm_menu']
                    ],
                    [
                        ['text' => '📷 CAMERA', 'callback_data' => 'cam_menu'],
                        ['text' => '💰 SUBSCRIPTION', 'callback_data' => 'sub_menu']
                    ]
                ]
            ];
            
            makeTelegramRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $response,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard
            ]);
            break;
            
        case 'sub_menu':
            // Show subscription page (same as above)
            break;
            
        default:
            // Simulate hacking for subscribed users
            if ($user['subscription'] !== 'none') {
                $user['hacks_completed']++;
                $users[$user['id']] = $user;
                
                // Simulate loading
                makeTelegramRequest('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => '🔄 <b>HACKING IN PROGRESS...</b>',
                    'parse_mode' => 'HTML'
                ]);
                
                // After 2 seconds, show success
                sleep(2);
                
                makeTelegramRequest('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => '✅ <b>HACK SUCCESSFUL</b>\n\nTarget compromised successfully!\n\nLogs cleaned ✓\nConnection closed ✓',
                    'parse_mode' => 'HTML',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [['text' => '⬅️ BACK TO MENU', 'callback_data' => 'back_home']]
                        ]
                    ]
                ]);
            }
    }
    
    // Answer callback query
    makeTelegramRequest('answerCallbackQuery', [
        'callback_query_id' => $callback['id']
    ]);
}

// Main execution
try {
    $input = file_get_contents('php://input');
    
    if (!empty($input)) {
        $update = json_decode($input, true);
        
        if ($update) {
            handleWebhook($update);
        } else {
            error_log("Invalid JSON received: " . $input);
        }
    } else {
        // For direct browser access
        echo "🤖 Telegram Bot is running!";
        echo "<br>Users count: " . count(readUsers());
        
        // Display users data (for debugging)
        if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
            echo "<pre>" . json_encode(readUsers(), JSON_PRETTY_PRINT) . "</pre>";
        }
    }
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    http_response_code(500);
    echo "Internal Server Error";
}
?>