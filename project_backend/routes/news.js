const express = require('express');
const router = express.Router();
const axios = require('axios');
const pool = require('../db');

/* -------------------------------------------------
   ⭐ ADDED: Notion URL Auto-Fixer
------------------------------------------------- */
function fixNotionURL(rawUrl) {
  if (!rawUrl || typeof rawUrl !== 'string') return rawUrl;

  try {
    const maybe = rawUrl.trim();
    if (!/^https?:\/\//i.test(maybe)) return rawUrl;

    const u = new URL(maybe);

    if (u.hostname.includes('notion.so') || u.hostname.includes('notion.site')) {

      // convert notion.so → notion.site
      if (u.hostname === 'www.notion.so' || u.hostname === 'notion.so') {
        u.hostname = u.hostname.replace('notion.so', 'notion.site');
      }

      // ensure pvs param
      if (!u.searchParams.has('pvs')) {
        u.searchParams.set('pvs', '4');
      }

      // ensure export param
      if (!u.searchParams.has('export')) {
        u.searchParams.set('export', '');
      }

      return u.toString();
    }

    return rawUrl;
  } catch (e) {
    return rawUrl;
  }
}

/* -------------------------------------------------
   AUTH MIDDLEWARE
------------------------------------------------- */
function requireAuth(req, res, next) {
  if (!req.session.user_id) {
    return res.status(401).json({ success: false, message: 'Not authenticated' });
  }
  next();
}

/* -------------------------------------------------
   ADD NEWS SOURCE
------------------------------------------------- */
router.post('/add-url', requireAuth, async (req, res) => {
  try {
    const { url, name } = req.body;
    const userId = req.session.user_id;

    if (!url) {
      return res.status(400).json({ success: false, message: 'URL is required' });
    }

    let sourceName = name;
    if (!sourceName) {
      try {
        const urlObj = new URL(url);
        sourceName = urlObj.hostname.replace('www.', '');
      } catch (e) {
        sourceName = url;
      }
    }

    await pool.query(
      'INSERT INTO news_sources (user_id, url, name) VALUES (?, ?, ?)',
      [userId, url, sourceName]
    );

    res.json({ success: true, message: 'News source added' });
  } catch (err) {
    console.error('Add news source error:', err);
    res.status(500).json({ success: false, message: 'Failed to add source' });
  }
});

/* -------------------------------------------------
   GET SOURCES
------------------------------------------------- */
router.get('/sources', requireAuth, async (req, res) => {
  try {
    const userId = req.session.user_id;

    const [sources] = await pool.query(
      'SELECT * FROM news_sources WHERE user_id = ? ORDER BY created_at DESC',
      [userId]
    );

    res.json({ success: true, sources });
  } catch (err) {
    console.error('Get sources error:', err);
    res.status(500).json({ success: false, message: 'Failed to fetch sources' });
  }
});

/* -------------------------------------------------
   DELETE SOURCE
------------------------------------------------- */
router.delete('/sources/:id', requireAuth, async (req, res) => {
  try {
    const userId = req.session.user_id;
    const sourceId = req.params.id;

    await pool.query(
      'DELETE FROM news_sources WHERE id = ? AND user_id = ?',
      [sourceId, userId]
    );

    res.json({ success: true, message: 'Source deleted' });
  } catch (err) {
    console.error('Delete source error:', err);
    res.status(500).json({ success: false, message: 'Failed to delete source' });
  }
});

/* -------------------------------------------------
   ⭐ FIXED: SEARCH FOR MENTIONS
------------------------------------------------- */
router.get('/search-mentions', requireAuth, async (req, res) => {
  try {
    const userId = req.session.user_id;

    console.log('🔍 Starting news search for user:', userId);

    /* -------------------------------------------------
       STEP 1: Build Portfolio Stock List
    ------------------------------------------------- */
    let portfolioStocks = [];

    // Manual stocks
    try {
      const manualStocksQuery = `SELECT DISTINCT symbol FROM user_stocks WHERE user_id = ?`;
      const [manualStocks] = await pool.query(manualStocksQuery, [userId]);

      portfolioStocks = portfolioStocks.concat(
        manualStocks.map(s => s.symbol.toUpperCase())
      );
    } catch (err) {
      console.error('Error fetching manual stocks:', err);
    }

    // Broker stocks (mock)
    try {
      const [brokers] = await pool.query(
        'SELECT DISTINCT broker_name FROM broker_connections WHERE user_id = ?',
        [userId]
      );

      for (const broker of brokers) {
        const brokerName = broker.broker_name;

        const [tokenRows] = await pool.query(
          'SELECT access_token FROM broker_connections WHERE user_id = ? AND broker_name = ?',
          [userId, brokerName]
        );

        if (tokenRows.length > 0 && tokenRows[0].access_token === 'MOCK_TOKEN_DEV') {
          if (brokerName === 'angelone') {
            portfolioStocks.push('RELIANCE', 'TCS', 'INFY');
          } else if (brokerName === 'zerodha') {
            portfolioStocks.push('INFY', 'HDFC', 'SBIN');
          }
        }
      }
    } catch (err) {
      console.error('Error fetching broker holdings:', err);
    }

    portfolioStocks = [...new Set(portfolioStocks)].filter(Boolean);

    if (portfolioStocks.length === 0) {
      return res.json({
        success: true,
        message: 'No stocks in portfolio',
        portfolioStocks: [],
        mentions: []
      });
    }

    /* -------------------------------------------------
       STEP 2: Get News Sources
    ------------------------------------------------- */
    const [sources] = await pool.query(
      'SELECT * FROM news_sources WHERE user_id = ?',
      [userId]
    );

    if (sources.length === 0) {
      return res.json({
        success: true,
        message: 'No news sources added',
        portfolioStocks,
        mentions: []
      });
    }

    /* -------------------------------------------------
       STEP 3: Search Each Source
    ------------------------------------------------- */
    const mentions = [];

    for (const source of sources) {
      try {
        console.log(`\n🔎 Searching ${source.name} (${source.url})`);

        // ⭐ APPLY FIX HERE
        const fixedUrl = fixNotionURL(source.url);
        console.log(`🔧 Final URL used: ${fixedUrl}`);

        const response = await axios.get(fixedUrl, {
          timeout: 10000,
          headers: {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
          }
        });

        const content = response.data.toString().toUpperCase();

        const stocksFound = [];

        for (const stock of portfolioStocks) {
          if (content.includes(stock.toUpperCase())) {
            stocksFound.push(stock);
          }
        }

        mentions.push({
          source: source.name,
          url: fixedUrl,
          stocksFound,
          totalStocks: portfolioStocks.length,
          error: null
        });

      } catch (err) {
        mentions.push({
          source: source.name,
          url: source.url,
          stocksFound: [],
          totalStocks: portfolioStocks.length,
          error: err.message
        });
      }
    }

    res.json({
      success: true,
      portfolioStocks,
      mentions
    });

  } catch (err) {
    console.error('❌ Search mentions error:', err);
    res.status(500).json({
      success: false,
      message: 'Failed to search mentions',
      error: err.message
    });
  }
});

module.exports = router;
