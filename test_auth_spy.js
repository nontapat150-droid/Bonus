const { GoogleAuth } = require('google-auth-library');

async function main() {
  const auth = new GoogleAuth({
    keyFile: './apis-1cd5e-firebase-adminsdk-fbsvc-2d77164594.json',
    scopes: ['https://www.googleapis.com/auth/firebase.messaging'],
  });
  
  try {
    const client = await auth.getClient();
    // Spy on the request
    client.request = async (opts) => {
      console.log(opts.data); // This is the URLSearchParams with grant_type and assertion
      return { data: { access_token: 'fake' } };
    };
    await client.getAccessToken();
  } catch (error) {
    console.error(error.message);
  }
}

main();
