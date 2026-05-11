# Email Setup Instructions

## Step 1: Install PHPMailer

### Option A: Using Composer (Recommended)

1. **Download and Install Composer** (if not already installed):
   - Download from: https://getcomposer.org/download/
   - For Windows: Download and run `Composer-Setup.exe`

2. **Install PHPMailer**:
   ```bash
   cd C:\xampp\htdocs\portfolio_watcher
   composer require phpmailer/phpmailer
   ```

### Option B: Manual Installation

1. Download PHPMailer from: https://github.com/PHPMailer/PHPMailer/releases
2. Extract the ZIP file
3. Copy the `src` folder to: `C:\xampp\htdocs\portfolio_watcher\vendor\phpmailer\`
4. The structure should be: `vendor/phpmailer/src/PHPMailer.php`

## Step 2: Configure Email Settings

1. Open `config/email.php`
2. Fill in your email credentials:

   **For Gmail:**
   - `smtp_username`: Your Gmail address (e.g., `yourname@gmail.com`)
   - `smtp_password`: Your Gmail **App Password** (NOT your regular password)
   
   **To get a Gmail App Password:**
   1. Go to your Google Account: https://myaccount.google.com/
   2. Click **Security** → **2-Step Verification** (enable it if not already)
   3. Go to **App Passwords**: https://myaccount.google.com/apppasswords
   4. Select **Mail** and your device
   5. Copy the 16-character password
   6. Paste it in `config/email.php` as `smtp_password`

   **For Other Email Providers:**
   - **Outlook/Hotmail**: `smtp.office365.com`, port `587`
   - **Yahoo**: `smtp.mail.yahoo.com`, port `587`
   - **Custom SMTP**: Update `smtp_host` and `smtp_port` accordingly

## Step 3: Test Email Sending

1. Go to your verification page
2. Enter your email and click "Send Code"
3. Check the browser console (F12) - it should show the verification code in debug mode
4. Check your email inbox for the verification code

## Troubleshooting

**Email not sending?**
- Check that PHPMailer is installed correctly
- Verify your SMTP credentials in `config/email.php`
- For Gmail: Make sure you're using an App Password, not your regular password
- Check your server's error logs: `C:\xampp\apache\logs\error.log`

**Still having issues?**
- The verification code will still appear in the browser console for testing
- Check the debug response in the browser's Network tab (F12 → Network)
- The `email_error` field will show specific error messages


