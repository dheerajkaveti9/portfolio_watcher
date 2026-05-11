// angel.js
require("dotenv").config();
const axios = require("axios");

module.exports = function (app) {

    // ============================================================
    // 1) GENERATE ANGEL ONE LOGIN URL
    // ============================================================
    app.get("/api/angel/login-url", (req, res) => {
        const apiKey = process.env.ANGEL_API_KEY;
        const redirectUrl = process.env.ANGEL_REDIRECT_URL;

        if (!apiKey || !redirectUrl) {
            return res.status(500).json({ error: "SmartAPI credentials missing" });
        }

        const loginUrl =
            `https://smartapi.angelone.in/publisher-login?api_key=${apiKey}&redirect_uri=${encodeURIComponent(redirectUrl)}`;

        res.json({ success: true, loginUrl });
    });

    // ============================================================
    // 2) EXCHANGE request_token → jwtToken
    // ============================================================
    app.post("/api/angel/exchange-token", async (req, res) => {
        try {
            const { request_token } = req.body;

            if (!request_token) {
                return res.status(400).json({ error: "Missing request_token" });
            }

            const response = await axios.post(
                "https://apiconnect.angelbroking.com/rest/auth/angelbroking/jwt/v1/generateSession",
                {
                    "clientcode": process.env.ANGEL_CLIENT_ID,
                    "password": process.env.ANGEL_API_SECRET,
                    "totp": process.env.ANGEL_TOTP || "" 
                }
            );

            const jwtToken = response.data.data.jwtToken;
            res.json({ success: true, jwtToken });

        } catch (err) {
            console.error("Token exchange error:", err?.response?.data || err);
            res.status(500).json({ error: "Failed to exchange token" });
        }
    });

    // ============================================================
    // 3) FETCH HOLDINGS (PORTFOLIO)
    // ============================================================
    app.post("/api/angel/holdings", async (req, res) => {
        try {
            const { jwtToken } = req.body;

            if (!jwtToken)
                return res.status(400).json({ error: "Missing jwtToken" });

            const response = await axios.get(
                "https://apiconnect.angelbroking.com/rest/portfolio/holdings/v1",
                {
                    headers: {
                        "X-PrivateKey": process.env.ANGEL_API_KEY,
                        "Authorization": `Bearer ${jwtToken}`,
                        "Accept": "application/json"
                    }
                }
            );

            res.json({
                success: true,
                data: response.data
            });

        } catch (err) {
            console.error("Holdings fetch error:", err?.response?.data || err);
            res.status(500).json({ error: "Failed to fetch holdings" });
        }
    });
};
