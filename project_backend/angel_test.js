const { authenticator } = require('otplib');

// Generate TOTP from secret
const totp = authenticator.generate('A66RLJRSPG3OC52TMWVYWT4GN4');
console.log('Generated TOTP:', totp);

// Use this TOTP in your login