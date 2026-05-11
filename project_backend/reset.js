// reset.js (ADD THIS LINE AT THE TOP)
require('dotenv').config();

// server.js - Password Reset Backend 
const express = require('express');
const bcrypt = require('bcryptjs');
const crypto = require('crypto');
const nodemailer = require('nodemailer');
const cors = require('cors');

const app = express();

// Middleware
app.use(express.json());
app.use(cors());

// In-memory storage (replace with database in production)
const users = new Map();
const resetCodes = new Map();

// Email configuration (use environment variables)
const transporter = nodemailer.createTransport({
    service: 'gmail',
    auth: {
        user: process.env.EMAIL_USER,
        pass: process.env.EMAIL_PASSWORD
    }
});

// ==================== HELPER FUNCTIONS ====================

// Generate 6-digit code
function generateResetCode() {
    return Math.floor(100000 + Math.random() * 900000).toString();
}

// Send email with reset code
async function sendResetEmail(email, code) {
    const mailOptions = {
        from: process.env.EMAIL_USER,
        to: email,
        subject: 'Password Reset Code - Portfolio Watcher',
        html: `
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #1173d4;">Password Reset Request</h2>
                <p>Your reset code:</p>
                <div style="background: #f0f0f0; padding: 20px; text-align: center; font-size: 32px; font-weight: bold;">
                    ${code}
                </div>
                <p>Expires in 15 minutes.</p>
            </div>
        `
    };
    try {
        await transporter.sendMail(mailOptions);
        return true;
    } catch (err) {
        console.error('Email sending failed:', err);
        return false;
    }
}

// ==================== API ENDPOINTS ====================

// 0. Base API info (so GET /api works)
app.get('/api', (req, res) => {
    res.json({
        success: true,
        message: 'API is working!',
        endpoints: [
            'POST /api/create-test-user',
            'POST /api/forgot-password',
            'POST /api/verify-reset-code',
            'POST /api/resend-reset-code',
            'POST /api/reset-password'
        ]
    });
});

// 1. Create test user
app.post('/api/create-test-user', async (req, res) => {
    const email = 'test@example.com';
    const password = await bcrypt.hash('password123', 10);

    users.set(email, { email, password, name: 'Test User' });

    res.json({
        success: true,
        message: 'Test user created',
        credentials: { email, password: 'password123' }
    });
});

// 2. Request password reset
app.post('/api/forgot-password', async (req, res) => {
    const { email } = req.body;
    if (!email) return res.status(400).json({ success: false, message: 'Email is required' });

    if (!users.has(email)) {
        return res.json({ success: true, message: 'If an account exists, a reset code will be sent.' });
    }

    const code = generateResetCode();
    const expiresAt = Date.now() + 15 * 60 * 1000;

    resetCodes.set(email, { code, expiresAt, attempts: 0 });

    const emailSent = await sendResetEmail(email, code);
    if (!emailSent) return res.status(500).json({ success: false, message: 'Failed to send email.' });

    res.json({ success: true, message: 'Reset code sent.' });
});

// 3. Verify reset code
app.post('/api/verify-reset-code', (req, res) => {
    const { email, code } = req.body;
    if (!email || !code) return res.status(400).json({ success: false, message: 'Email and code required' });

    const data = resetCodes.get(email);
    if (!data) return res.status(400).json({ success: false, message: 'No reset request found.' });

    if (Date.now() > data.expiresAt) {
        resetCodes.delete(email);
        return res.status(400).json({ success: false, message: 'Code expired.' });
    }

    if (data.attempts >= 5) {
        resetCodes.delete(email);
        return res.status(429).json({ success: false, message: 'Too many attempts.' });
    }

    if (data.code !== code) {
        data.attempts++;
        return res.status(400).json({ success: false, message: 'Invalid code.' });
    }

    data.verified = true;

    res.json({ success: true, message: 'Code verified.', resetToken: crypto.randomBytes(32).toString('hex') });
});

// 4. Resend reset code
app.post('/api/resend-reset-code', async (req, res) => {
    const { email } = req.body;
    if (!email) return res.status(400).json({ success: false, message: 'Email required' });

    if (!users.has(email)) return res.json({ success: true, message: 'If an account exists, a new code will be sent.' });

    const code = generateResetCode();
    const expiresAt = Date.now() + 15 * 60 * 1000;
    resetCodes.set(email, { code, expiresAt, attempts: 0 });

    const emailSent = await sendResetEmail(email, code);
    if (!emailSent) return res.status(500).json({ success: false, message: 'Failed to send email.' });

    res.json({ success: true, message: 'New reset code sent.' });
});

// 5. Reset password
app.post('/api/reset-password', async (req, res) => {
    const { email, code, newPassword } = req.body;
    if (!email || !code || !newPassword) return res.status(400).json({ success: false, message: 'Email, code, and password required' });
    if (newPassword.length < 8) return res.status(400).json({ success: false, message: 'Password must be at least 8 characters' });

    const data = resetCodes.get(email);
    if (!data || !data.verified) return res.status(400).json({ success: false, message: 'Invalid or unverified request' });
    if (Date.now() > data.expiresAt) {
        resetCodes.delete(email);
        return res.status(400).json({ success: false, message: 'Code expired' });
    }

    const hashedPassword = await bcrypt.hash(newPassword, 10);
    const user = users.get(email);
    user.password = hashedPassword;
    users.set(email, user);

    resetCodes.delete(email);

    res.json({ success: true, message: 'Password reset successfully' });
});

// ==================== CLEANUP ====================
setInterval(() => {
    const now = Date.now();
    for (const [email, data] of resetCodes.entries()) {
        if (now > data.expiresAt) resetCodes.delete(email);
    }
}, 5 * 60 * 1000);

// ==================== START SERVER ====================
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
    console.log(`API info at http://localhost:${PORT}/api`);
});
