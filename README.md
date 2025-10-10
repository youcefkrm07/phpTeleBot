# PHP Telegram Bot for App Cloner

This is a powerful PHP-based Telegram bot designed to help developers and power users work with App Cloner files. It provides a simple, interactive interface to encrypt and decrypt `cloneSettings.json` and decrypt `appcloner.dat` files directly within Telegram.

## Features

- **Decrypt `cloneSettings.json`**: Easily decrypt your `cloneSettings.json` files by providing the encrypted file and the corresponding package name.
- **Encrypt `cloneSettings.json`**: Encrypt a plain JSON settings file back into the App Cloner format.
- **Decrypt `appcloner.dat`**: Decrypt the `appcloner.dat` file (located in the APK's `assets` folder) to retrieve the underlying DEX file.
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

After sending `/start`, you will see the main menu with three options:

-   `🔓 Decrypt Settings`
-   `🔒 Encrypt Settings`
-   `📦 Decrypt AppCloner.dat`

### Decrypting `cloneSettings.json`

1.  Press **🔓 Decrypt Settings**.
2.  The bot will ask you to upload your encrypted `cloneSettings.json` file.
3.  After uploading, the bot will ask for the app's **package name** (e.g., `com.whatsapp`). This is case-sensitive.
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

    **How to find the `clone_timestamp`**:
    -   Decompile the cloned APK using a tool like `apktool`.
    -   Open the `AndroidManifest.xml` file.
    -   Search for the following meta-data tag:
        ```xml
        <meta-data android:name="com.applisto.appcloner.cloneTimestamp" android:value="1234567890123" />
        ```
    -   Copy the `android:value`. This is your timestamp.

4.  Provide the timestamp to the bot. It will decrypt the file and send you the `decrypted_classes.dex` file.

## Dependencies

-   PHP 7.0+
-   `php-curl` extension
-   `php-openssl` extension

---
*This bot is intended for educational and development purposes. Use it responsibly.*