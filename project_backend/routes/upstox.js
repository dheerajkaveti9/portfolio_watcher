const express = require("express");
const router = express.Router();
const axios = require("axios");
const pool = require("../db");
require("dotenv").config();

// Login URL
router.get("/login-url", (req, res) => {
  try {
    const redirect = encodeURIComponent(process.env.UPSTOX_REDIRECT_URL);
    const clientId = process.env.UPSTOX_CLIENT_ID;

    const url = `https://api.upstox.com/v2/login/authorize?client_id=${clientId}&response_type=code&redirect_uri=${redirect}`;

    return res.json({ success: true, loginUrl: url });
  } catch (err) {
    return res.status(500).json({ success: false, message: "Failed to generate login URL" });
  }
});

// Callback
router.get("/callback", async (req, res) => {
  try {
    const { code } = req.query;

    if (!code) {
      return res.redirect("/portfolio_watcher/home.html?broker=upstox&error=missing_code");
    }

    const response = await axios.post("https://api.upstox.com/v2/login/token", null, {
      params: {
        client_id: process.env.UPSTOX_CLIENT_ID,
        client_secret: process.env.UPSTOX_CLIENT_SECRET,
        grant_type: "authorization_code",
        redirect_uri: process.env.UPSTOX_REDIRECT_URL,
        code: code,
      },
    });

    const accessToken = response.data?.access_token;

    await pool.query(
      `INSERT INTO broker_connections (user_id, broker_name, access_token)
       VALUES (?, 'upstox', ?)
       ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), updated_at = NOW()`,
      [req.session.user_id, accessToken]
    );

    return res.redirect("/portfolio_watcher/home.html?broker=upstox&status=connected");
  } catch (err) {
    console.error("Upstox callback error:", err.response?.data || err.message);
    return res.redirect("/portfolio_watcher/home.html?broker=upstox&error=callback_failed");
  }
});

// Holdings
router.get("/holdings", async (req, res) => {
  try {
    const userId = req.session.user_id;

    if (!userId) {
      return res.status(401).json({
        success: false,
        message: "Not authenticated"
      });
    }

    const [rows] = await pool.query(
      `SELECT access_token FROM broker_connections 
       WHERE user_id = ? AND broker_name = 'upstox'`,
      [userId]
    );

    if (rows.length === 0) {
      return res.json({
        success: false,
        message: "Upstox not connected",
      });
    }

    const token = rows[0].access_token;

    const response = await axios.get(
      "https://api.upstox.com/v2/portfolio/long-term-holdings",
      {
        headers: {
          "Authorization": `Bearer ${token}`,
          "Accept": "application/json"
        }
      }
    );

    return res.json({
      success: true,
      holdings: response.data?.data || [],
    });

  } catch (err) {
    console.error("Upstox Holdings Error:", err.response?.data || err.message);

    return res.status(400).json({
      success: false,
      message: err.response?.data?.message || "Failed to fetch holdings",
    });
  }
});

// Disconnect
router.post("/disconnect", async (req, res) => {
  try {
    const userId = req.session.user_id;
    
    if (!userId) {
      return res.status(401).json({ 
        success: false, 
        message: "Not authenticated" 
      });
    }

    await pool.query(
      'DELETE FROM broker_connections WHERE user_id = ? AND broker_name = ?',
      [userId, 'upstox']
    );

    console.log('🔌 Upstox disconnected for user:', userId);

    return res.json({ 
      success: true, 
      message: "Upstox disconnected successfully" 
    });

  } catch (err) {
    console.error("Upstox disconnect error:", err);
    return res.status(500).json({ 
      success: false, 
      message: "Failed to disconnect" 
    });
  }
});

module.exports = router;