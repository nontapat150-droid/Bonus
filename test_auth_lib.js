const { GoogleAuth } = require('google-auth-library');

async function main() {
  const auth = new GoogleAuth({
    keyFile: './apis-1cd5e-firebase-adminsdk-fbsvc-2d77164594.json',
    scopes: ['https://www.googleapis.com/auth/firebase.messaging'],
  });
  
  try {
    const client = await auth.getClient();
    const token = await client.getAccessToken();
    console.log("Success! Token:", token.token);
  } catch (error) {
    console.error("Error:", error.message);
  }
}

main();
