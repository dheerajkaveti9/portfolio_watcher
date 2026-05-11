const express = require("express");
const router = express.Router();
const axios = require("axios");
const crypto = require("crypto");
const pool = require("../db");
require("dotenv").config();

// ============================================
// 1. Generate Login URL
// ============================================
router.get('/login-url', (req, res) => {
    try {
        const apiKey = process.env.ZERODHA_API_KEY;
        const redirectUri = process.env.ZERODHA_REDIRECT_URI;

        console.log('🔍 Zerodha Login URL Request');
        console.log('API Key:', apiKey ? `${apiKey.substring(0, 8)}...` : 'NOT SET');
        console.log('Redirect URI:', redirectUri);

        if (!apiKey || apiKey === 'your_kite_api_key') {
            return res.json({ 
                success: false, 
                message: "Zerodha API key not configured in .env" 
            });
        }

        if (!redirectUri) {
            return res.json({ 
                success: false, 
                message: "Redirect URI missing in .env" 
            });
        }

        // ✅ Correct Zerodha login URL format (no redirect_url parameter needed)
        const loginUrl = `https://kite.zerodha.com/connect/login?v=3&api_key=${apiKey}`;

        console.log('✅ Login URL generated');
        
        // ✅ FIXED: Return 'loginUrl' not 'url' to match home.html expectations
        res.json({ 
            success: true, 
            loginUrl: loginUrl  // Changed from 'url' to 'loginUrl'
        });
    } catch (err) {
        console.error("❌ Zerodha login URL error:", err);
        res.status(500).json({ 
            success: false, 
            message: "Failed to generate login URL" 
        });
    }
});

// ============================================
// 2. OAuth Callback Handler
// ============================================
router.get("/callback", async (req, res) => {
    try {
        const { request_token, status } = req.query;

        console.log('🔄 Zerodha Callback');
        console.log('Request Token:', request_token ? 'Present' : 'Missing');
        console.log('Status:', status);

        // Validate callback
        if (!request_token) {
            console.error('❌ Missing request_token');
            return res.redirect("/portfolio_watcher/home.html?broker=zerodha&error=missing_request_token");
        }

        if (status === 'error') {
            console.error('❌ User denied access or error occurred');
            return res.redirect("/portfolio_watcher/home.html?broker=zerodha&error=access_denied");
        }

        // Check user session
        const userId = req.session.user_id;
        if (!userId) {
            console.error('❌ No user session found');
            return res.redirect("/portfolio_watcher/login.html?error=session_expired");
        }

        console.log('👤 User ID:', userId);

        const apiKey = process.env.ZERODHA_API_KEY;
        const apiSecret = process.env.ZERODHA_API_SECRET;

        if (!apiKey || !apiSecret) {
            console.error('❌ Missing API credentials');
            return res.redirect("/portfolio_watcher/home.html?broker=zerodha&error=config_error");
        }

        // Generate checksum (Required by Zerodha)
        const checksum = crypto
            .createHash("sha256")
            .update(apiKey + request_token + apiSecret)
            .digest("hex");

        console.log('🔐 Checksum generated');
        console.log('📡 Exchanging token with Zerodha...');

        // ✅ FIXED: Correct token exchange format using form data
        const response = await axios.post(
            "https://api.kite.trade/session/token",
            new URLSearchParams({
                api_key: apiKey,
                request_token: request_token,
                checksum: checksum
            }).toString(),
            {
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Kite-Version": "3"
                }
            }
        );

        console.log('📥 Response received from Zerodha');

        const accessToken = response.data?.data?.access_token;
        const zerodhaUserId = response.data?.data?.user_id;

        if (!accessToken) {
            console.error('❌ No access token in response');
            console.error('Response:', JSON.stringify(response.data, null, 2));
            return res.redirect("/portfolio_watcher/home.html?broker=zerodha&error=no_token");
        }

        console.log('✅ Access Token received');
        console.log('👤 Zerodha User ID:', zerodhaUserId);

        // Save to database
        await pool.query(
            `INSERT INTO broker_connections (user_id, broker_name, access_token, broker_user_id, created_at)
             VALUES (?, 'zerodha', ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
                access_token = VALUES(access_token), 
                broker_user_id = VALUES(broker_user_id),
                updated_at = NOW()`,
            [userId, accessToken, zerodhaUserId || null]
        );

        console.log('💾 Connection saved to database');
        console.log('✅ Zerodha integration successful!');

        // Redirect back to Apache (port 80) where frontend is hosted
        return res.redirect("http://localhost/portfolio_watcher/home.html?broker=zerodha&status=connected");
        
    } catch (err) {
        console.error("❌ Zerodha callback error:", err.response?.data || err.message);
        console.error("Full error:", err);
        
        // More specific error messages
        if (err.response?.status === 403) {
            return res.redirect("/portfolio_watcher/home.html?broker=zerodha&error=invalid_token");
        }
        
        return res.redirect("/portfolio_watcher/home.html?broker=zerodha&error=callback_failed");
    }
});

// ============================================
// 3. Fetch Holdings
// ============================================
// Fixed Zerodha Holdings Route
router.get("/holdings", async (req, res) => {
  try {
    const userId = req.session.user_id;
    
    console.log('📊 Zerodha Holdings Request - User ID:', userId);
    
    if (!userId) {
      return res.status(401).json({ 
        success: false, 
        message: "Not authenticated",
        holdings: []
      });
    }

    // Get access token
    const [rows] = await pool.query(
      'SELECT access_token FROM broker_connections WHERE user_id = ? AND broker_name = ? LIMIT 1',
      [userId, 'zerodha']
    );

    console.log('Database query result:', rows.length, 'connection(s) found');

    if (rows.length === 0) {
      return res.json({ 
        success: false, 
        message: "Zerodha not connected",
        holdings: []
      });
    }

    const accessToken = rows[0].access_token;
    console.log('Token found:', accessToken ? 'Yes' : 'No');

    // Check if it's a mock token
    if (accessToken === 'MOCK_TOKEN_DEV') {
      console.log('⚠️ Mock token detected - returning sample holdings');
      return res.json({
        success: true,
        holdings: [
          {
            symbol: 'INFY',
            quantity: 20,
            average_price: 1450.00,
            ltp: 1500.00,
            broker: 'zerodha'
          },
          {
            symbol: 'HDFC',
            quantity: 15,
            average_price: 1620.00,
            ltp: 1650.00,
            broker: 'zerodha'
          }
        ]
      });
    }

    const apiKey = process.env.ZERODHA_API_KEY;

    console.log('🔄 Fetching real holdings from Zerodha API...');

    // Fetch holdings
    const response = await axios.get(
      "https://api.kite.trade/portfolio/holdings",
      {
        headers: {
          'Authorization': `token ${apiKey}:${accessToken}`,
          'X-Kite-Version': '3'
        }
      }
    );

    console.log('✅ Zerodha API Response:', {
      status: response.status,
      holdingsCount: response.data?.data?.length || 0
    });

    const rawHoldings = response.data?.data || [];

    // Normalize to standard format
    const normalizedHoldings = rawHoldings.map(h => ({
      symbol: h.tradingsymbol || h.symbol,
      quantity: parseFloat(h.quantity || 0),
      average_price: parseFloat(h.average_price || 0),
      ltp: parseFloat(h.last_price || h.ltp || 0),
      broker: 'zerodha'
    }));

    console.log('📦 Returning', normalizedHoldings.length, 'normalized holdings');

    return res.json({ 
      success: true, 
      holdings: normalizedHoldings 
    });

  } catch (err) {
    console.error("❌ Zerodha Holdings Error:", {
      message: err.message,
      response: err.response?.data,
      status: err.response?.status
    });
    
    if (err.response?.status === 403 || err.response?.status === 401) {
      return res.json({ 
        success: false, 
        message: "Token expired or invalid. Please reconnect Zerodha.",
        holdings: []
      });
    }

    return res.status(500).json({ 
      success: false, 
      message: err.response?.data?.message || "Failed to fetch holdings",
      holdings: []
    });
  }
});
// ============================================
// 4. Fetch Positions (Zerodha)
// ============================================
router.get("/positions", async (req, res) => {
  try {
    const userId = req.session.user_id;
    console.log('📊 Zerodha Positions Request - User ID:', userId);

    if (!userId) {
      return res.status(401).json({
        success: false,
        message: "Not authenticated",
        positions: []
      });
    }

    const [rows] = await pool.query(
      'SELECT access_token FROM broker_connections WHERE user_id = ? AND broker_name = "zerodha" LIMIT 1',
      [userId]
    );

    if (rows.length === 0) {
      return res.json({
        success: false,
        message: "Zerodha not connected",
        positions: []
      });
    }

    const accessToken = rows[0].access_token;
    const apiKey = process.env.ZERODHA_API_KEY;

    console.log("🔄 Fetching Zerodha positions...");

    const response = await axios.get(
      "https://api.kite.trade/portfolio/positions",
      {
        headers: {
          "Authorization": `token ${apiKey}:${accessToken}`,
          "X-Kite-Version": "3"
        }
      }
    );

    console.log("✅ Zerodha positions fetched");

    const netPositions = response.data?.data?.net || [];

    const normalized = netPositions.map(p => ({
      symbol: p.tradingsymbol,
      quantity: parseFloat(p.quantity),
      average_price: parseFloat(p.average_price),
      ltp: parseFloat(p.last_price),
      broker: 'zerodha',
      type: 'position'
    }));

    return res.json({
      success: true,
      positions: normalized
    });

  } catch (err) {
    console.error("❌ Zerodha Positions Error:", err.response?.data || err.message);
    return res.status(500).json({
      success: false,
      message: "Failed to fetch Zerodha positions",
      positions: []
    });
  }
});


// Mock connection for development
router.post("/mock-connect", async (req, res) => {
  try {
    const userId = req.session.user_id;
    
    if (!userId) {
      return res.status(401).json({ 
        success: false, 
        message: "Not authenticated" 
      });
    }

    // Create mock connection with fake token
    await pool.query(
      `INSERT INTO broker_connections (user_id, broker_name, access_token, broker_user_id, created_at)
       VALUES (?, 'zerodha', 'MOCK_TOKEN_DEV', 'MOCK_USER', NOW())
       ON DUPLICATE KEY UPDATE 
          access_token = 'MOCK_TOKEN_DEV', 
          broker_user_id = 'MOCK_USER',
          updated_at = NOW()`,
      [userId]
    );

    console.log('✅ Zerodha mock connection created for user:', userId);

    return res.json({ 
      success: true, 
      message: "Mock connection created successfully" 
    });

  } catch (err) {
    console.error("Mock connect error:", err);
    return res.status(500).json({ 
      success: false, 
      message: "Failed to create mock connection" 
    });
  }
});

// ============================================
// 4. Disconnect Broker
// ============================================
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
            [userId, 'zerodha']
        );

        console.log('🔌 Zerodha disconnected for user:', userId);

        return res.json({ 
            success: true, 
            message: "Zerodha disconnected successfully" 
        });

    } catch (err) {
        console.error("Disconnect error:", err);
        return res.status(500).json({ 
            success: false, 
            message: "Failed to disconnect" 
        });
    }
});

module.exports = router;