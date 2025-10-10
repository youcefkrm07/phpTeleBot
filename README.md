# PHP Telegram Bot for App Cloner

This is a powerful PHP-based Telegram bot designed to help developers and power users work with App Cloner files. It provides a simple, interactive interface to encrypt and decrypt `cloneSettings.json`, decrypt `appcloner.dat` files, and manage **Chained Properties** directly within Telegram.

## Features

- **Decrypt `cloneSettings.json`**: Easily decrypt your `cloneSettings.json` files by providing the encrypted file and the corresponding package name.
- **Encrypt `cloneSettings.json`**: Encrypt a plain JSON settings file back into the App Cloner format.
- **Decrypt `appcloner.dat`**: Decrypt the `appcloner.dat` file (located in the APK's `assets` folder) to retrieve the underlying DEX file.
- **Decrypt Chained Properties**: Decrypts the chained `.properties` files from a cloned APK.
- **Encrypt Chained Properties**: Encrypts a `.properties` file into 25 chained files, ready to be added to an APK.
- **Stateful & Interactive**: The bot guides you through each step of the process.
- **Error Handling**: Provides clear instructions and help for common issues (e.g., wrong package name, incorrect timestamp).
- **Secure**: No user data or files are stored permanently. All operations are performed in memory, and temporary files are deleted immediately.

## Setup

1.  **Get a Bot Token**: Talk to [@BotFather](https://t.me/BotFather) on Telegram to create a new bot and get your API token.
2.  **Configure the Bot**: Open the `botw.php` file and replace the placeholder token with your own:
    ```php
    define('BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN_HERE');
    ```
3.  **Deploy**: Upload the `botw.php` file to a web server that supports PHP.
4.  **Set Webhook**: You must link your deployed script to the Telegram API by setting a webhook. Open your browser and visit the following URL, replacing the placeholders with your information:
    ```
    https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://<YOUR_DOMAIN>/path/to/botw.php
    ```
    You should see a `{"ok":true,"result":true,"description":"Webhook was set"}` response.

## How to Use

Once the bot is running, you can start interacting with it in your Telegram client.

### Commands

-   `/start` - Initializes the bot and displays the main menu.
-   `/help` - Shows a detailed help guide for all features.
-   `/cancel` - Aborts the current operation at any time and returns to the main menu.

### Main Menu

After sending `/start`, you will see the main menu with several options:

-   `🔓 Decrypt Settings (Legacy)`
-   `🔓 Decrypt Settings (Chunks)`
-   `🔒 Encrypt Settings`
-   `📦 Decrypt AppCloner.dat`
-   `🔓 Decrypt Chained Props`
-   `🔒 Encrypt Chained Props`

### Decrypting Settings (from Chunks)

This is the recommended method for decrypting `cloneSettings.json`. It works by assembling encrypted chunks from a `.zip` file.

1.  Press **🔓 Decrypt Settings (Chunks)**.
2.  Upload a `.zip` file containing the encrypted chunks (e.g., `config.bin` or files with MD5-style names).
3.  Provide the app's **package name**.
4.  The bot will find the files, assemble them, and try to decrypt them with both dynamic and fixed keys, then send back the decrypted `cloneSettings.json` file.

### Decrypting Settings (Legacy)

This method is for decrypting a single, already-assembled `cloneSettings.json` file.

1.  Press **🔓 Decrypt Settings (Legacy)**.
2.  The bot will ask you to upload your encrypted `cloneSettings.json` file.
3.  After uploading, the bot will ask for the app's **package name**.
4.  If the package name is correct, the bot will send back the decrypted and formatted `.json` file.

### Encrypting `cloneSettings.json`

1.  Press **🔒 Encrypt Settings**.
2.  The bot will ask you to upload your decrypted (plain text) JSON file.
3.  After uploading, provide the **package name** you want to associate with the settings.
4.  The bot will send back a `.txt` file containing the Base64-encoded encrypted settings.

### Decrypting `appcloner.dat`

1.  Press **📦 Decrypt AppCloner.dat**.
2.  The bot will ask you to upload the `appcloner.dat` file from your cloned APK's `assets` folder.
3.  After uploading, the bot will ask for the `clone_timestamp`.

### Decrypting Chained Properties

This feature allows you to decrypt the string properties that are stored in a chain of encrypted files.

1.  Press **🔓 Decrypt Chained Props**.
2.  The bot will ask you to upload a **`.zip` file** containing the encrypted property chunks.
3.  After uploading, provide the clone's **package name**.
4.  Finally, enter the **`clone_timestamp`**.
5.  The bot will process the zip file, find and decrypt all chained files, and send you a single, combined `.properties` file.

### Encrypting Chained Properties

This feature takes a single `.properties` file and encrypts it into the 25 chained files that App Cloner uses.

1.  Press **🔒 Encrypt Chained Props**.
2.  The bot will ask you to upload your `.properties` file.
3.  After uploading, provide the **package name** for the clone.
4.  Finally, enter the **`clone_timestamp`**.
5.  The bot will encrypt your properties into 25 files and send them back as a `.zip` archive. You can then place these files into the `assets` folder of your APK.

### How to find the `clone_timestamp`

This value is required for most decryption/encryption processes.
-   Decompile the cloned APK using a tool like `apktool`.
-   Open the `AndroidManifest.xml` file.
-   Search for the following meta-data tag:
    ```xml
    <meta-data android:name="com.applisto.appcloner.cloneTimestamp" android:value="1234567890123" />
    ```
-   Copy the `android:value`. This is your timestamp.

## Dependencies

-   PHP 7.0+
-   `php-curl` extension
-   `php-openssl` extension
-   `php-zip` extension (for creating zip archives)

---
*This bot is intended for educational and development purposes. Use it responsibly.*