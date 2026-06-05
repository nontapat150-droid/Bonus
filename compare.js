const fs = require('fs');
const crypto = require('crypto');

const keyData = JSON.parse(fs.readFileSync('./apis-1cd5e-firebase-adminsdk-fbsvc-2d77164594.json', 'utf8'));

const header = {alg: 'RS256', typ: 'JWT', kid: keyData.private_key_id};
const now = 1700000000;
const claim = {
  iss: keyData.client_email,
  scope: 'https://www.googleapis.com/auth/firebase.messaging',
  aud: keyData.token_uri,
  exp: now + 3600,
  iat: now
};

function toBase64Url(str) {
  return Buffer.from(str).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

const headerStr = JSON.stringify(header);
const claimStr = JSON.stringify(claim);

const encodedHeader = toBase64Url(headerStr);
const encodedClaim = toBase64Url(claimStr);
const signatureInput = `${encodedHeader}.${encodedClaim}`;

const signer = crypto.createSign('RSA-SHA256');
signer.update(signatureInput);
const signature = signer.sign(keyData.private_key, 'base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

console.log("Header: " + headerStr);
console.log("Claim: " + claimStr);
console.log("Input: " + signatureInput);
console.log("Signature: " + signature);
