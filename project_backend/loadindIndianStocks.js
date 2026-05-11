// loaders/loadIndianStocks.js
// Try load from disk
try {
const txt = await fs.readFile(dataPath, 'utf8');
const parsed = JSON.parse(txt);
if (parsed && Array.isArray(parsed.stocks) && parsed.stocks.length > 0) {
indianStocksCache.stocks = parsed.stocks;
indianStocksCache.lastUpdated = parsed.lastUpdated;
console.log(`💾 Loaded ${parsed.stocks.length} stocks from cache file`);
indianStocksCache.isLoading = false;
return indianStocksCache;
}
} catch (e) {
console.log('📁 No cache file, will fetch fresh data');
}
}


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
await fs.writeFile(dataPath, JSON.stringify({ stocks: finalStocks, lastUpdated: indianStocksCache.lastUpdated }, null, 2), 'utf8');
console.log('💾 Saved combined stocks to cache file');
} catch (e) {
console.warn('⚠️ Could not save cache file:', e.message);
}


console.log(`✅ Loaded total stocks: ${finalStocks.length}`);
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