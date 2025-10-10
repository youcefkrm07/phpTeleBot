<?php
// clone_decrypt_bot.php - CUSTOM PACKAGE ONLY

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN_HERE');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN);
define('STATE_DIR', sys_get_temp_dir() . '/bot_states');

// --- App Cloner Constants ---
define('BASE_KEY_B64', 'Q29GbnBTNnV4S2pkZklPeHZhWHlLNGJ5QlBTMVdjZFU=');
define('CHAINED_KEY_PREFIX', '584BEF6DF3297F91623E2DE659BF8D2F');
define('CHAINED_RESOURCE_PREFIX', 'A8F5F167F44F4964E6C998DEE827110C');
define('CHAINED_MAX_DEPTH', 50);


// Create state directory
if (!is_dir(STATE_DIR)) {
    mkdir(STATE_DIR, 0700, true);
}

// ===== USER STATE MANAGEMENT (FILE-BASED) =====
function getUserState($user_id) {
    $file = STATE_DIR . "/user_{$user_id}.json";
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: [];
    }
    return ['state' => 'idle'];
}

function setUserState($user_id, $data) {
    $file = STATE_DIR . "/user_{$user_id}.json";
    file_put_contents($file, json_encode($data));
}

function clearUserState($user_id) {
    $file = STATE_DIR . "/user_{$user_id}.json";
    if (file_exists($file)) {
        unlink($file);
    }
}

// ===== TELEGRAM API FUNCTIONS =====
function sendMessage($chat_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }
    
    $ch = curl_init(API_URL . '/sendMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}

function sendDocument($chat_id, $file_path, $caption = '') {
    $data = [
        'chat_id' => $chat_id,
        'document' => new CURLFile($file_path),
        'caption' => $caption
    ];
    
    $ch = curl_init(API_URL . '/sendDocument');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}

function downloadFile($file_id) {
    // Get file path
    $ch = curl_init(API_URL . '/getFile?file_id=' . $file_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($result, true);
    if (!$data['ok']) {
        throw new Exception("Failed to get file info");
    }
    
    $file_path = $data['result']['file_path'];
    $file_url = 'https://api.telegram.org/file/bot' . BOT_TOKEN . '/' . $file_path;
    
    // Download file
    $content = file_get_contents($file_url);
    if ($content === false) {
        throw new Exception("Failed to download file");
    }
    
    return $content;
}

// ===== KEYBOARD HELPERS =====
function removeKeyboard() {
    return json_encode(['remove_keyboard' => true]);
}

function mainMenuKeyboard() {
    return json_encode([
        'keyboard' => [
            [['text' => '🔓 Decrypt Settings'], ['text' => '🔒 Encrypt Settings']],
            [['text' => '📦 Decrypt AppCloner.dat']],
            [['text' => '🔓 Decrypt Chained Props'], ['text' => '🔒 Encrypt Chained Props']],
            [['text' => '❓ Help']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ]);
}

// ===== CLONE SETTINGS DECRYPTION LOGIC =====
function decryptCloneSettings($encrypted_base64, $package_name) {
    try {
        // Validate inputs
        if (empty($encrypted_base64) || empty($package_name)) {
            throw new Exception("Missing encrypted content or package name");
        }
        
        // Clean base64 input
        $encrypted_base64 = trim($encrypted_base64);
        $encrypted_base64 = str_replace([' ', "\n", "\r", "\t"], '', $encrypted_base64);
        
        // Generate decryption key
        $fixed_string = "/I am the one who knocks!";
        $key_material = $package_name . $fixed_string;
        $decryption_key = md5($key_material, true); // 16 bytes for AES-128
        
        // Decode base64
        $encrypted_data = base64_decode($encrypted_base64);
        if ($encrypted_data === false) {
            throw new Exception("Invalid base64 encoding");
        }
        
        // Decrypt using AES-128-ECB
        $decrypted = openssl_decrypt(
            $encrypted_data,
            'AES-128-ECB',
            $decryption_key,
            OPENSSL_RAW_DATA
        );
        
        if ($decrypted === false) {
            throw new Exception("Decryption failed - possibly wrong package name");
        }
        
        // Validate JSON
        $json_test = json_decode($decrypted);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Decrypted content is not valid JSON - wrong package name?");
        }
        
        return [
            'success' => true,
            'data' => $decrypted
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function encryptCloneSettings($json_content, $package_name) {
    try {
        // Validate JSON
        $json_test = json_decode($json_content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON format: " . json_last_error_msg());
        }
        
        // Generate encryption key
        $fixed_string = "/I am the one who knocks!";
        $key_material = $package_name . $fixed_string;
        $encryption_key = md5($key_material, true); // 16 bytes for AES-128
        
        // Encrypt using AES-128-ECB
        $encrypted = openssl_encrypt(
            $json_content,
            'AES-128-ECB',
            $encryption_key,
            OPENSSL_RAW_DATA
        );
        
        if ($encrypted === false) {
            throw new Exception("Encryption failed");
        }
        
        // Encode to base64
        $encrypted_base64 = base64_encode($encrypted);
        
        return [
            'success' => true,
            'data' => $encrypted_base64
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ===== APPCLONER.DAT DECRYPTION LOGIC =====
function deriveAppClonerKey($clone_timestamp) {
    try {
        // Decode base key
        $base_key_bytes = base64_decode(BASE_KEY_B64);
        if ($base_key_bytes === false) {
            throw new Exception("Failed to decode base key");
        }
        
        // Convert to string
        $base_key_str = $base_key_bytes;
        
        // Create mutable key by replacing beginning with timestamp
        $key_builder = str_split($base_key_str);
        $timestamp_chars = str_split($clone_timestamp);
        
        $replace_len = min(count($key_builder), count($timestamp_chars));
        for ($i = 0; $i < $replace_len; $i++) {
            $key_builder[$i] = $timestamp_chars[$i];
        }
        
        $final_key_str = implode('', $key_builder);
        $final_key_bytes = $final_key_str; // UTF-8 encoded
        
        return $final_key_bytes;
        
    } catch (Exception $e) {
        throw new Exception("Key derivation failed: " . $e->getMessage());
    }
}

function decryptAppClonerDat($encrypted_data, $clone_timestamp) {
    try {
        // Validate inputs
        if (empty($encrypted_data)) {
            throw new Exception("No encrypted data provided");
        }
        
        if (empty($clone_timestamp)) {
            throw new Exception("Clone timestamp is required");
        }
        
        // Derive the decryption key
        $key = deriveAppClonerKey($clone_timestamp);
        
        // Key must be 16, 24, or 32 bytes for AES
        $key_len = strlen($key);
        if (!in_array($key_len, [16, 24, 32])) {
            throw new Exception("Invalid key length: $key_len bytes (expected 16, 24, or 32)");
        }
        
        // Decrypt using AES-ECB with PKCS7 padding
        $cipher = 'AES-' . ($key_len * 8) . '-ECB';
        $decrypted = openssl_decrypt(
            $encrypted_data,
            $cipher,
            $key,
            OPENSSL_RAW_DATA
        );
        
        if ($decrypted === false) {
            throw new Exception("Decryption failed - possibly wrong clone_timestamp or corrupted data");
        }
        
        // Validate DEX header (magic bytes: "dex\n" or 0x6465780a)
        if (strlen($decrypted) >= 4) {
            $magic = substr($decrypted, 0, 4);
            if ($magic !== "dex\n" && bin2hex($magic) !== '6465780a') {
                // Try to check for other common file signatures
                $hex_magic = bin2hex(substr($decrypted, 0, 8));
                throw new Exception("Decrypted data doesn't appear to be a valid DEX file. Got magic: $hex_magic - wrong timestamp?");
            }
        }
        
        return [
            'success' => true,
            'data' => $decrypted,
            'size' => strlen($decrypted)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ===== CHAINED PROPERTIES LOGIC =====

function findZipEntryData($zip_path, $base_filename) {
    $zip = new ZipArchive;
    if ($zip->open($zip_path) !== TRUE) {
        throw new Exception("Failed to open ZIP file: {$zip_path}");
    }

    $base_filename_lower = strtolower($base_filename);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $item_name = $zip->getNameIndex($i);
        $file_info = pathinfo($item_name);

        // Compare just the filename part
        if (isset($file_info['filename']) && strtolower($file_info['filename']) === $base_filename_lower) {
            $content = $zip->getFromIndex($i);
            $zip->close();
            return $content;
        }
    }

    $zip->close();
    return null;
}

function parseProperties($data_bytes) {
    $properties = [];
    $lines = explode("\n", $data_bytes);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim(str_replace(['\=', '\:'], ['=', ':'], $key));
            $value = trim(str_replace('\n', "\n", $value));
            if (!empty($key)) {
                $properties[$key] = $value;
            }
        }
    }
    return $properties;
}

function formatPropertiesMap($props_map) {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $header_ts = $now->format('Y-m-d H:i:s T');
    $output = "#Props - {$header_ts}\n";
    $output .= "#Total: " . count($props_map) . "\n";

    ksort($props_map);
    foreach ($props_map as $key => $value) {
        $key = str_replace(['=', ':'], ['\=', '\:'], $key);
        $value = str_replace("\n", '\n', $value);
        $output .= "{$key}={$value}\n";
    }
    return $output;
}

function decryptChainedProperties($zip_path, $package_name, $clone_timestamp) {
    try {
        $initial_key_material = CHAINED_KEY_PREFIX . $package_name . $clone_timestamp;
        $current_key_md5 = strtoupper(md5($initial_key_material));

        $all_decrypted_properties = [];
        $files_processed = 0;

        for ($i = 0; $i < CHAINED_MAX_DEPTH; $i++) {
            $resource_filename_hash = strtoupper(md5(CHAINED_RESOURCE_PREFIX . $current_key_md5));
            $encrypted_data = findZipEntryData($zip_path, $resource_filename_hash);

            if ($encrypted_data === null || strlen($encrypted_data) === 0) {
                if ($i === 0) throw new Exception("Initial resource file ('{$resource_filename_hash}') not found in the zip. The zip might be incorrect or the package name/timestamp might be wrong.");
                else break; // End of chain
            }

            $files_processed++;
            // The key is the raw binary representation of the MD5 hex string.
            $aes_key = hex2bin($current_key_md5);

            // A 16-byte key (from 32-char hex) requires AES-128.
            $decrypted_bytes = openssl_decrypt($encrypted_data, 'aes-128-ecb', $aes_key, OPENSSL_RAW_DATA);

            if ($decrypted_bytes === false) {
                 throw new Exception("Decryption failed for resource '{$resource_filename_hash}'. Usually means a wrong package name or timestamp.");
            }

            $properties_chunk = parseProperties($decrypted_bytes);
            if (!empty($properties_chunk)) {
                $all_decrypted_properties = $all_decrypted_properties + $properties_chunk;
            }
            $current_key_md5 = $resource_filename_hash;
        }

        if (empty($all_decrypted_properties) && $files_processed === 0) {
            throw new Exception("No chained properties files were found or processed.");
        }

        return ['success' => true, 'data' => formatPropertiesMap($all_decrypted_properties), 'count' => count($all_decrypted_properties)];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function encryptChainedProperties($properties_content, $package_name, $clone_timestamp) {
    try {
        $source_properties_map = parseProperties($properties_content);
        if ($source_properties_map === null) {
            throw new Exception("Failed to parse source properties file.");
        }

        $all_props_list = [];
        foreach ($source_properties_map as $key => $value) {
            $all_props_list[] = [$key, $value];
        }
        usort($all_props_list, function($a, $b) { return strcmp($a[0], $b[0]); });

        $total_props = count($all_props_list);
        $num_chunks = 25;

        $chunks_of_property_maps = [];

        if ($total_props > 0) {
            $base_size = floor($total_props / $num_chunks);
            $remainder = $total_props % $num_chunks;
            $current_idx = 0;
            for ($i = 0; $i < $num_chunks; $i++) {
                $chunk_size = $base_size + ($i < $remainder ? 1 : 0);
                $chunk_items = array_slice($all_props_list, $current_idx, $chunk_size);
                $chunk_dict = [];
                foreach ($chunk_items as $item) { $chunk_dict[$item[0]] = $item[1]; }
                $chunks_of_property_maps[] = $chunk_dict;
                $current_idx += $chunk_size;
            }
        } else {
            for ($i = 0; $i < $num_chunks; $i++) { $chunks_of_property_maps[] = []; }
        }

        $initial_key_material = CHAINED_KEY_PREFIX . $package_name . $clone_timestamp;
        $current_key_md5 = strtoupper(md5($initial_key_material));
        $encrypted_files = [];

        foreach ($chunks_of_property_maps as $chunk_map) {
            $plain_text = formatPropertiesMap($chunk_map);
            // The key is the raw binary representation of the MD5 hex string.
            $aes_key = hex2bin($current_key_md5);
            // A 16-byte key (from 32-char hex) requires AES-128.
            $encrypted_bytes = openssl_encrypt($plain_text, 'aes-128-ecb', $aes_key, OPENSSL_RAW_DATA);

            if ($encrypted_bytes === false) throw new Exception("Chunk encryption failed.");

            $filename = strtoupper(md5(CHAINED_RESOURCE_PREFIX . $current_key_md5));
            $encrypted_files[$filename] = $encrypted_bytes;
            $current_key_md5 = $filename;
        }

        return ['success' => true, 'files' => $encrypted_files];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}


// ===== PROCESSING FUNCTIONS =====
function processSettingsDecryption($chat_id, $user_id, $encrypted_content, $package) {
    sendMessage($chat_id, "⏳ <b>Decrypting...</b>\n\nPlease wait...", removeKeyboard());
    
    $result = decryptCloneSettings($encrypted_content, $package);
    
    if ($result['success']) {
        // Save to temp file
        $temp_file = tempnam(sys_get_temp_dir(), 'decrypted_') . '.json';
        file_put_contents($temp_file, $result['data']);
        
        // Format JSON nicely
        $json_obj = json_decode($result['data']);
        $pretty_json = json_encode($json_obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($temp_file, $pretty_json);
        
        $message_text = "✅ <b>Decryption Successful!</b>\n\n";
        $message_text .= "📦 <b>Package:</b> <code>{$package}</code>\n";
        $message_text .= "📄 <b>File size:</b> " . number_format(strlen($pretty_json)) . " bytes\n";
        $message_text .= "🔓 <b>Status:</b> Decrypted and formatted";
        
        sendDocument($chat_id, $temp_file, $message_text);
        sendMessage($chat_id, "✨ Ready for next operation!", mainMenuKeyboard());
        
        unlink($temp_file);
    } else {
        $message_text = "❌ <b>Decryption Failed</b>\n\n";
        $message_text .= "📛 <b>Error:</b> " . htmlspecialchars($result['error']) . "\n\n";
        $message_text .= "<b>Common issues:</b>\n";
        $message_text .= "• Wrong package name (case-sensitive)\n";
        $message_text .= "• File is already decrypted\n";
        $message_text .= "• File is corrupted\n";
        $message_text .= "• Invalid base64 encoding\n\n";
        $message_text .= "Please try again with correct details.";
        
        sendMessage($chat_id, $message_text, mainMenuKeyboard());
    }
    
    clearUserState($user_id);
}

function processSettingsEncryption($chat_id, $user_id, $json_content, $package) {
    sendMessage($chat_id, "⏳ <b>Encrypting...</b>\n\nPlease wait...", removeKeyboard());
    
    $result = encryptCloneSettings($json_content, $package);
    
    if ($result['success']) {
        $temp_file = tempnam(sys_get_temp_dir(), 'encrypted_') . '.txt';
        file_put_contents($temp_file, $result['data']);
        
        $message_text = "✅ <b>Encryption Successful!</b>\n\n";
        $message_text .= "📦 <b>Package:</b> <code>{$package}</code>\n";
        $message_text .= "📄 <b>Encrypted size:</b> " . number_format(strlen($result['data'])) . " bytes\n";
        $message_text .= "🔒 <b>Status:</b> Encrypted (Base64)";
        
        sendDocument($chat_id, $temp_file, $message_text);
        sendMessage($chat_id, "✨ Ready for next operation!", mainMenuKeyboard());
        
        unlink($temp_file);
    } else {
        $message_text = "❌ <b>Encryption Failed</b>\n\n";
        $message_text .= "📛 <b>Error:</b> " . htmlspecialchars($result['error']) . "\n\n";
        $message_text .= "Please ensure your JSON file is valid.";
        
        sendMessage($chat_id, $message_text, mainMenuKeyboard());
    }
    
    clearUserState($user_id);
}

function processAppClonerDecryption($chat_id, $user_id, $encrypted_content, $timestamp) {
    sendMessage($chat_id, "⏳ <b>Decrypting AppCloner.dat...</b>\n\nPlease wait...", removeKeyboard());
    
    $result = decryptAppClonerDat($encrypted_content, $timestamp);
    
    if ($result['success']) {
        $temp_file = tempnam(sys_get_temp_dir(), 'decrypted_classes_') . '.dex';
        file_put_contents($temp_file, $result['data']);
        
        $message_text = "✅ <b>AppCloner.dat Decryption Successful!</b>\n\n";
        $message_text .= "🕐 <b>Timestamp:</b> <code>{$timestamp}</code>\n";
        $message_text .= "📄 <b>Decrypted size:</b> " . number_format($result['size']) . " bytes\n";
        $message_text .= "🔓 <b>Status:</b> Valid DEX file\n";
        $message_text .= "💾 <b>Output:</b> decrypted_classes.dex";
        
        sendDocument($chat_id, $temp_file, $message_text);
        
        $info_text = "ℹ️ <b>Next Steps:</b>\n\n";
        $info_text .= "1. Extract the DEX file\n";
        $info_text .= "2. Use tools like jadx, apktool, or dex2jar\n";
        $info_text .= "3. Analyze the decompiled code\n\n";
        $info_text .= "✨ Ready for next operation!";
        
        sendMessage($chat_id, $info_text, mainMenuKeyboard());
        
        unlink($temp_file);
    } else {
        $message_text = "❌ <b>Decryption Failed</b>\n\n";
        $message_text .= "📛 <b>Error:</b> " . htmlspecialchars($result['error']) . "\n\n";
        $message_text .= "<b>Common issues:</b>\n";
        $message_text .= "• Wrong clone_timestamp value\n";
        $message_text .= "• Corrupted appcloner.dat file\n";
        $message_text .= "• Invalid file format\n\n";
        $message_text .= "<b>How to find timestamp:</b>\n";
        $message_text .= "1. Decompile the cloned APK\n";
        $message_text .= "2. Open AndroidManifest.xml\n";
        $message_text .= "3. Find: <code>&lt;meta-data android:name=\"com.applisto.appcloner.cloneTimestamp\"</code>\n";
        $message_text .= "4. Use the value from android:value attribute\n\n";
        $message_text .= "Please try again with correct timestamp.";
        
        sendMessage($chat_id, $message_text, mainMenuKeyboard());
    }
    
    clearUserState($user_id);
}

function processChainedPropertiesDecryption($chat_id, $user_id, $zip_path, $package_name, $timestamp) {
    sendMessage($chat_id, "⏳ <b>Decrypting Chained Properties...</b>\n\nThis may take a moment. Please wait.", removeKeyboard());

    $result = decryptChainedProperties($zip_path, $package_name, $timestamp);

    if ($result['success']) {
        $temp_file = tempnam(sys_get_temp_dir(), 'decrypted_props_') . '.properties';
        file_put_contents($temp_file, $result['data']);

        $message_text = "✅ <b>Chained Properties Decryption Successful!</b>\n\n";
        $message_text .= "📦 <b>Package:</b> <code>{$package_name}</code>\n";
        $message_text .= "🕐 <b>Timestamp:</b> <code>{$timestamp}</code>\n";
        $message_text .= "🔑 <b>Properties Found:</b> " . $result['count'] . "\n";

        sendDocument($chat_id, $temp_file, $message_text);
        sendMessage($chat_id, "✨ Ready for next operation!", mainMenuKeyboard());

        unlink($temp_file);
    } else {
        $message_text = "❌ <b>Decryption Failed</b>\n\n";
        $message_text .= "📛 <b>Error:</b> " . htmlspecialchars($result['error']) . "\n\n";
        $message_text .= "Please try again with correct details.";

        sendMessage($chat_id, $message_text, mainMenuKeyboard());
    }

    if (file_exists($zip_path)) {
        unlink($zip_path);
    }
    clearUserState($user_id);
}

function processChainedPropertiesEncryption($chat_id, $user_id, $properties_content, $package_name, $timestamp) {
    sendMessage($chat_id, "⏳ <b>Encrypting Chained Properties...</b>\n\nThis will create 25 encrypted files. Please wait.", removeKeyboard());

    $result = encryptChainedProperties($properties_content, $package_name, $timestamp);

    if ($result['success']) {
        $zip_path = tempnam(sys_get_temp_dir(), 'chained_props_') . '.zip';
        $zip = new ZipArchive;
        if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
            sendMessage($chat_id, "❌ Error: Could not create the output zip file.", mainMenuKeyboard());
            clearUserState($user_id);
            return;
        }

        foreach ($result['files'] as $filename => $content) {
            $zip->addFromString('assets/' . $filename, $content);
        }
        $zip->close();

        $caption = "✅ <b>Chained Properties Encryption Successful!</b>\n\n";
        $caption .= "📦 <b>Package:</b> <code>{$package_name}</code>\n";
        $caption .= "🕐 <b>Timestamp:</b> <code>{$timestamp}</code>\n";
        $caption .= "🗂️ <b>Files Created:</b> " . count($result['files']) . "\n\n";
        $caption .= "Add these files to your APK's <code>assets</code> directory.";

        sendDocument($chat_id, $zip_path, $caption);
        sendMessage($chat_id, "✨ Ready for next operation!", mainMenuKeyboard());

        unlink($zip_path);

    } else {
        $message_text = "❌ <b>Encryption Failed</b>\n\n";
        $message_text .= "📛 <b>Error:</b> " . htmlspecialchars($result['error']) . "\n\n";
        $message_text .= "Please check your file and try again.";

        sendMessage($chat_id, $message_text, mainMenuKeyboard());
    }

    clearUserState($user_id);
}

// ===== MAIN BOT LOGIC =====
try {
    $update = json_decode(file_get_contents('php://input'), true);
    
    if (!$update) {
        exit('no update');
    }
    
    $message = $update['message'] ?? null;
    if (!$message) {
        exit('no message');
    }
    
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';
    $document = $message['document'] ?? null;
    
    $user_state = getUserState($user_id);
    $state = $user_state['state'] ?? 'idle';
    
    // Handle /start command
    if ($text === '/start') {
        clearUserState($user_id);
        
        $message_text = "👋 <b>Welcome to Clone Tools Bot!</b>\n\n";
        $message_text .= "🔧 <b>Available Tools:</b>\n\n";
        $message_text .= "🔓 <b>Decrypt Settings</b>\n";
        $message_text .= "   └ Decrypt cloneSettings.json files\n\n";
        $message_text .= "🔒 <b>Encrypt Settings</b>\n";
        $message_text .= "   └ Encrypt JSON to cloneSettings format\n\n";
        $message_text .= "📦 <b>Decrypt AppCloner.dat</b>\n";
        $message_text .= "   └ Decrypt appcloner.dat to DEX file\n\n";
        $message_text .= "Choose an option below to begin! 👇";
        
        sendMessage($chat_id, $message_text, mainMenuKeyboard());
        exit('ok');
    }
    
    // Handle /help command
    if ($text === '/help') {
        $message_text = "📖 <b>Help Guide</b>\n\n";
        $message_text .= "<b>🔓 DECRYPT SETTINGS:</b>\n";
        $message_text .= "1. Click 'Decrypt Settings'\n";
        $message_text .= "2. Upload encrypted cloneSettings.json\n";
        $message_text .= "3. Enter package name (e.g., com.whatsapp)\n";
        $message_text .= "4. Download decrypted JSON\n\n";
        
        $message_text .= "<b>🔒 ENCRYPT SETTINGS:</b>\n";
        $message_text .= "1. Click 'Encrypt Settings'\n";
        $message_text .= "2. Upload decrypted JSON file\n";
        $message_text .= "3. Enter package name\n";
        $message_text .= "4. Download encrypted file\n\n";
        
        $message_text .= "<b>📦 DECRYPT APPCLONER.DAT:</b>\n";
        $message_text .= "1. Click 'Decrypt AppCloner.dat'\n";
        $message_text .= "2. Upload appcloner.dat file (from APK assets)\n";
        $message_text .= "3. Enter clone_timestamp from AndroidManifest.xml\n";
        $message_text .= "4. Download decrypted DEX file\n\n";

        $message_text .= "<b>🔓 DECRYPT CHAINED PROPS:</b>\n";
        $message_text .= "1. Click 'Decrypt Chained Props'\n";
        $message_text .= "2. Upload a <code>.zip</code> file containing the encrypted property chunks.\n";
        $message_text .= "3. Enter the clone's package name\n";
        $message_text .= "4. Enter the clone_timestamp\n";
        $message_text .= "5. Download the decrypted .properties file\n\n";

        $message_text .= "<b>🔒 ENCRYPT CHAINED PROPS:</b>\n";
        $message_text .= "1. Click 'Encrypt Chained Props'\n";
        $message_text .= "2. Upload your .properties file\n";
        $message_text .= "3. Enter the clone's package name\n";
        $message_text .= "4. Enter the clone_timestamp\n";
        $message_text .= "5. Download a zip file with the encrypted chunks\n\n";
        
        $message_text .= "<b>Finding clone_timestamp:</b>\n";
        $message_text .= "• Decompile APK with apktool\n";
        $message_text .= "• Open AndroidManifest.xml\n";
        $message_text .= "• Look for: <code>com.applisto.appcloner.cloneTimestamp</code>\n";
        $message_text .= "• Copy the value attribute\n\n";
        
        $message_text .= "<b>Commands:</b>\n";
        $message_text .= "/start - Main menu\n";
        $message_text .= "/cancel - Cancel operation\n";
        $message_text .= "/help - Show this help";
        
        sendMessage($chat_id, $message_text, mainMenuKeyboard());
        exit('ok');
    }
    
    // Handle /cancel command
    if ($text === '/cancel') {
        clearUserState($user_id);
        sendMessage($chat_id, "❌ Operation cancelled. Choose a new option:", mainMenuKeyboard());
        exit('ok');
    }
    
    // Handle "Decrypt Settings" button
    if ($text === '🔓 Decrypt Settings') {
        clearUserState($user_id);
        setUserState($user_id, ['state' => 'awaiting_file_decrypt_settings']);
        
        $message_text = "📤 <b>Upload Encrypted File</b>\n\n";
        $message_text .= "Please upload your encrypted <code>cloneSettings.json</code> file.\n\n";
        $message_text .= "The file should contain Base64-encoded encrypted data.\n\n";
        $message_text .= "Send /cancel to abort.";
        
        sendMessage($chat_id, $message_text, removeKeyboard());
        exit('ok');
    }
    
    // Handle "Encrypt Settings" button
    if ($text === '🔒 Encrypt Settings') {
        clearUserState($user_id);
        setUserState($user_id, ['state' => 'awaiting_file_encrypt_settings']);
        
        $message_text = "📤 <b>Upload JSON File</b>\n\n";
        $message_text .= "Please upload your decrypted JSON file.\n\n";
        $message_text .= "The file must contain valid JSON data.\n\n";
        $message_text .= "Send /cancel to abort.";
        
        sendMessage($chat_id, $message_text, removeKeyboard());
        exit('ok');
    }
    
    // Handle "Decrypt AppCloner.dat" button
    if ($text === '📦 Decrypt AppCloner.dat') {
        clearUserState($user_id);
        setUserState($user_id, ['state' => 'awaiting_file_decrypt_appcloner']);
        
        $message_text = "📤 <b>Upload AppCloner.dat File</b>\n\n";
        $message_text .= "Please upload the <code>appcloner.dat</code> file from your cloned APK's assets folder.\n\n";
        $message_text .= "📍 <b>Location:</b> <code>assets/appcloner.dat</code>\n\n";
        $message_text .= "Send /cancel to abort.";
        
        sendMessage($chat_id, $message_text, removeKeyboard());
        exit('ok');
    }

    // Handle "Decrypt Chained Props" button
    if ($text === '🔓 Decrypt Chained Props') {
        clearUserState($user_id);
        setUserState($user_id, ['state' => 'awaiting_zip_decrypt_chained']);

        $message_text = "📤 <b>Upload ZIP File</b>\n\n";
        $message_text .= "Please upload a <code>.zip</code> file containing the encrypted property chunks.\n\n";
        $message_text .= "Send /cancel to abort.";

        sendMessage($chat_id, $message_text, removeKeyboard());
        exit('ok');
    }

    // Handle "Encrypt Chained Props" button
    if ($text === '🔒 Encrypt Chained Props') {
        clearUserState($user_id);
        setUserState($user_id, ['state' => 'awaiting_props_file_encrypt_chained']);

        $message_text = "📤 <b>Upload .properties File</b>\n\n";
        $message_text .= "Please upload your <code>.properties</code> file to encrypt.\n\n";
        $message_text .= "Send /cancel to abort.";

        sendMessage($chat_id, $message_text, removeKeyboard());
        exit('ok');
    }
    
    // Handle "Help" button
    if ($text === '❓ Help') {
        // Reuse help command logic
        $text = '/help';
        goto help_handler;
    }
    
    help_handler:
    
    // Handle file upload for settings decryption
    if ($state === 'awaiting_file_decrypt_settings' && $document) {
        try {
            sendMessage($chat_id, "⏳ Downloading file...", removeKeyboard());
            
            $file_content = downloadFile($document['file_id']);
            
            // Try to detect if it's already JSON (decrypted)
            $json_test = json_decode($file_content);
            if (json_last_error() === JSON_ERROR_NONE && is_object($json_test)) {
                $message_text = "⚠️ <b>File Already Decrypted</b>\n\n";
                $message_text .= "This appears to be a decrypted JSON file.\n\n";
                $message_text .= "Did you mean to <b>encrypt</b> it instead?\n\n";
                $message_text .= "Use '🔒 Encrypt Settings' button for encryption.";
                
                sendMessage($chat_id, $message_text, mainMenuKeyboard());
                clearUserState($user_id);
            } else {
                // Save encrypted content and ask for package name
                setUserState($user_id, [
                    'state' => 'awaiting_package_decrypt_settings',
                    'encrypted_content' => $file_content
                ]);
                
                $message_text = "✅ <b>File received!</b>\n\n";
                $message_text .= "📦 Now enter the <b>package name</b>\n\n";
                $message_text .= "<b>Examples:</b>\n";
                $message_text .= "• <code>com.whatsapp</code>\n";
                $message_text .= "• <code>com.instagram.android</code>\n";
                $message_text .= "• <code>com.example.app</code>\n\n";
                $message_text .= "⚠️ Package name is case-sensitive!";
                
                sendMessage($chat_id, $message_text, removeKeyboard());
            }
        } catch (Exception $e) {
            $message_text = "❌ <b>Error reading file:</b>\n\n";
            $message_text .= htmlspecialchars($e->getMessage()) . "\n\n";
            $message_text .= "Please try uploading the file again.";
            
            sendMessage($chat_id, $message_text, mainMenuKeyboard());
            clearUserState($user_id);
        }
    }
    // Handle file upload for settings encryption
    elseif ($state === 'awaiting_file_encrypt_settings' && $document) {
        try {
            sendMessage($chat_id, "⏳ Downloading file...", removeKeyboard());
            
            $file_content = downloadFile($document['file_id']);
            
            // Validate JSON
            $json_test = json_decode($file_content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $message_text = "❌ <b>Invalid JSON File</b>\n\n";
                $message_text .= "Error: " . json_last_error_msg() . "\n\n";
                $message_text .= "Please upload a valid JSON file.";
                
                sendMessage($chat_id, $message_text, mainMenuKeyboard());
                clearUserState($user_id);
            } else {
                // Check if it's already encrypted (base64)
                $decoded = base64_decode($file_content, true);
                if ($decoded !== false && base64_encode($decoded) === trim($file_content)) {
                    $message_text = "⚠️ <b>File Might Be Encrypted</b>\n\n";
                    $message_text .= "This looks like Base64-encoded data (possibly already encrypted).\n\n";
                    $message_text .= "Did you mean to <b>decrypt</b> it instead?\n\n";
                    $message_text .= "Proceeding anyway... Enter package name:";
                    sendMessage($chat_id, $message_text, removeKeyboard());
                }
                
                // Save JSON content and ask for package name
                setUserState($user_id, [
                    'state' => 'awaiting_package_encrypt_settings',
                    'json_content' => $file_content
                ]);
                
                $message_text = "✅ <b>File received!</b>\n\n";
                $message_text .= "📦 Now enter the <b>package name</b>\n\n";
                $message_text .= "<b>Examples:</b>\n";
                $message_text .= "• <code>com.whatsapp</code>\n";
                $message_text .= "• <code>com.instagram.android</code>\n";
                $message_text .= "• <code>com.example.app</code>\n\n";
                $message_text .= "⚠️ Package name is case-sensitive!";
                
                sendMessage($chat_id, $message_text, removeKeyboard());
            }
        } catch (Exception $e) {
            $message_text = "❌ <b>Error reading file:</b>\n\n";
            $message_text .= htmlspecialchars($e->getMessage()) . "\n\n";
            $message_text .= "Please try uploading the file again.";
            
            sendMessage($chat_id, $message_text, mainMenuKeyboard());
            clearUserState($user_id);
        }
    }
    // Handle file upload for AppCloner.dat decryption
    elseif ($state === 'awaiting_file_decrypt_appcloner' && $document) {
        try {
            sendMessage($chat_id, "⏳ Downloading file...", removeKeyboard());
            
            $file_content = downloadFile($document['file_id']);
            
            if (strlen($file_content) < 100) {
                throw new Exception("File too small to be valid appcloner.dat");
            }
            
            // Save encrypted content and ask for timestamp
            setUserState($user_id, [
                'state' => 'awaiting_timestamp_appcloner',
                'encrypted_content' => $file_content
            ]);
            
            $message_text = "✅ <b>File received!</b>\n\n";
            $message_text .= "📄 <b>Size:</b> " . number_format(strlen($file_content)) . " bytes\n\n";
            $message_text .= "🕐 Now enter the <b>clone_timestamp</b>\n\n";
            $message_text .= "<b>How to find it:</b>\n";
            $message_text .= "1. Decompile the cloned APK\n";
            $message_text .= "2. Open <code>AndroidManifest.xml</code>\n";
            $message_text .= "3. Find this line:\n";
            $message_text .= "<code>&lt;meta-data android:name=\"com.applisto.appcloner.cloneTimestamp\" android:value=\"...\" /&gt;</code>\n";
            $message_text .= "4. Copy the value from <code>android:value</code>\n\n";
            $message_text .= "<b>Example:</b> <code>1234567890123</code>";
            
            sendMessage($chat_id, $message_text, removeKeyboard());
        } catch (Exception $e) {
            $message_text = "❌ <b>Error reading file:</b>\n\n";
            $message_text .= htmlspecialchars($e->getMessage()) . "\n\n";
            $message_text .= "Please try uploading the file again.";
            
            sendMessage($chat_id, $message_text, mainMenuKeyboard());
            clearUserState($user_id);
        }
    }
    // Handle ZIP file upload for chained properties decryption
    elseif ($state === 'awaiting_zip_decrypt_chained' && $document) {
        try {
            if ($document['mime_type'] !== 'application/zip') {
                sendMessage($chat_id, "⚠️ <b>Invalid File Type</b>\n\nPlease upload a valid <code>.zip</code> file.", mainMenuKeyboard());
                clearUserState($user_id);
                exit('ok');
            }

            sendMessage($chat_id, "⏳ Downloading ZIP file...", removeKeyboard());

            $file_content = downloadFile($document['file_id']);
            $temp_zip_path = tempnam(sys_get_temp_dir(), 'user_zip_') . '.zip';
            file_put_contents($temp_zip_path, $file_content);

            setUserState($user_id, [
                'state' => 'awaiting_package_decrypt_chained',
                'zip_path' => $temp_zip_path
            ]);

            $message_text = "✅ <b>ZIP received!</b>\n\n";
            $message_text .= "📦 Now enter the <b>package name</b> of the cloned app.\n\n";
            $message_text .= "<b>Examples:</b>\n";
            $message_text .= "• <code>com.whatsapp.clone</code>\n";
            $message_text .= "• <code>com.instagram.android.clone</code>\n\n";
            $message_text .= "⚠️ This must be the exact package name of the CLONE.";

            sendMessage($chat_id, $message_text, removeKeyboard());

        } catch (Exception $e) {
            sendMessage($chat_id, "❌ <b>Error processing file:</b>\n\n" . htmlspecialchars($e->getMessage()), mainMenuKeyboard());
            clearUserState($user_id);
        }
    }
    // Handle props file upload for chained properties encryption
    elseif ($state === 'awaiting_props_file_encrypt_chained' && $document) {
        try {
            sendMessage($chat_id, "⏳ Downloading properties file...", removeKeyboard());
            $file_content = downloadFile($document['file_id']);
            setUserState($user_id, [
                'state' => 'awaiting_package_encrypt_chained',
                'props_content' => $file_content
            ]);
            $message_text = "✅ <b>File received!</b>\n\n";
            $message_text .= "📦 Now enter the <b>package name</b> for the clone.\n\n";
            $message_text .= "⚠️ This must be the exact package name you intend to use.";
            sendMessage($chat_id, $message_text, removeKeyboard());
        } catch (Exception $e) {
            sendMessage($chat_id, "❌ <b>Error processing file:</b>\n\n" . htmlspecialchars($e->getMessage()), mainMenuKeyboard());
            clearUserState($user_id);
        }
    }
    // Handle package name input for settings decryption
    elseif ($state === 'awaiting_package_decrypt_settings') {
        $package = trim($text);
        
        if (strlen($package) < 3 || !preg_match('/^[a-zA-Z0-9._]+$/', $package)) {
            sendMessage($chat_id, "❌ <b>Invalid package name format.</b> Please try again.");
        } 
        else {
            $encrypted_content = $user_state['encrypted_content'] ?? '';
            if ($encrypted_content) {
                processSettingsDecryption($chat_id, $user_id, $encrypted_content, $package);
            } else {
                sendMessage($chat_id, "❌ Error: No file data found. Please /start and upload again.", mainMenuKeyboard());
                clearUserState($user_id);
            }
        }
    }
    // Handle package name input for settings encryption
    elseif ($state === 'awaiting_package_encrypt_settings') {
        $package = trim($text);
        
        if (strlen($package) < 3 || !preg_match('/^[a-zA-Z0-9._]+$/', $package)) {
            sendMessage($chat_id, "❌ <b>Invalid package name format.</b> Please try again.");
        } 
        else {
            $json_content = $user_state['json_content'] ?? '';
            if ($json_content) {
                processSettingsEncryption($chat_id, $user_id, $json_content, $package);
            } else {
                sendMessage($chat_id, "❌ Error: No file data found. Please /start and upload again.", mainMenuKeyboard());
                clearUserState($user_id);
            }
        }
    }
    // Handle timestamp input for AppCloner.dat decryption
    elseif ($state === 'awaiting_timestamp_appcloner') {
        $timestamp = trim($text);
        
        if (empty($timestamp) || !is_numeric($timestamp)) {
            sendMessage($chat_id, "❌ <b>Invalid timestamp.</b> It must be a number. Please try again.");
        }
        else {
            $encrypted_content = $user_state['encrypted_content'] ?? '';
            if ($encrypted_content) {
                processAppClonerDecryption($chat_id, $user_id, $encrypted_content, $timestamp);
            } else {
                sendMessage($chat_id, "❌ Error: No file data found. Please /start and upload again.", mainMenuKeyboard());
                clearUserState($user_id);
            }
        }
    }
    // Handle package name for chained props
    elseif ($state === 'awaiting_package_decrypt_chained') {
        $package = trim($text);
        if (strlen($package) < 3 || !preg_match('/^[a-zA-Z0-9._]+$/', $package)) {
            sendMessage($chat_id, "❌ <b>Invalid package name format.</b> Please try again.");
        } else {
            $user_state['state'] = 'awaiting_timestamp_decrypt_chained';
            $user_state['package_name'] = $package;
            setUserState($user_id, $user_state);

            $message_text = "✅ <b>Package name set!</b>\n\n";
            $message_text .= "🕐 Now enter the <b>clone_timestamp</b>.\n\n";
            $message_text .= "You can find this in the cloned APK's <code>AndroidManifest.xml</code> under the key <code>com.applisto.appcloner.cloneTimestamp</code>.";
            sendMessage($chat_id, $message_text, removeKeyboard());
        }
    }
    // Handle timestamp for chained props and process
    elseif ($state === 'awaiting_timestamp_decrypt_chained') {
        $timestamp = trim($text);
        if (empty($timestamp) || !is_numeric($timestamp)) {
            sendMessage($chat_id, "❌ <b>Invalid timestamp.</b> It must be a number. Please try again.");
        } else {
            $zip_path = $user_state['zip_path'] ?? '';
            $package_name = $user_state['package_name'] ?? '';

            if (file_exists($zip_path) && !empty($package_name)) {
                processChainedPropertiesDecryption($chat_id, $user_id, $zip_path, $package_name, $timestamp);
            } else {
                sendMessage($chat_id, "❌ Error: Missing ZIP file or package name. Please /start over.", mainMenuKeyboard());
                if(isset($user_state['zip_path']) && file_exists($user_state['zip_path'])) {
                    unlink($user_state['zip_path']);
                }
                clearUserState($user_id);
            }
        }
    }
    // Handle package name for chained props encryption
    elseif ($state === 'awaiting_package_encrypt_chained') {
        $package = trim($text);
        if (strlen($package) < 3 || !preg_match('/^[a-zA-Z0-9._]+$/', $package)) {
            sendMessage($chat_id, "❌ <b>Invalid package name format.</b> Please try again.");
        } else {
            $user_state['state'] = 'awaiting_timestamp_encrypt_chained';
            $user_state['package_name'] = $package;
            setUserState($user_id, $user_state);

            $message_text = "✅ <b>Package name set!</b>\n\n";
            $message_text .= "🕐 Now enter the <b>clone_timestamp</b>.\n\n";
            sendMessage($chat_id, $message_text, removeKeyboard());
        }
    }
    // Handle timestamp for chained props encryption and process
    elseif ($state === 'awaiting_timestamp_encrypt_chained') {
        $timestamp = trim($text);
        if (empty($timestamp) || !is_numeric($timestamp)) {
            sendMessage($chat_id, "❌ <b>Invalid timestamp.</b> It must be a number. Please try again.");
        } else {
            $props_content = $user_state['props_content'] ?? '';
            $package_name = $user_state['package_name'] ?? '';

            if (!empty($props_content) && !empty($package_name)) {
                processChainedPropertiesEncryption($chat_id, $user_id, $props_content, $package_name, $timestamp);
            } else {
                sendMessage($chat_id, "❌ Error: Missing properties file or package name. Please /start over.", mainMenuKeyboard());
                clearUserState($user_id);
            }
        }
    }
    // Handle any other message
    else {
        if ($text && strpos($text, '/') !== 0) {
            $message_text = "⚠️ <b>Please choose an option</b>\n\n";
            $message_text .= "Use the buttons below or /help for instructions.";
            sendMessage($chat_id, $message_text, mainMenuKeyboard());
        } elseif ($text && strpos($text, '/') === 0) {
            sendMessage($chat_id, "❓ Unknown command. Use /start or /help", mainMenuKeyboard());
        }
    }
    
} catch (Exception $e) {
    error_log("Bot Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (isset($chat_id)) {
        sendMessage($chat_id, "❌ System error occurred. Please try /start to restart.", mainMenuKeyboard());
    }
}

exit('ok');
