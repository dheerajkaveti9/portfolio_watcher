// loaders/stockFetchers.js (create this file)

const axios = require('axios');

async function fetchNSEStocks() {
  try {
    const apiKey = process.env.STOCK_API_KEY;
    const response = await axios.get(
      `https://api.twelvedata.com/stocks?exchange=NSE&apikey=${apiKey}`,
      { timeout: 30000 }
    );

    if (response.data && response.data.data) {
      return response.data.data
        .filter(s => s.type === 'Common Stock' || s.type === 'Equity')
        .map(s => ({
          symbol: s.symbol,
          name: s.name,
          exchange: 'NSE',
          type: s.type,
          currency: s.currency || 'INR'
        }));
    }
    return [];
  } catch (err) {
    console.error('❌ NSE fetch error:', err.message);
    return [];
  }
}

async function fetchBSEStocks() {
  try {
    const apiKey = process.env.STOCK_API_KEY;
    const response = await axios.get(
      `https://api.twelvedata.com/stocks?exchange=BSE&apikey=${apiKey}`,
      { timeout: 30000 }
    );

    if (response.data && response.data.data) {
      return response.data.data
        .filter(s => s.type === 'Common Stock' || s.type === 'Equity')
        .map(s => ({
          symbol: s.symbol,
          name: s.name,
          exchange: 'BSE',
          type: s.type,
          currency: s.currency || 'INR'
        }));
    }
    return [];
  } catch (err) {
    console.error('❌ BSE fetch error:', err.message);
    return [];
  }
}

module.exports = { fetchNSEStocks, fetchBSEStocks };