/**
 * server.js
 * Portfolio Watcher - Unified Server with Indian Stock Search
 */

require('dotenv').config();
const puppeteer = require('puppeteer');
const express = require('express');
const session = require('express-session');
const bcrypt = require('bcryptjs');
const mysql = require('mysql2/promise');
const nodemailer = require('nodemailer');
const cors = require('cors');
const rateLimit = require('express-rate-limit');
const jwt = require('jsonwebtoken');
const helmet = require('helmet');
const { body, validationResult } = require('express-validator');
const axios = require('axios');
const path = require('path');

// ====== Indian Stock Search Loader =====
const { loadIndianStocks, indianStocksCache } = require('./loaders/loadIndianStocks');

const app = express();
app.use(helmet());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ------------ CORS -------------
app.use(cors({
  origin: [
    'http://localhost',
    'http://localhost:80',
    'http://127.0.0.1',
    'http://127.0.0.1:80',
    'http://localhost:5500',
    'http://127.0.0.1:5500',
    'http://localhost:3000',
    'http://127.0.0.1:3000'
  ],
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization']
}));

// ------------ SESSION -------------
app.use(session({
  secret: process.env.SESSION_SECRET || process.env.JWT_SECRET || 'change_this_secret',
  resave: false,
  saveUninitialized: false,
  cookie: {
    secure: false,
    httpOnly: true,
    sameSite: 'lax',
    maxAge: 24 * 60 * 60 * 1000,
    domain: 'localhost'
  }
}));

// ------------ RATE LIMITERS -------------
const authLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 5,
  message: { success: false, message: 'Too many requests, please try again after a minute.' }
});

const resendLimiter = rateLimit({
  windowMs: 60 * 60 * 1000,
  max: 3,
  message: { success: false, message: 'Too many resend attempts. Please try again after an hour.' }
});

// ------------ UTILITIES -------------
const JWT_SECRET = process.env.JWT_SECRET || 'your-jwt-secret';

function generateToken(userId, email) {
  return jwt.sign({ userId, email }, JWT_SECRET, { expiresIn: '7d' });
}

function generateResetCode() {
  return Math.floor(100000 + Math.random() * 900000).toString();
}

function generateVerificationCode() {
  return Math.floor(100000 + Math.random() * 900000).toString();
}

function validatePassword(password) {
  if (password.length < 8) return { valid: false, message: 'Password must be at least 8 characters long' };
  if (!/[A-Z]/.test(password)) return { valid: false, message: 'Password must contain at least one uppercase letter' };
  if (!/[a-z]/.test(password)) return { valid: false, message: 'Password must contain at least one lowercase letter' };
  if (!/\d/.test(password)) return { valid: false, message: 'Password must contain at least one number' };
  return { valid: true };
}

// ------------ DATABASE POOL -------------
const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

pool.getConnection()
  .then(conn => { 
    console.log('✅ Database connected successfully'); 
    conn.release(); 
  })
  .catch(err => { 
    console.error('❌ Database connection failed:', err.message); 
    process.exit(1); 
  });

// ------------ EMAIL -------------
const transporter = nodemailer.createTransport({
  service: process.env.EMAIL_SERVICE || 'gmail',
  auth: {
    user: process.env.EMAIL_USER,
    pass: process.env.EMAIL_PASSWORD
  }
});

transporter.verify()
  .then(() => console.log('✅ Email service ready'))
  .catch(err => console.warn('⚠️ Email service error (ignored for dev):', err.message));

async function sendResetEmail(email, code, userName = 'User') {
  const html = `<div>
    <h2>Password reset code</h2>
    <p>Hello ${userName},</p>
    <p>Use this code to reset your password: <strong>${code}</strong> (expires in 15 minutes)</p>
    <p>If you did not request this, ignore this email.</p>
  </div>`;
  
  try {
    await transporter.sendMail({
      from: process.env.EMAIL_USER,
      to: email,
      subject: 'Password Reset Code - Portfolio Watcher',
      html
    });
    return { success: true };
  } catch (err) {
    console.error('Email send error:', err.message);
    return { success: false, error: err.message };
  }
}

async function sendVerificationEmail(email, code, userName = 'User') {
  const html = `<div>
    <h2>Account verification code</h2>
    <p>Hello ${userName},</p>
    <p>Your account verification code is: <strong>${code}</strong> (expires in 30 minutes).</p>
    <p>If you did not create an account, ignore this email.</p>
  </div>`;
  
  try {
    await transporter.sendMail({
      from: process.env.EMAIL_USER,
      to: email,
      subject: 'Verify your Portfolio Watcher account',
      html
    });
    return { success: true };
  } catch (err) {
    console.error('Verification email error:', err.message);
    return { success: false, error: err.message };
  }
}

// ------------ AUTH MIDDLEWARE -------------
function requireAuth(req, res, next) {
  if (!req.session.user_id) {
    return res.status(401).json({ success: false, message: 'Not authenticated' });
  }
  next();
}

// ========================= ROUTES =========================

// Health
app.get('/api/health', (req, res) => {
  res.json({ 
    status: 'ok', 
    uptime: process.uptime(), 
    timestamp: new Date().toISOString(),
    stockCacheLoaded: indianStocksCache.stocks.length > 0,
    stockCacheSize: indianStocksCache.stocks.length
  });
});

// ---------- AUTH: REGISTER ----------
app.post('/api/auth/register',
  authLimiter,
  body('email').isEmail().normalizeEmail(),
  body('password').isLength({ min: 8 }),
  body('full_name').optional().trim(),
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, message: 'Invalid input data' });
      }

      const { email, password, full_name } = req.body;
      const pv = validatePassword(password);
      if (!pv.valid) {
        return res.status(400).json({ success: false, message: pv.message });
      }

      const [existing] = await pool.query('SELECT id FROM users WHERE email = ?', [email]);
      if (existing.length > 0) {
        return res.status(400).json({ success: false, message: 'Email already registered' });
      }

      const hashed = await bcrypt.hash(password, 10);
      const [result] = await pool.query(
        'INSERT INTO users (email, password, name) VALUES (?, ?, ?)', 
        [email, hashed, full_name || null]
      );

      const token = generateToken(result.insertId, email);
      req.session.user_id = result.insertId;
      req.session.email = email;

      res.status(201).json({ 
        success: true, 
        message: 'Registration successful', 
        token, 
        user: { id: result.insertId, email, full_name: full_name || null }, 
        redirect: 'home.html' 
      });
    } catch (err) {
      console.error('Register error:', err);
      res.status(500).json({ success: false, message: 'Server error. Please try again later.' });
    }
  }
);

// ---------- AUTH: LOGIN ----------
app.post('/api/auth/login', 
  authLimiter,
  body('email').isEmail().normalizeEmail(),
  body('password').notEmpty(),
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, message: 'Invalid email or password' });
      }

      const { email, password } = req.body;
      const [users] = await pool.query(
        'SELECT id, email, password, name, verified FROM users WHERE email = ?', 
        [email]
      );
      
      if (users.length === 0) {
        return res.status(401).json({ success: false, message: 'Invalid email or password' });
      }

      const user = users[0];
      const ok = await bcrypt.compare(password, user.password);
      if (!ok) {
        return res.status(401).json({ success: false, message: 'Invalid email or password' });
      }

      const token = generateToken(user.id, user.email);
      req.session.user_id = user.id;
      req.session.email = user.email;

      res.json({ 
        success: true, 
        message: 'Login successful', 
        token, 
        user: { id: user.id, email: user.email, full_name: user.name }, 
        redirect: 'home.html' 
      });
    } catch (err) {
      console.error('Login error:', err);
      res.status(500).json({ success: false, message: 'Server error. Please try again later.' });
    }
  }
);

// ---------- AUTH: CHECK SESSION ----------
app.get('/api/check-session', (req, res) => {
  if (!req.session.user_id) {
    return res.json({ success: false });
  }

  return res.json({
    success: true,
    user: {
      id: req.session.user_id,
      email: req.session.email
    }
  });
});

// ---------- AUTH: LOGOUT ----------
app.post('/api/logout', (req, res) => {
  req.session.destroy(err => {
    if (err) {
      return res.status(500).json({ success: false, message: 'Logout failed' });
    }
    res.json({ success: true, message: 'Logged out successfully' });
  });
});

// ---------- AUTH: SEND VERIFICATION CODE ----------
app.post('/api/auth/send-code', 
  resendLimiter, 
  body('email').isEmail().normalizeEmail(), 
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, message: 'Invalid email address' });
      }

      const { email } = req.body;
      const [users] = await pool.query('SELECT id, name FROM users WHERE email = ?', [email]);

      if (users.length === 0) {
        return res.status(400).json({ success: false, message: 'User not found' });
      }

      const user = users[0];
      const code = generateVerificationCode();
      const expiresAt = new Date(Date.now() + 30 * 60 * 1000);

      await pool.query(
        'UPDATE users SET verify_code = ?, verify_expires = ?, verified = 0 WHERE id = ?', 
        [code, expiresAt, user.id]
      );

      const mailResult = await sendVerificationEmail(email, code, user.name || 'User');
      if (!mailResult.success) {
        return res.status(500).json({ success: false, message: 'Failed to send verification email' });
      }

      return res.json({ success: true, message: 'Verification code sent' });
    } catch (err) {
      console.error('send-code error:', err);
      return res.status(500).json({ success: false, message: 'Server error. Please try again later.' });
    }
  }
);

// ---------- AUTH: VERIFY EMAIL ----------
app.post('/api/auth/verify-email', 
  authLimiter, 
  body('email').isEmail().normalizeEmail(), 
  body('code').isLength({ min: 6, max: 6 }).isNumeric(), 
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, message: 'Invalid email or code format' });
      }

      const { email, code } = req.body;
      const [rows] = await pool.query(
        'SELECT id, verify_code, verify_expires FROM users WHERE email = ? LIMIT 1', 
        [email]
      );
      
      if (rows.length === 0) {
        return res.status(400).json({ success: false, message: 'User not found' });
      }

      const user = rows[0];

      if (!user.verify_code || String(user.verify_code) !== String(code)) {
        return res.status(400).json({ success: false, message: 'Incorrect verification code' });
      }

      if (user.verify_expires && new Date() > new Date(user.verify_expires)) {
        await pool.query('UPDATE users SET verify_code = NULL, verify_expires = NULL WHERE id = ?', [user.id]);
        return res.status(400).json({ success: false, message: 'Verification code expired. Please request a new code.' });
      }

      await pool.query(
        'UPDATE users SET verified = 1, verify_code = NULL, verify_expires = NULL WHERE id = ?', 
        [user.id]
      );

      req.session.user_id = user.id;
      req.session.email = email;
      req.session.save();

      return res.json({ success: true, message: 'Email verified successfully' });
    } catch (err) {
      console.error('verify-email error:', err);
      return res.status(500).json({ success: false, message: 'Server error. Please try again later.' });
    }
  }
);

// -------------------- PASSWORD RESET --------------------

app.post('/api/forgot-password', 
  resendLimiter, 
  body('email').isEmail().normalizeEmail(), 
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, message: 'Invalid email address' });
      }

      const { email } = req.body;
      const [users] = await pool.query('SELECT id, email, name FROM users WHERE email = ?', [email]);
      
      if (users.length === 0) {
        return res.json({ success: true, message: 'If an account exists, you will receive a reset code.' });
      }

      const user = users[0];
      await pool.query('DELETE FROM password_reset_codes WHERE email = ?', [email]);

      const resetCode = generateResetCode();
      const expiresAt = new Date(Date.now() + 15 * 60 * 1000);

      await pool.query(
        `INSERT INTO password_reset_codes (user_id, email, reset_code, expires_at, verified, attempts, created_at) 
         VALUES (?, ?, ?, ?, FALSE, 0, NOW())`,
        [user.id, email, resetCode, expiresAt]
      );

      const emailResult = await sendResetEmail(email, resetCode, user.name || 'User');
      if (!emailResult.success) {
        return res.status(500).json({ success: false, message: 'Failed to send email. Please try again later.' });
      }

      return res.json({ success: true, message: 'Reset code sent to your email' });
    } catch (err) {
      console.error('Forgot password error:', err);
      res.status(500).json({ success: false, message: 'Server error. Please try again later.' });
    }
  }
);

app.post('/api/verify-reset-code',
  body('email').isEmail().normalizeEmail(),
  body('code').isLength({ min: 6, max: 6 }).isNumeric(),
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, message: 'Invalid email or code format' });
      }

      const { email, code } = req.body;
      const [rows] = await pool.query(
        `SELECT * FROM password_reset_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1`, 
        [email]
      );
      
      if (rows.length === 0) {
        return res.status(400).json({ success: false, message: 'Invalid or expired reset code' });
      }

      const row = rows[0];

      if (new Date() > new Date(row.expires_at)) {
        await pool.query('DELETE FROM password_reset_codes WHERE id = ?', [row.id]);
        return res.status(400).json({ success: false, message: 'Reset code has expired. Please request a new one.' });
      }

      if (row.attempts >= 5) {
        await pool.query('DELETE FROM password_reset_codes WHERE id = ?', [row.id]);
        return res.status(429).json({ success: false, message: 'Too many attempts. Please request a new code.' });
      }

      if (row.reset_code !== code) {
        const newAttempts = row.attempts + 1;
        await pool.query('UPDATE password_reset_codes SET attempts = ? WHERE id = ?', [newAttempts, row.id]);
        
        if (newAttempts >= 5) {
          await pool.query('DELETE FROM password_reset_codes WHERE id = ?', [row.id]);
          return res.status(429).json({ success: false, message: 'Too many attempts. Please request a new code.' });
        }
        
        return res.status(400).json({ success: false, message: `Invalid code. ${5 - newAttempts} attempts left.` });
      }

      await pool.query('UPDATE password_reset_codes SET verified = TRUE, verified_at = NOW() WHERE id = ?', [row.id]);
      req.session.resetVerified = { email, code, resetId: row.id, verifiedAt: new Date().toISOString() };
      req.session.save();

      return res.json({ success: true, message: 'Code verified successfully' });
    } catch (err) {
      console.error('Verify code error:', err);
      res.status(500).json({ success: false, message: 'Server error. Please try again later.' });
    }
  }
);

app.get('/api/check-reset-session', (req, res) => {
  if (req.session && req.session.resetVerified) {
    return res.json({ verified: true, email: req.session.resetVerified.email });
  }
  return res.json({ verified: false });
});

app.post('/api/reset-password',
  body('newPassword').isLength({ min: 8 }),
  async (req, res) => {
    try {
      if (!req.session || !req.session.resetVerified) {
        return res.status(400).json({ success: false, message: 'Reset request not verified' });
      }

      const { newPassword } = req.body;
      const { email, resetId } = req.session.resetVerified;

      const pv = validatePassword(newPassword);
      if (!pv.valid) {
        return res.status(400).json({ success: false, message: pv.message });
      }

      const [rows] = await pool.query(
        `SELECT * FROM password_reset_codes WHERE id = ? AND email = ? AND verified = TRUE LIMIT 1`, 
        [resetId, email]
      );
      
      if (rows.length === 0) {
        return res.status(400).json({ success: false, message: 'Invalid or unverified reset request' });
      }

      const resetRow = rows[0];
      if (new Date() > new Date(resetRow.expires_at)) {
        await pool.query('DELETE FROM password_reset_codes WHERE id = ?', [resetRow.id]);
        delete req.session.resetVerified;
        req.session.save();
        return res.status(400).json({ success: false, message: 'Reset code has expired' });
      }

      const hashed = await bcrypt.hash(newPassword, 10);
      await pool.query('UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?', [hashed, email]);
      await pool.query('DELETE FROM password_reset_codes WHERE id = ?', [resetRow.id]);

      delete req.session.resetVerified;
      req.session.save();

      return res.json({ success: true, message: 'Password reset successfully' });
    } catch (err) {
      console.error('Reset password error:', err);
      res.status(500).json({ success: false, message: 'Server error. Please try again later.' });
    }
  }
);

// -------------------- INDIAN STOCK SEARCH --------------------

app.get('/api/stocks/search-indian', async (req, res) => {
  try {
    const query = (req.query.q || '').toLowerCase().trim();
    
    if (query.length < 2) {
      return res.json({ 
        success: true, 
        stocks: [], 
        count: 0, 
        message: 'Query too short' 
      });
    }

    // Ensure cache is loaded
    if (indianStocksCache.stocks.length === 0 && !indianStocksCache.isLoading) {
      loadIndianStocks();
      return res.json({ 
        success: true, 
        stocks: [], 
        count: 0, 
        loading: true 
      });
    }

    // Search in cache
    const results = indianStocksCache.stocks.filter(stock => {
      const s = (stock.symbol || '').toString().toLowerCase();
      const n = (stock.name || '').toString().toLowerCase();
      return s.includes(query) || n.includes(query);
    });

    // Sort exact symbol matches first
    results.sort((a, b) => {
      const aExact = (a.symbol || '').toLowerCase() === query;
      const bExact = (b.symbol || '').toLowerCase() === query;
      if (aExact && !bExact) return -1;
      if (!aExact && bExact) return 1;
      
      const aStarts = (a.symbol || '').toLowerCase().startsWith(query);
      const bStarts = (b.symbol || '').toLowerCase().startsWith(query);
      if (aStarts && !bStarts) return -1;
      if (!aStarts && bStarts) return 1;
      
      return 0;
    });

    const limited = results.slice(0, 40);
    
    return res.json({ 
      success: true, 
      stocks: limited.map(s => ({ 
        symbol: s.symbol, 
        name: s.name, 
        exchange: s.exchange 
      })), 
      count: limited.length, 
      totalMatches: results.length, 
      cacheAge: indianStocksCache.lastUpdated 
    });
  } catch (err) {
    console.error('Search error:', err);
    res.status(500).json({ success: false, message: 'Search failed' });
  }
});

app.post('/api/stocks/refresh-cache', requireAuth, async (req, res) => {
  try {
    await loadIndianStocks({ forceRefresh: true });
    res.json({ 
      success: true, 
      count: indianStocksCache.stocks.length, 
      lastUpdated: indianStocksCache.lastUpdated 
    });
  } catch (err) {
    console.error('Refresh cache error:', err);
    res.status(500).json({ success: false, message: 'Failed to refresh cache' });
  }
});

// -------------------- LEGACY STOCK API (TwelveData) --------------------

app.get('/api/stocks/search', async (req, res) => {
  const q = req.query.q;
  if (!q) {
    return res.status(400).json({ error: "Missing query parameter 'q'" });
  }
  
  try {
    const response = await axios.get(
      `https://api.twelvedata.com/symbol_search?symbol=${encodeURIComponent(q)}&apikey=${process.env.STOCK_API_KEY}`
    );
    return res.json(response.data);
  } catch (err) {
    console.error('Stock search failed:', err.message);
    return res.status(500).json({ error: 'Stock search API failed' });
  }
});

app.get('/api/stocks/price', async (req, res) => {
  const symbol = req.query.symbol;
  if (!symbol) {
    return res.status(400).json({ error: "Missing symbol parameter" });
  }
  
  try {
    const response = await axios.get(
      `https://api.twelvedata.com/price?symbol=${encodeURIComponent(symbol)}&apikey=${process.env.STOCK_API_KEY}`
    );
    return res.json(response.data);
  } catch (err) {
    console.error('Stock price failed:', err.message);
    return res.status(500).json({ error: 'Price API failed' });
  }
});

app.get('/api/stocks/details', async (req, res) => {
  const symbol = req.query.symbol;
  if (!symbol) {
    return res.status(400).json({ error: "Missing symbol parameter" });
  }
  
  try {
    const response = await axios.get(
      `https://api.twelvedata.com/fundamentals?symbol=${encodeURIComponent(symbol)}&apikey=${process.env.STOCK_API_KEY}`
    );
    return res.json(response.data);
  } catch (err) {
    console.error('Stock details failed:', err.message);
    return res.status(500).json({ error: 'Details API failed' });
  }
});

// ---------------- BROKER OAUTH ROUTES ----------------

const angeloneRoutes = require('./routes/angelone');
const zerodhaRoutes = require('./routes/zerodha');
const upstoxRoutes = require('./routes/upstox');

app.use('/api/angelone', angeloneRoutes);
app.use('/api/zerodha', zerodhaRoutes);
app.use('/api/upstox', upstoxRoutes);

// ---------------- NEWS MONITORING ROUTES ----------------
// --- Notion URL auto-converter middleware ---
// Small helper to make ANY Notion URL scrapable by converting to pvs=4 & export when needed.
function fixNotionURL(rawUrl) {
  if (!rawUrl || typeof rawUrl !== 'string') return rawUrl;

  try {
    // preserve original if not a URL
    const maybe = rawUrl.trim();
    // quick check to avoid throwing on non-url strings
    if (!/^https?:\/\//i.test(maybe)) return rawUrl;

    const u = new URL(maybe);

    // operate only on notion domains
    if (u.hostname.includes('notion.so') || u.hostname.includes('notion.site')) {

      // If it's notion.so, try to use the site host variant when possible.
      // (We leave workspace-hosted notion.site hostnames untouched except for params)
      if (u.hostname === 'www.notion.so' || u.hostname === 'notion.so') {
        // convert to notion.site — this makes the path scrapable in many cases.
        // NOTE: we don't know the workspace prefix here, but many public shares use the notion.site host already.
        u.hostname = u.hostname.replace('notion.so', 'notion.site');
      }

      // force pvs param to request pre-rendered static HTML
      if (!u.searchParams.has('pvs')) {
        u.searchParams.set('pvs', '4');
      }

      // add export param to force plain HTML where supported
      if (!u.searchParams.has('export')) {
        // export with empty value is acceptable; URLSearchParams will render it as `export=`
        u.searchParams.set('export', '');
      }

      // remove params that may force client-only render (optional safety)
      // keep source param if present (no harm), but ensure pvs & export exist
      return u.toString();
    }

    return rawUrl;
  } catch (e) {
    // if URL parsing fails, return original
    return rawUrl;
  }
}

// Middleware: rewrite Notion URLs on requests to /api/news/* so downstream routes get a scrapable URL
app.use((req, res, next) => {
  try {
    // Apply only to news routes to avoid touching other endpoints
    if (req.path && req.path.startsWith('/api/news')) {
      if (req.body && req.body.url) {
        req.body.url = fixNotionURL(req.body.url);
      }
      if (req.query && req.query.url) {
        req.query.url = fixNotionURL(req.query.url);
      }
    }
  } catch (err) {
    // don't break requests if anything goes wrong here
    console.warn('Notion URL middleware warning:', err && err.message);
  }
  next();
});


const newsRoutes = require('./routes/news');
app.use('/api/news', newsRoutes);
// ---------- NOTION CONTENT ROUTE ----------
app.use('/api', require('./routes/notion'));
// Get connected brokers
app.get('/api/get-connected-brokers', requireAuth, async (req, res) => {
  try {
    const userId = req.session.user_id;
    const [rows] = await pool.execute(
      'SELECT broker_name, created_at FROM broker_connections WHERE user_id = ?', 
      [userId]
    );
    const brokers = rows.map(r => r.broker_name);
    return res.json({ success: true, brokers });
  } catch (err) {
    console.error('Get connected brokers error:', err);
    return res.status(500).json({ success: false, message: 'Failed to fetch connected brokers' });
  }
});

// ---------------- CLEANUP TASK ----------------

setInterval(async () => {
  try {
    const [result] = await pool.query('DELETE FROM password_reset_codes WHERE expires_at < NOW()');
    if (result.affectedRows > 0) {
      console.log(`Cleaned up ${result.affectedRows} expired reset codes`);
    }
  } catch (err) {
    console.error('Cleanup error:', err);
  }
}, 5 * 60 * 1000);
// Replace your /api/fetch-html endpoint with this enhanced version:
app.get('/api/fetch-html', async (req, res) => {
  try {
    const targetUrl = req.query.url;
    if (!targetUrl) {
      return res.status(400).json({ error: "Missing url parameter" });
    }

    console.log(`📡 Fetching URL: ${targetUrl}`);

    const isMedium =
    targetUrl.includes("medium.com") ||
    targetUrl.includes("link.medium.com") ||
    /^https?:\/\/medium\.com\//i.test(targetUrl);

    
    // For Medium, use Puppeteer (headless browser)
    if (isMedium) {
      console.log('🌐 Using Puppeteer for Medium');
      
      let browser;
      try {
        browser = await puppeteer.launch({
          headless: 'new',
          args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        
        const page = await browser.newPage();
        
        // Set realistic viewport and user agent
        await page.setViewport({ width: 1920, height: 1080 });
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        // Navigate to the page
        console.log('  Navigating to page...');
        await page.goto(targetUrl, { 
          waitUntil: 'networkidle2',
          timeout: 30000 
        });
        
        // Wait for article content to load
        await page.waitForSelector('article, [data-selectable-paragraph]', { timeout: 10000 }).catch(() => {});
        
        // Extract all text content
        const content = await page.evaluate(() => {
          document.querySelectorAll('script, style, nav, header, footer').forEach(el => el.remove());
          const article = document.querySelector('article') || document.body;
          return article.innerText || article.textContent || '';
        });
        
        await browser.close();
        
        console.log(`✅ Puppeteer success! Extracted ${content.length} chars`);
        return res.send(content);
        
      } catch (err) {
        if (browser) await browser.close();
        console.error('Puppeteer error:', err.message);
        throw err;
      }
    }
    
    // For non-Medium URLs, use existing axios method
    const headers = {
      "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
      "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
      "Accept-Language": "en-US,en;q=0.9"
    };

    const response = await axios.get(targetUrl, {
      headers,
      timeout: 20000,
      maxRedirects: 5
    });

    console.log(`✅ Fetched ${response.data.length} chars`);
    return res.send(response.data);

  } catch (err) {
    console.error("❌ Fetch error:", err.message);
    return res.status(500).json({
      error: "Failed to fetch URL",
      message: err.message,
      suggestion: "The website might be blocking automated requests."
    });
  }
});

// ---------------- ERROR HANDLER ----------------

app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);
  res.status(500).json({ success: false, message: 'Internal server error' });
});

// ---------------- INITIALIZE STOCK CACHE ----------------

loadIndianStocks()
  .then(() => console.log('✅ Indian stocks cache initialized'))
  .catch(err => console.warn('⚠️ Initial stock load error:', err.message));

// Auto-refresh every 24 hours
setInterval(() => {
  console.log('🔄 Auto-refreshing Indian stocks cache...');
  loadIndianStocks()
    .catch(e => console.error('Auto-refresh error:', e.message));
}, 24 * 60 * 60 * 1000);

// ---------------- START SERVER ----------------

const PORT = process.env.PORT || 3000;

app.listen(PORT, () => {
  console.log(`
╔════════════════════════════════════════════╗
║   Portfolio Watcher API Server             ║
║   Running on port ${PORT}                      ║
║   Environment: ${process.env.NODE_ENV || 'development'}               ║
╚════════════════════════════════════════════╝
  `);
  console.log(`API root: http://localhost:${PORT}/api`);
  console.log(`Health: http://localhost:${PORT}/api/health`);
  console.log(`Indian Stock Search: http://localhost:${PORT}/api/stocks/search-indian`);
});

// Export for tests
module.exports = app;