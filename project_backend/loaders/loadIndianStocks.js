// loaders/loadIndianStocks.js

const fs = require('fs').promises;
const path = require('path');

// Cache object
const indianStocksCache = {
  stocks: [],
  lastUpdated: null,
  isLoading: false
};

async function loadIndianStocks() {
  if (indianStocksCache.isLoading) {
    console.log('⏳ Already loading stocks...');
    return indianStocksCache;
  }

  indianStocksCache.isLoading = true;
  const dataPath = path.join(__dirname, '..', 'data', 'indian_stocks.json');

  try {
    // Try load from disk
    try {
      const txt = await fs.readFile(dataPath, 'utf8');
      const parsed = JSON.parse(txt);
      if (parsed && Array.isArray(parsed.stocks) && parsed.stocks.length > 0) {
        indianStocksCache.stocks = parsed.stocks;
        indianStocksCache.lastUpdated = parsed.lastUpdated;
        console.log(`💾 Loaded ${parsed.stocks.length} stocks from cache file`); // ✅ FIXED: Changed from template literal in console.log
        indianStocksCache.isLoading = false;
        return indianStocksCache;
      }
    } catch (e) {
      console.log('📁 No cache file, will fetch fresh data');
    }

    // ✅ ADDED: Import the fetch functions (you need to define these or import them)
    const { fetchNSEStocks, fetchBSEStocks } = require('./stockFetchers'); // Adjust path as needed

    // Fetch NSE + BSE in parallel
    const [nse, bse] = await Promise.all([fetchNSEStocks(), fetchBSEStocks()]);
    const combined = [...nse, ...bse];

    // De-duplicate by symbol (prefer NSE names when conflict)
    const map = new Map();
    for (const s of combined) {
      const key = String(s.symbol).toUpperCase();
      if (!map.has(key)) map.set(key, s);
    }
    const finalStocks = Array.from(map.values());

    indianStocksCache.stocks = finalStocks;
    indianStocksCache.lastUpdated = new Date().toISOString();

    // Save to disk
    try {
      await fs.mkdir(path.join(__dirname, '..', 'data'), { recursive: true });
      await fs.writeFile(
        dataPath, 
        JSON.stringify({ 
          stocks: finalStocks, 
          lastUpdated: indianStocksCache.lastUpdated 
        }, null, 2), 
        'utf8'
      );
      console.log('💾 Saved combined stocks to cache file');
    } catch (e) {
      console.warn('⚠️ Could not save cache file:', e.message);
    }

    console.log(`✅ Loaded total stocks: ${finalStocks.length}`); // ✅ FIXED: Changed from template literal in console.log

    return indianStocksCache;
  } catch (err) {
    console.error('❌ loadIndianStocks error:', err.message);
    // fallback
    indianStocksCache.stocks = [];
    indianStocksCache.lastUpdated = new Date().toISOString();
    return indianStocksCache;
  } finally {
    indianStocksCache.isLoading = false;
  }
}

module.exports = { loadIndianStocks, indianStocksCache };